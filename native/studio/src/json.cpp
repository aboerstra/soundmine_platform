#include <soundmine/studio/workflow.hpp>

#include <nlohmann/json.hpp>

#include <algorithm>
#include <initializer_list>
#include <set>
#include <stdexcept>
#include <type_traits>
#include <utility>

namespace soundmine::studio {
namespace {

using Json = nlohmann::json;
using namespace std::string_view_literals;

template <typename E>
using EnumEntry = std::pair<E, std::string_view>;

constexpr EnumEntry<Evidence> evidenceValues[] = {
    {Evidence::verified, "verified"}, {Evidence::manufacturerDocumented, "manufacturer-documented"},
    {Evidence::userDeclared, "user-declared"}, {Evidence::unverified, "unverified"},
};
constexpr EnumEntry<Signal> signalValues[] = {
    {Signal::midi, "midi"}, {Signal::usbMidi, "usb-midi"}, {Signal::pitchCv, "pitch-cv"},
    {Signal::gate, "gate"}, {Signal::velocityCv, "velocity-cv"}, {Signal::clock, "clock"},
    {Signal::audio, "audio"},
};
constexpr EnumEntry<Direction> directionValues[] = {
    {Direction::input, "input"}, {Direction::output, "output"}, {Direction::bidirectional, "bidirectional"},
};
constexpr EnumEntry<Confirmation> confirmationValues[] = {
    {Confirmation::confirmed, "confirmed"}, {Confirmation::declared, "declared"},
    {Confirmation::unverified, "unverified"},
};
constexpr EnumEntry<Event> eventValues[] = {
    {Event::notes, "notes"}, {Event::harmony, "harmony"}, {Event::clock, "clock"}, {Event::control, "control"},
};
constexpr EnumEntry<Merge> mergeValues[] = {{Merge::priority, "priority"}, {Merge::combine, "combine"}};
constexpr EnumEntry<Priority> priorityValues[] = {{Priority::hard, "hard"}, {Priority::soft, "soft"}};
constexpr EnumEntry<Status> statusValues[] = {
    {Status::feasible, "feasible"}, {Status::conditional, "conditional"}, {Status::infeasible, "infeasible"},
};
constexpr EnumEntry<Severity> severityValues[] = {
    {Severity::info, "info"}, {Severity::warning, "warning"}, {Severity::error, "error"},
};

constexpr auto& values(Evidence) { return evidenceValues; }
constexpr auto& values(Signal) { return signalValues; }
constexpr auto& values(Direction) { return directionValues; }
constexpr auto& values(Confirmation) { return confirmationValues; }
constexpr auto& values(Event) { return eventValues; }
constexpr auto& values(Merge) { return mergeValues; }
constexpr auto& values(Priority) { return priorityValues; }
constexpr auto& values(Status) { return statusValues; }
constexpr auto& values(Severity) { return severityValues; }

[[noreturn]] void invalid(std::string_view message)
{
    throw std::invalid_argument(std::string(message));
}

template <class T> T decode(const Json& json);
template <class T> Json encode(const T& value);

class Reader {
public:
    explicit Reader(const Json& json) : json_(json)
    {
        if (!json.is_object())
            invalid("Expected a JSON object");
    }

    template <class T>
    void operator()(std::string_view key, T& value)
    {
        if (!optional(key, value))
            invalid("Missing JSON member: " + std::string(key));
    }

    template <class T>
    void operator()(std::string_view key, std::optional<T>& value)
    {
        T parsed;
        if (optional(key, parsed))
            value = std::move(parsed);
    }

    template <class T>
    bool optional(std::string_view key, T& value)
    {
        const auto item = json_.find(key);
        if (item == json_.end())
            return false;
        try {
            value = decode<T>(*item);
        } catch (const std::invalid_argument& e) {
            invalid(std::string(key) + ": " + e.what());
        }
        ++consumed_;
        return true;
    }

    void finish() const
    {
        if (json_.size() != consumed_)
            invalid("Unknown JSON member");
    }

private:
    const Json& json_;
    std::size_t consumed_{};
};

struct Writer {
    Json json = Json::object();

    template <class T>
    void operator()(std::string_view key, const T& value) { json[key] = encode(value); }

    template <class T>
    void operator()(std::string_view key, const std::optional<T>& value)
    {
        if (value)
            (*this)(key, *value);
    }

