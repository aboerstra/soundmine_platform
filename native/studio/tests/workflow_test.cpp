#include <soundmine/studio/workflow.hpp>

#include <algorithm>
#include <filesystem>
#include <fstream>
#include <iostream>
#include <iterator>
#include <stdexcept>

using namespace soundmine::studio;
namespace fs = std::filesystem;

namespace {

std::string load(const fs::path& path)
{
    std::ifstream input(path, std::ios::binary);
    if (!input)
        throw std::runtime_error("Cannot read " + path.string());
    return {std::istreambuf_iterator<char>(input), std::istreambuf_iterator<char>()};
}

void expect(bool condition, std::string_view message)
{
    if (!condition)
        throw std::runtime_error(std::string(message));
}

bool has(const std::vector<Diagnostic>& diagnostics, std::string_view code)
{
    return std::ranges::any_of(diagnostics, [=](const auto& diagnostic) { return diagnostic.code == code; });
}

template <typename Reader>
auto roundTrip(const fs::path& file, Reader read)
{
    auto model = read(load(file));
    const auto serialized = writeJson(model);
    expect(serialized == writeJson(read(serialized)), "Serialization changes after a round trip");
    return model;
}

void rejectsIntent(const std::string& payload)
{
    try {
        readIntent(payload);
    } catch (const std::invalid_argument&) {
        return;
    }
    throw std::runtime_error("Invalid JSON accepted");
}

EventGraph branchAndMerge()
{
    return {{
        {"read", {}, {{"chords", Event::harmony}}},
        {"branch-a", {{"in", {Event::harmony, std::nullopt}}}, {{"out", Event::harmony}}},
        {"branch-b", {{"in", {Event::harmony, std::nullopt}}}, {{"out", Event::harmony}}},
        {"merge", {{"in", {Event::harmony, Merge::priority}}}, {}},
    }, {
        {"to-a", {"read", "chords"}, {"branch-a", "in"}},
        {"to-b", {"read", "chords"}, {"branch-b", "in"}},
        {"from-a", {"branch-a", "out"}, {"merge", "in"}},
        {"from-b", {"branch-b", "out"}, {"merge", "in"}},
    }};
}

void physicalCases(const std::vector<DeviceProfile>& profiles, const ConnectionGraph& graph, const Intent& intent)
{
    auto changed = graph;
    changed.connections[0].signal = Signal::gate;
    expect(has(validate(profiles, changed, intent), "PHYSICAL_SIGNAL_MISMATCH"), "Mismatched signal accepted");
    changed = graph;
    auto secondPitch = changed.connections[0];
    secondPitch.id = "another-pitch";
    changed.connections.push_back(secondPitch);
    expect(has(validate(profiles, changed, intent), "PHYSICAL_INPUT_CONFLICT"), "Two sources on one input accepted");
    changed = graph;
    changed.connections.erase(changed.connections.begin());
    const auto incomplete = plan(profiles, changed, intent);
    expect(incomplete.status == Status::infeasible && has(incomplete.diagnostics, "DIRECT_CV_GATE_MISSING"),
           "Missing pitch cable did not invalidate direct CV/Gate");
    expect(incomplete.retainedConnections == std::vector<std::string>{"gate-direct"}, "Remaining gate route was lost");

    changed = graph;
    changed.devices.pop_back();
    expect(has(validate(profiles, changed, intent), "UNKNOWN_ROLE_DEVICE"), "Removed harmony source still assigned");
    auto duplicate = profiles;
    duplicate.front().ports.push_back(duplicate.front().ports.front());
    expect(has(validate(duplicate, graph, intent), "INVALID_IDENTIFIER"), "Duplicate port accepted");
}

void logicalCases(const std::vector<DeviceProfile>& profiles, const ConnectionGraph& graph, Intent intent)
{
    expect(validate(profiles, graph, intent).empty(), "Valid branch and merge rejected");
    auto& logical = *intent.eventGraph;
    logical.operations.back().inputs.at("in").merge.reset();
    expect(has(validate(profiles, graph, intent), "EVENT_MERGE_REQUIRED"), "Implicit logical merge accepted");
    logical.operations.back().inputs.at("in") = {Event::notes, Merge::combine};
    expect(has(validate(profiles, graph, intent), "EVENT_TYPE_MISMATCH"), "Event type mismatch accepted");
    expect(has(validate(profiles, graph, intent), "EVENT_OWNERSHIP_UNRESOLVED"), "Competing note owners accepted");

    logical = branchAndMerge();
    logical.operations.back().outputs.emplace("loop", Event::harmony);
    logical.operations.front().inputs.emplace("loop", EventInput{Event::harmony, std::nullopt});
    logical.routes.push_back({"loop", {"merge", "loop"}, {"read", "loop"}});
    expect(has(validate(profiles, graph, intent), "EVENT_CYCLE"), "Logical cycle accepted");
}

void pitchCases(std::vector<DeviceProfile> profiles, ConnectionGraph graph, Intent intent)
{
    const VerifiedPitchIntervention proof{"sl61", "bsp", "pitch-direct"};
    expect(has(plan(profiles, graph, intent, proof).diagnostics, "PITCH_PATH_UNRESOLVED"),
           "A claim passed as a verified route without supporting evidence");

    // Synthetic evidence exercises the verified branch. The checked-in profiles stay user-declared.
    for (auto& profile : profiles)
        for (auto& port : profile.ports)
            port.evidence = Evidence::verified;
    for (auto& edge : graph.connections)
        edge.confirmation = Confirmation::confirmed;
    auto& pitchCapability = profiles.front().capabilities.back();
    expect(pitchCapability.id == "external-per-note-pitch-control", "Pitch capability fixture changed");
    pitchCapability.evidence = Evidence::verified;

    expect(plan(profiles, graph, intent).status == Status::conditional, "Profile evidence replaced local proof");
    expect(plan(profiles, graph, intent, proof).status == Status::feasible, "Verified intervention was rejected");
    graph.connections.front().id = "replacement-pitch";
    expect(plan(profiles, graph, intent, proof).status == Status::conditional, "Evidence transferred to another cable");

    intent.requirements.push_back({"patternSource", "missing-capability", Priority::hard, Evidence::verified});
    expect(plan(profiles, graph, intent).status == Status::infeasible, "Missing hard capability accepted");
}

void standalonePatternCases()
{
    // Synthetic profiles describe the workflow without making claims about any device.
    const DeviceProfile sequencerProfile{
        "test.sequencer-a", "Test", "Hardware sequencer", std::nullopt,
        {{"in", Signal::midi, Direction::input, Evidence::verified}},
        {{"step-sequencing", Evidence::verified, std::nullopt},
         {"midi-pattern-selection", Evidence::verified, std::nullopt}},
    };
    std::vector<DeviceProfile> profiles{
        {"test.midi-interface", "Test", "MIDI interface", std::nullopt,
         {{"out-a", Signal::midi, Direction::output, Evidence::verified},
          {"out-b", Signal::midi, Direction::output, Evidence::verified},
          {"out-c", Signal::midi, Direction::output, Evidence::verified},
          {"clock-in", Signal::midi, Direction::input, Evidence::verified}}, {}},
        sequencerProfile, sequencerProfile, sequencerProfile,
    };
    profiles[1].capabilities.push_back({"internal-clock", Evidence::verified, std::nullopt});
    profiles[1].ports.push_back({"clock-out", Signal::midi, Direction::output, Evidence::verified});
    profiles[2].id = "test.sequencer-b";
    profiles[3].id = "test.sequencer-c";
    const ConnectionGraph graph{
        {{"interface", "test.midi-interface"}, {"sequencer-a", "test.sequencer-a"},
         {"sequencer-b", "test.sequencer-b"}, {"sequencer-c", "test.sequencer-c"}},
        {{"midi-a", {"interface", "out-a"}, {"sequencer-a", "in"}, Signal::midi, Confirmation::confirmed},
         {"midi-b", {"interface", "out-b"}, {"sequencer-b", "in"}, Signal::midi, Confirmation::confirmed},
         {"midi-c", {"interface", "out-c"}, {"sequencer-c", "in"}, Signal::midi, Confirmation::confirmed},
         {"hardware-clock", {"sequencer-a", "clock-out"}, {"interface", "clock-in"},
          Signal::midi, Confirmation::confirmed}},
    };
    const Intent intent{
        .id = "standalone-patterns",
        .name = "Standalone pattern orchestration",
        .roles = {{"clockSource", "sequencer-a"}, {"patternsA", "sequencer-a"},
                  {"patternsB", "sequencer-b"}, {"patternsC", "sequencer-c"}},
        .goals = {"Cue a different pattern on each device at a shared musical boundary"},
        .constraints = {},
        .requirements = {
            {"clockSource", "internal-clock", Priority::hard, Evidence::verified},
            {"patternsA", "step-sequencing", Priority::hard, Evidence::verified},
            {"patternsB", "step-sequencing", Priority::hard, Evidence::verified},
            {"patternsC", "step-sequencing", Priority::hard, Evidence::verified},
            {"patternsA", "midi-pattern-selection", Priority::hard, Evidence::verified},
            {"patternsB", "midi-pattern-selection", Priority::hard, Evidence::verified},
            {"patternsC", "midi-pattern-selection", Priority::hard, Evidence::verified},
        },
        .eventGraph = EventGraph{{
            {"track-clock", {}, {{"clock", Event::clock}}},
            {"queue-patterns", {{"clock", {Event::clock, std::nullopt}}},
             {{"select-a", Event::control}, {"select-b", Event::control}, {"select-c", Event::control}}},
            {"select-a", {{"select", {Event::control, std::nullopt}}}, {}},
            {"select-b", {{"select", {Event::control, std::nullopt}}}, {}},
            {"select-c", {{"select", {Event::control, std::nullopt}}}, {}},
        }, {
            {"clock-in", {"track-clock", "clock"}, {"queue-patterns", "clock"}},
            {"change-a", {"queue-patterns", "select-a"}, {"select-a", "select"}},
            {"change-b", {"queue-patterns", "select-b"}, {"select-b", "select"}},
            {"change-c", {"queue-patterns", "select-c"}, {"select-c", "select"}},
        }},
    };

    const auto standalone = plan(profiles, graph, intent);
    expect(standalone.status == Status::feasible, "Standalone patterns required a DAW");
    expect(standalone.assignments == intent.roles, "Hardware lost sequencing or clock responsibility");
    expect(standalone.eventGraph && standalone.eventGraph->routes.size() == 4,
           "The plan lost a clock or pattern control route");

    auto& thirdTarget = profiles.back();
    thirdTarget.capabilities.back().evidence = Evidence::userDeclared;
    const auto unverified = plan(profiles, graph, intent);
    expect(unverified.status == Status::conditional &&
           unverified.diagnostics.size() == 1 &&
           unverified.diagnostics.front().code == "CAPABILITY_EVIDENCE_INSUFFICIENT" &&
           unverified.diagnostics.front().message == "patternsC: midi-pattern-selection",
           "Unverified pattern selection did not identify the affected target");

    thirdTarget.capabilities.pop_back();
    const auto missing = plan(profiles, graph, intent);
    expect(missing.status == Status::infeasible && has(missing.diagnostics, "CAPABILITY_MISSING"),
           "The plan accepted a target with no pattern selection");
}

} // namespace

