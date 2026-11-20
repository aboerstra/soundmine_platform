#include <soundmine/studio/workflow.hpp>

#include <algorithm>
#include <set>
#include <tuple>
#include <utility>

namespace soundmine::studio {
namespace {

using Diagnostics = std::vector<Diagnostic>;

void report(Diagnostics& diagnostics, std::string code, Severity severity, std::string message)
{
    diagnostics.push_back({std::move(code), severity, std::move(message)});
}

template <typename T>
using Index = std::map<std::string, const T*>;

template <typename T>
Index<T> index(const std::vector<T>& records, std::string_view label, Diagnostics& diagnostics)
{
    Index<T> result;
    for (const auto& record : records) {
        if (record.id.empty() || !result.emplace(record.id, &record).second)
            report(diagnostics, "INVALID_IDENTIFIER", Severity::error,
                   std::string(label) + " identifier is blank or repeated: " + record.id);
    }
    return result;
}

template <typename T>
const T* find(const Index<T>& index, const std::string& id)
{
    const auto found = index.find(id);
    return found == index.end() ? nullptr : found->second;
}

struct Catalog {
    Index<DeviceProfile> instances;
    std::map<std::string, Index<Port>> ports;
    std::map<std::string, Index<Capability>> capabilities;
};

Catalog buildCatalog(const std::vector<DeviceProfile>& profiles, const ConnectionGraph& graph,
                     Diagnostics& diagnostics)
{
    Catalog catalog;
    const auto profileIndex = index(profiles, "Profile", diagnostics);
    for (const auto& [id, profile] : profileIndex) {
        catalog.ports.emplace(id, index(profile->ports, id + " port", diagnostics));
        catalog.capabilities.emplace(id, index(profile->capabilities, id + " capability", diagnostics));
    }
    for (const auto& [id, device] : index(graph.devices, "Device", diagnostics)) {
        if (const auto* profile = find(profileIndex, device->profileId))
            catalog.instances.emplace(id, profile);
        else
            report(diagnostics, "UNKNOWN_PROFILE", Severity::error, id + ": " + device->profileId);
    }
    return catalog;
}

const Port* portAt(const Catalog& catalog, const Endpoint& endpoint)
{
    const auto* profile = find(catalog.instances, endpoint.deviceId);
    return profile ? find(catalog.ports.at(profile->id), endpoint.portId) : nullptr;
}

void validatePhysical(const Catalog& catalog, const ConnectionGraph& graph, Diagnostics& diagnostics)
{
    std::set<Endpoint> destinations;
    for (const auto& [id, edge] : index(graph.connections, "Connection", diagnostics)) {
        const auto* source = portAt(catalog, edge->from);
        const auto* target = portAt(catalog, edge->to);
        if (!source || !target) {
            report(diagnostics, "UNKNOWN_PHYSICAL_ENDPOINT", Severity::error, id);
            continue;
        }
        if (source->signal != edge->signal || target->signal != edge->signal)
            report(diagnostics, "PHYSICAL_SIGNAL_MISMATCH", Severity::error, id);
        if (edge->from == edge->to || source->direction == Direction::input ||
            target->direction == Direction::output)
            report(diagnostics, "PHYSICAL_DIRECTION_INVALID", Severity::error, id);
        if (!destinations.insert(edge->to).second)
            report(diagnostics, "PHYSICAL_INPUT_CONFLICT", Severity::error, id);
    }
}

void validateEvents(const EventGraph& graph, Diagnostics& diagnostics)
{
    const auto operations = index(graph.operations, "Operation", diagnostics);
    std::map<std::string, std::size_t> incoming;
    std::multimap<std::string, std::string> outgoing;
    std::set<std::pair<EventEndpoint, EventEndpoint>> edges;
    std::set<EventEndpoint> destinations;
    for (const auto& [id, operation] : operations) {
        incoming.emplace(id, 0);
        if (operation->inputs.contains("") || operation->outputs.contains(""))
            report(diagnostics, "INVALID_EVENT_PORT", Severity::error, id);
    }

    for (const auto& [id, route] : index(graph.routes, "Event route", diagnostics)) {
        const auto* source = find(operations, route->from.operationId);
        const auto* target = find(operations, route->to.operationId);
        if (!source || !target || !source->outputs.contains(route->from.portId) ||
            !target->inputs.contains(route->to.portId)) {
            report(diagnostics, "UNKNOWN_EVENT_ENDPOINT", Severity::error, id);
            continue;
        }
        if (!edges.emplace(route->from, route->to).second)
            report(diagnostics, "DUPLICATE_EVENT_ROUTE", Severity::error, id);
        const auto& input = target->inputs.at(route->to.portId);
        if (source->outputs.at(route->from.portId) != input.event)
            report(diagnostics, "EVENT_TYPE_MISMATCH", Severity::error, id);
        if (!destinations.insert(route->to).second) {
            if (!input.merge)
                report(diagnostics, "EVENT_MERGE_REQUIRED", Severity::error, id);
            else if (input.event == Event::clock || input.event == Event::notes)
                report(diagnostics, "EVENT_OWNERSHIP_UNRESOLVED", Severity::error, id);
        }
        outgoing.emplace(source->id, target->id);
        ++incoming.at(target->id);
    }

    std::vector<std::string> ready;
    for (const auto& [id, count] : incoming)
        if (count == 0)
            ready.push_back(id);
    std::size_t visited = 0;
    while (!ready.empty()) {
        auto id = std::move(ready.back());
        ready.pop_back();
        ++visited;
        const auto [begin, end] = outgoing.equal_range(id);
        for (auto edge = begin; edge != end; ++edge)
            if (--incoming.at(edge->second) == 0)
                ready.push_back(edge->second);
    }
    if (visited != operations.size())
        report(diagnostics, "EVENT_CYCLE", Severity::error, "Logical event routes contain a cycle.");
}

void validateIntent(const Catalog& catalog, const Intent& intent, Diagnostics& diagnostics)
{
    if (intent.id.empty() || intent.name.empty() || intent.roles.empty() || intent.goals.empty() ||
        std::ranges::find(intent.goals, "") != intent.goals.end())
        report(diagnostics, "INVALID_INTENT", Severity::error, "Intent requires an ID, name, roles and goals.");
    for (const auto& [role, id] : intent.roles)
        if (role.empty() || !find(catalog.instances, id))
            report(diagnostics, "UNKNOWN_ROLE_DEVICE", Severity::error, role + ": " + id);
    for (const auto& requirement : intent.requirements)
        if (!intent.roles.contains(requirement.role) || requirement.capabilityId.empty())
            report(diagnostics, "INVALID_REQUIREMENT", Severity::error,
                   requirement.role + ": " + requirement.capabilityId);
    if (intent.eventGraph)
        validateEvents(*intent.eventGraph, diagnostics);
}

bool satisfies(Evidence actual, Evidence minimum)
{
    return actual == Evidence::verified || actual == minimum || minimum == Evidence::unverified;
}

void checkRequirements(const Catalog& catalog, const Intent& intent, Diagnostics& diagnostics)
{
    for (const auto& requirement : intent.requirements) {
        const auto* profile = catalog.instances.at(intent.roles.at(requirement.role));
        const auto* capability = find(catalog.capabilities.at(profile->id), requirement.capabilityId);
        const auto subject = requirement.role + ": " + requirement.capabilityId;
        if (!capability) {
            const auto severity = requirement.priority == Priority::hard ? Severity::error : Severity::info;
            report(diagnostics, "CAPABILITY_MISSING", severity, subject);
        } else if (!satisfies(capability->evidence, requirement.minimumEvidence)) {
            const auto severity = requirement.priority == Priority::hard ? Severity::warning : Severity::info;
            report(diagnostics, "CAPABILITY_EVIDENCE_INSUFFICIENT", severity, subject);
        }
    }
}

std::string roleDevice(const Intent& intent, const std::string& role)
{
    const auto found = intent.roles.find(role);
    return found == intent.roles.end() ? std::string{} : found->second;
}

std::vector<const Connection*> directRoutes(const ConnectionGraph& graph, const std::string& source,
                                             const std::string& target, Signal signal)
{
    std::vector<const Connection*> matches;
    for (const auto& cable : graph.connections)
        if (cable.signal == signal && cable.from.deviceId == source && cable.to.deviceId == target)
            matches.push_back(&cable);
    return matches;
}

void checkPitchIntervention(const Catalog& catalog, const Intent& intent, const std::string& sequencer,
                            const std::vector<const Connection*>& pitch,
                            const std::optional<VerifiedPitchIntervention>& verification,
                            Diagnostics& diagnostics)
{
    if (!intent.constraints.requirePerNoteHarmonicAdaptation.value_or(false))
        return;
    const auto harmony = roleDevice(intent, "harmonySource");
    const auto* profile = find(catalog.instances, sequencer);
    if (verification && profile && !harmony.empty() && verification->harmonyDeviceId == harmony &&
        verification->sequencerDeviceId == sequencer && pitch.size() == 1) {
        const auto& cable = *pitch.front();
        const auto* capability = find(catalog.capabilities.at(profile->id), "external-per-note-pitch-control");
        if (verification->pitchConnectionId == cable.id && cable.confirmation == Confirmation::confirmed &&
            capability && capability->evidence == Evidence::verified)
            return;
    }
    report(diagnostics, "PITCH_PATH_UNRESOLVED", Severity::warning,
           "Per-note harmonic adaptation requires a verified intervention on the active pitch route.");
}

void evaluatePaths(const Catalog& catalog, const ConnectionGraph& graph, const Intent& intent,
                   const std::optional<VerifiedPitchIntervention>& verification, ExecutionPlan& result)
{
    const auto sequencer = roleDevice(intent, "patternSource");
    const auto synth = roleDevice(intent, "soundGenerator");
    const auto pitch = directRoutes(graph, sequencer, synth, Signal::pitchCv);
    const auto gate = directRoutes(graph, sequencer, synth, Signal::gate);
    const auto velocity = directRoutes(graph, sequencer, synth, Signal::velocityCv);
    const auto preserve = [&](const auto& routes) {
        for (const auto* cable : routes) {
            result.retainedConnections.push_back(cable->id);
            if (cable->confirmation != Confirmation::confirmed ||
                portAt(catalog, cable->from)->evidence != Evidence::verified ||
                portAt(catalog, cable->to)->evidence != Evidence::verified)
                report(result.diagnostics, "PHYSICAL_PATH_UNVERIFIED", Severity::warning, cable->id);
        }
    };

    if (intent.constraints.preserveDirectCvGate.value_or(false)) {
        preserve(pitch);
        preserve(gate);
        if (pitch.empty() || gate.empty())
            report(result.diagnostics, "DIRECT_CV_GATE_MISSING", Severity::error,
                   "The pattern source and sound generator require direct pitch CV and gate routes.");
        else {
            report(result.diagnostics, "HARDWARE_PATH_PRESERVED", Severity::info,
                   "The plan retains the direct hardware pitch CV and gate routes.");
        }
    }
    if (intent.constraints.preserveHardwareVelocityCv.value_or(false))
        preserve(velocity);
    checkPitchIntervention(catalog, intent, sequencer, pitch, verification, result.diagnostics);
}

void sortDiagnostics(Diagnostics& diagnostics)
{
    std::ranges::sort(diagnostics, {}, [](const auto& d) {
        return std::tie(d.code, d.severity, d.message);
    });
}

} // namespace

std::vector<Diagnostic> validate(const std::vector<DeviceProfile>& profiles,
                                 const ConnectionGraph& graph, const Intent& intent)
{
    Diagnostics diagnostics;
    const auto catalog = buildCatalog(profiles, graph, diagnostics);
    validatePhysical(catalog, graph, diagnostics);
    validateIntent(catalog, intent, diagnostics);
    sortDiagnostics(diagnostics);
    return diagnostics;
}

ExecutionPlan plan(const std::vector<DeviceProfile>& profiles, const ConnectionGraph& graph,
                   const Intent& intent, std::optional<VerifiedPitchIntervention> verification)
{
    ExecutionPlan result;
    result.intentId = intent.id;
    const auto catalog = buildCatalog(profiles, graph, result.diagnostics);
    validatePhysical(catalog, graph, result.diagnostics);
    validateIntent(catalog, intent, result.diagnostics);
    if (result.diagnostics.empty()) {
        result.assignments = intent.roles;
        result.eventGraph = intent.eventGraph;
        if (result.eventGraph) {
            std::ranges::sort(result.eventGraph->operations, {}, &Operation::id);
            std::ranges::sort(result.eventGraph->routes, {}, &EventRoute::id);
        }
        checkRequirements(catalog, intent, result.diagnostics);
        evaluatePaths(catalog, graph, intent, verification, result);
        const bool hasHardRequirement = std::ranges::any_of(intent.requirements, [](const auto& requirement) {
            return requirement.priority == Priority::hard;
        });
        if (!hasHardRequirement && !intent.constraints.preserveDirectCvGate.value_or(false) &&
            !intent.constraints.requirePerNoteHarmonicAdaptation.value_or(false))
            report(result.diagnostics, "GOAL_UNSPECIFIED", Severity::warning,
                   "Supply a typed requirement or a direct hardware routing constraint.");
    }

    result.status = Status::feasible;
    for (const auto& diagnostic : result.diagnostics) {
        if (diagnostic.severity == Severity::error) {
            result.status = Status::infeasible;
            break;
        }
        if (diagnostic.severity == Severity::warning)
            result.status = Status::conditional;
    }
    std::ranges::sort(result.retainedConnections);
    sortDiagnostics(result.diagnostics);
    return result;
}

} // namespace soundmine::studio
