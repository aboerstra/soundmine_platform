#pragma once

#include <compare>
#include <map>
#include <optional>
#include <string>
#include <string_view>
#include <vector>

namespace soundmine::studio {

enum class Evidence { verified, manufacturerDocumented, userDeclared, unverified };
enum class Signal { midi, usbMidi, pitchCv, gate, velocityCv, clock, audio };
enum class Direction { input, output, bidirectional };
enum class Confirmation { confirmed, declared, unverified };
enum class Event { notes, harmony, clock, control };
enum class Merge { priority, combine };
enum class Priority { hard, soft };
enum class Status { feasible, conditional, infeasible };
enum class Severity { info, warning, error };

struct Port {
    std::string id;
    Signal signal{};
    Direction direction{};
    Evidence evidence{Evidence::unverified};
};

struct Capability {
    std::string id;
    Evidence evidence{Evidence::unverified};
    std::optional<std::string> notes;
};

struct DeviceProfile {
    std::string id, manufacturer, model;
    std::optional<std::string> firmware;
    std::vector<Port> ports;
    std::vector<Capability> capabilities;
};

struct DeviceInstance {
    std::string id, profileId;
};

struct Endpoint {
    std::string deviceId, portId;
    auto operator<=>(const Endpoint&) const = default;
};

struct Connection {
    std::string id;
    Endpoint from, to;
    Signal signal{};
    Confirmation confirmation{Confirmation::unverified};
};

struct ConnectionGraph {
    std::vector<DeviceInstance> devices;
    std::vector<Connection> connections;
};

struct EventInput {
    Event event{};
    std::optional<Merge> merge;
};

struct Operation {
    std::string id;
    std::map<std::string, EventInput> inputs;
    std::map<std::string, Event> outputs;
};

struct EventEndpoint {
    std::string operationId, portId;
    auto operator<=>(const EventEndpoint&) const = default;
};

struct EventRoute {
    std::string id;
    EventEndpoint from, to;
};

struct EventGraph {
    std::vector<Operation> operations;
    std::vector<EventRoute> routes;
};

struct Requirement {
    std::string role, capabilityId;
    Priority priority{Priority::hard};
    Evidence minimumEvidence{Evidence::verified};
};

struct Constraints {
    std::optional<bool> preserveDirectCvGate, preserveHardwareVelocityCv,
        allowAdditionalHardware, requirePerNoteHarmonicAdaptation;
};

struct Intent {
    std::string id, name;
    std::map<std::string, std::string> roles;
    std::vector<std::string> goals;
    Constraints constraints;
    std::vector<Requirement> requirements;
    std::optional<EventGraph> eventGraph;
};

struct Diagnostic {
    std::string code;
    Severity severity{};
    std::string message;
};

struct ExecutionPlan {
    std::string intentId;
    Status status{Status::infeasible};
    std::vector<Diagnostic> diagnostics;
    std::map<std::string, std::string> assignments;
    std::vector<std::string> retainedConnections;
    std::optional<EventGraph> eventGraph;
};

// Supplied by the local verifier after testing the currently attached hardware.
// Imported Studio JSON cannot supply this evidence. A reconnection requires fresh evidence.
struct VerifiedPitchIntervention {
    std::string harmonyDeviceId;
    std::string sequencerDeviceId;
    std::string pitchConnectionId;
};

std::vector<Diagnostic> validate(const std::vector<DeviceProfile>& profiles,
                                 const ConnectionGraph& connections, const Intent& intent);
ExecutionPlan plan(const std::vector<DeviceProfile>& profiles, const ConnectionGraph& connections,
                   const Intent& intent,
                   std::optional<VerifiedPitchIntervention> pitchIntervention = std::nullopt);

// Readers reject malformed or unsupported interchange documents with std::invalid_argument.
DeviceProfile readDeviceProfile(std::string_view json);
ConnectionGraph readConnections(std::string_view json);
Intent readIntent(std::string_view json);
ExecutionPlan readExecutionPlan(std::string_view json);
std::string writeJson(const DeviceProfile& profile);
std::string writeJson(const ConnectionGraph& connections);
std::string writeJson(const Intent& intent);
std::string writeJson(const ExecutionPlan& plan);

} // namespace soundmine::studio