int main(int argc, char** argv)
{
    try {
        expect(argc == 3, "Expected fixture and output directories");
        const fs::path fixtures = argv[1];
        const fs::path output = argv[2];
        std::vector<DeviceProfile> profiles;
        for (const auto* name : {"bsp-2600", "blue-marvin", "sl61"})
            profiles.push_back(roundTrip(fixtures / (std::string(name) + "-device-profile.json"), readDeviceProfile));
        auto graph = roundTrip(fixtures / "bsp-2600-connections.json", readConnections);
        auto intent = roundTrip(fixtures / "adaptive-bass-intent.json", readIntent);
        const auto expected = roundTrip(fixtures / "adaptive-bass-expected-plan.json", readExecutionPlan);
        const auto native = plan(profiles, graph, intent);
        expect(native.status == expected.status, "Reference feasibility status differs");
        for (const auto& diagnostic : expected.diagnostics)
            expect(has(native.diagnostics, diagnostic.code), "Reference diagnostic missing");
        expect(native.retainedConnections == std::vector<std::string>{"gate-direct", "pitch-direct"},
               "Direct pitch and gate were not retained");

        physicalCases(profiles, graph, intent);
        pitchCases(profiles, graph, intent);
        standalonePatternCases();
        intent.eventGraph = branchAndMerge();
        logicalCases(profiles, graph, intent);
        intent.requirements.push_back({"patternSource", "step-sequencing", Priority::hard, Evidence::verified});
        const auto intended = writeJson(intent);
        expect(writeJson(readIntent(intended)) == intended, "Typed intent round trip failed");
        const auto nativePlan = plan(profiles, graph, intent);
        expect(has(nativePlan.diagnostics, "CAPABILITY_EVIDENCE_INSUFFICIENT"), "Declared evidence passed as verified");
        const auto planned = writeJson(nativePlan);
        expect(writeJson(readExecutionPlan(planned)) == planned, "Generated plan round trip failed");
        std::ranges::reverse(graph.connections);
        std::ranges::reverse(profiles);
        std::ranges::reverse(intent.eventGraph->operations);
        std::ranges::reverse(intent.eventGraph->routes);
        expect(writeJson(plan(profiles, graph, intent)) == planned, "Plan depends on inventory input order");

        auto unsupported = intended;
        unsupported.replace(unsupported.find("1.0"), 3, "2.0");
        rejectsIntent(unsupported);
        auto extraMember = intended;
        extraMember.insert(1, "\"untrustedCommand\":\"execute\",");
        rejectsIntent(extraMember);
        auto ambiguous = intended;
        ambiguous.insert(1, "\"schemaVersion\":\"1.0\",");
        rejectsIntent(ambiguous);
        rejectsIntent("{}");

        fs::create_directories(output);
        std::ofstream intendedFile(output / "intent.json");
        std::ofstream plannedFile(output / "execution-plan.json");
        intendedFile << intended;
        plannedFile << planned;
        expect(intendedFile.good() && plannedFile.good(), "Cannot write schema conformance outputs");
        std::cout << "Native fixtures, validation, planning and JSON cases passed\n";
        return 0;
    } catch (const std::exception& error) {
        std::cerr << error.what() << '\n';
        return 1;
    }
}