    template <class T>
    void optional(std::string_view key, const T& value)
    {
        if (!value.empty())
            (*this)(key, value);
    }
};

template <class F, class T> void fields(F& f, T& v)
{
    using Model = std::remove_const_t<T>;
    if constexpr (std::is_same_v<Model, Port>) {
        f("id", v.id);
        f("signal", v.signal);
        f("direction", v.direction);
        f("evidence", v.evidence);
    } else if constexpr (std::is_same_v<Model, Capability>) {
        f("id", v.id);
        f("evidence", v.evidence);
        f("notes", v.notes);
    } else if constexpr (std::is_same_v<Model, DeviceProfile>) {
        f("id", v.id);
        f("manufacturer", v.manufacturer);
        f("model", v.model);
        f("firmware", v.firmware);
        f("ports", v.ports);
        f("capabilities", v.capabilities);
    } else if constexpr (std::is_same_v<Model, DeviceInstance>) {
        f("id", v.id);
        f("profileId", v.profileId);
    } else if constexpr (std::is_same_v<Model, Endpoint>) {
        f("deviceId", v.deviceId);
        f("portId", v.portId);
    } else if constexpr (std::is_same_v<Model, Connection>) {
        f("id", v.id);
        f("from", v.from);
        f("to", v.to);
        f("signal", v.signal);
        f("confirmation", v.confirmation);
    } else if constexpr (std::is_same_v<Model, ConnectionGraph>) {
        f("devices", v.devices);
        f("connections", v.connections);
    } else if constexpr (std::is_same_v<Model, EventInput>) {
        f("event", v.event);
        f("merge", v.merge);
    } else if constexpr (std::is_same_v<Model, Operation>) {
        f("id", v.id);
        f("inputs", v.inputs);
        f("outputs", v.outputs);
    } else if constexpr (std::is_same_v<Model, EventEndpoint>) {
        f("operationId", v.operationId);
        f("portId", v.portId);
    } else if constexpr (std::is_same_v<Model, EventRoute>) {
        f("id", v.id);
        f("from", v.from);
        f("to", v.to);
    } else if constexpr (std::is_same_v<Model, EventGraph>) {
        f("operations", v.operations);
        f("routes", v.routes);
    } else if constexpr (std::is_same_v<Model, Requirement>) {
        f("role", v.role);
        f("capabilityId", v.capabilityId);
        f("priority", v.priority);
        f("minimumEvidence", v.minimumEvidence);
    } else if constexpr (std::is_same_v<Model, Constraints>) {
        f("preserveDirectCvGate", v.preserveDirectCvGate);
        f("preserveHardwareVelocityCv", v.preserveHardwareVelocityCv);
        f("allowAdditionalHardware", v.allowAdditionalHardware);
        f("requirePerNoteHarmonicAdaptation", v.requirePerNoteHarmonicAdaptation);
    } else if constexpr (std::is_same_v<Model, Intent>) {
        f("id", v.id);
        f("name", v.name);
        f("roles", v.roles);
        f("goals", v.goals);
        f("constraints", v.constraints);
        f.optional("requirements", v.requirements);
        f("eventGraph", v.eventGraph);
    } else if constexpr (std::is_same_v<Model, Diagnostic>) {
        f("code", v.code);
        f("severity", v.severity);
        f("message", v.message);
    } else if constexpr (std::is_same_v<Model, ExecutionPlan>) {
        f("intentId", v.intentId);
        f("status", v.status);
        f("diagnostics", v.diagnostics);
        f.optional("assignments", v.assignments);
        f.optional("retainedConnections", v.retainedConnections);
        f("eventGraph", v.eventGraph);
    }
}

template <class T> struct IsVector : std::false_type {};
template <class T> struct IsVector<std::vector<T>> : std::true_type {};
template <class T> struct IsMap : std::false_type {};
template <class T> struct IsMap<std::map<std::string, T>> : std::true_type {};

template <class T>
T decode(const Json& json)
{
    if constexpr (std::is_same_v<T, std::string>) {
        if (!json.is_string())
            invalid("Expected a string");
        return json.get<std::string>();
    } else if constexpr (std::is_same_v<T, bool>) {
        if (!json.is_boolean())
            invalid("Expected a boolean");
        return json.get<bool>();
    } else if constexpr (std::is_enum_v<T>) {
        if (json.is_string())
            for (const auto& [value, name] : values(T{}))
                if (json == name)
                    return value;
        invalid("Unknown enumerated value");
    } else if constexpr (IsVector<T>::value) {
        if (!json.is_array())
            invalid("Expected an array");
        T result;
        result.reserve(json.size());
        for (const auto& item : json)
            result.push_back(decode<typename T::value_type>(item));
        return result;
    } else if constexpr (IsMap<T>::value) {
        if (!json.is_object())
            invalid("Expected an object");
        T result;
        for (const auto& [key, item] : json.items())
            result.emplace(key, decode<typename T::mapped_type>(item));
        return result;
    } else {
        T result;
        Reader reader(json);
        fields(reader, result);
        reader.finish();
        return result;
    }
}

template <class T>
Json encode(const T& value)
{
    if constexpr (std::is_same_v<T, std::string> || std::is_same_v<T, bool>) {
        return value;
    } else if constexpr (std::is_enum_v<T>) {
        for (const auto& [candidate, name] : values(T{}))
            if (value == candidate)
                return name;
        invalid("Unknown enumerated value");
    } else if constexpr (IsVector<T>::value) {
        Json result = Json::array();
        for (const auto& item : value)
            result.push_back(encode(item));
        return result;
    } else if constexpr (IsMap<T>::value) {
        Json result = Json::object();
        for (const auto& [key, item] : value)
            result[key] = encode(item);
        return result;
    } else {
        Writer writer;
        fields(writer, value);
        return writer.json;
    }
}

void nonEmpty(std::initializer_list<std::string_view> values)
{
    if (std::ranges::find(values, ""sv) != values.end())
        invalid("Required string cannot be empty");
}

void checkShape(const DeviceProfile& profile)
{
    nonEmpty({profile.manufacturer, profile.model});
    constexpr auto alphanumeric = "abcdefghijklmnopqrstuvwxyz0123456789"sv;
    constexpr auto profileIdCharacters = "abcdefghijklmnopqrstuvwxyz0123456789._-"sv;
    if (profile.id.size() < 2 || alphanumeric.find(profile.id.front()) == std::string_view::npos ||
        profile.id.find_first_not_of(profileIdCharacters) != std::string::npos)
        invalid("Invalid device profile identifier");
    for (const auto& port : profile.ports)
        nonEmpty({port.id});
    for (const auto& capability : profile.capabilities)
        nonEmpty({capability.id});
}

void checkShape(const ConnectionGraph& graph)
{
    if (graph.devices.empty())
        invalid("Connections require at least one device");
    for (const auto& device : graph.devices)
        nonEmpty({device.id, device.profileId});
    for (const auto& edge : graph.connections)
        nonEmpty({edge.id, edge.from.deviceId, edge.from.portId, edge.to.deviceId, edge.to.portId});
}

void checkShape(const EventGraph& graph)
{
    for (const auto& operation : graph.operations) {
        nonEmpty({operation.id});
        if (operation.inputs.contains("") || operation.outputs.contains(""))
            invalid("Event port identifier cannot be empty");
    }
    for (const auto& route : graph.routes)
        nonEmpty({route.id, route.from.operationId, route.from.portId, route.to.operationId, route.to.portId});
}

void checkShape(const Intent& intent)
{
    nonEmpty({intent.id, intent.name});
    if (intent.roles.empty() || intent.goals.empty())
        invalid("Intent requires roles and goals");
    for (const auto& [role, device] : intent.roles)
        nonEmpty({role, device});
    for (const auto& goal : intent.goals)
        nonEmpty({goal});
    for (const auto& requirement : intent.requirements)
        nonEmpty({requirement.role, requirement.capabilityId});
    if (intent.eventGraph)
        checkShape(*intent.eventGraph);
}

void checkShape(const ExecutionPlan& plan)
{
    nonEmpty({plan.intentId});
    if (plan.eventGraph)
        checkShape(*plan.eventGraph);
    for (const auto& diagnostic : plan.diagnostics)
        nonEmpty({diagnostic.code, diagnostic.message});
    for (const auto& [role, device] : plan.assignments)
        nonEmpty({role, device});
    std::set<std::string_view> retained;
    for (const auto& id : plan.retainedConnections) {
        nonEmpty({id});
        if (!retained.insert(id).second)
            invalid("Retained connection repeated");
    }
}

template <class T>
T readDocument(std::string_view input, std::string_view expectedKind)
{
    std::vector<std::set<std::string>> objectKeys;
    const auto inspect = [&](int depth, Json::parse_event_t event, Json& value) {
        using Parse = Json::parse_event_t;
        if (depth > 64)
            invalid("Studio JSON nesting limit exceeded");
        if (event == Parse::object_start)
            objectKeys.emplace_back();
        if (event == Parse::key && !objectKeys.back().insert(value.get<std::string>()).second)
            invalid("Duplicate JSON member");
        if (event == Parse::object_end)
            objectKeys.pop_back();
        return true;
    };
    try {
        auto json = Json::parse(input.begin(), input.end(), inspect);
        if (!json.is_object() || json.value("schemaVersion", Json{}) != "1.0" ||
            json.value("kind", Json{}) != expectedKind)
            invalid("Unsupported Studio JSON kind or version");
        json.erase("schemaVersion");
        json.erase("kind");
        auto model = decode<T>(json);
        checkShape(model);
        return model;
    } catch (const Json::exception& error) {
        invalid(error.what());
    }
}

template <class T>
std::string writeDocument(const T& model, std::string_view kind)
{
    checkShape(model);
    auto json = encode(model);
    json["schemaVersion"] = "1.0";
    json["kind"] = kind;
    return json.dump(2) + '\n';
}

} // namespace

DeviceProfile readDeviceProfile(std::string_view json)
{
    return readDocument<DeviceProfile>(json, "studio.device-profile");
}
ConnectionGraph readConnections(std::string_view json)
{
    return readDocument<ConnectionGraph>(json, "studio.connections");
}
Intent readIntent(std::string_view json)
{
    return readDocument<Intent>(json, "workflow.intent");
}
ExecutionPlan readExecutionPlan(std::string_view json)
{
    return readDocument<ExecutionPlan>(json, "workflow.execution-plan");
}
std::string writeJson(const DeviceProfile& model) { return writeDocument(model, "studio.device-profile"); }
std::string writeJson(const ConnectionGraph& model) { return writeDocument(model, "studio.connections"); }
std::string writeJson(const Intent& model) { return writeDocument(model, "workflow.intent"); }
std::string writeJson(const ExecutionPlan& model) { return writeDocument(model, "workflow.execution-plan"); }

} // namespace soundmine::studio
