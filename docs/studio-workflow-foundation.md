# Soundmine Studio and Workflow Foundation — Architecture Contract v0.1

Status: draft Studio contracts and a portable native workflow core. The Studio persistence and local hardware integration described below are future work.

## Product principle

Hardware-biased means the distribution of musical work changes as the connected equipment and physical connections change. Preserve native hardware sequencing, gates, articulation, clock and synthesis wherever practical. Software supplies missing capabilities, not automatic replacement of hardware signal paths.

A workflow is a musical goal and its constraints; an execution plan is one validated realization of that goal using actual devices and connections. Never confuse a proposed plan with a verified physical patch.

## Ownership boundaries

- **Platform cloud (existing Laravel modular monolith):** user-owned studio inventories, verified and user-declared device capability profiles, saved workflow intents, versioned schemas, hosted MCP endpoints in a later milestone, and authenticated synchronization. Do not introduce MCP endpoints before authorization and privacy contracts are defined.
- **Platform native shared library (C++):** deterministic schema parsing, typed graph validation, capability matching, plan diagnostics, and non-real-time configuration compilation. Keep this library independent of Laravel and product-specific musical rules.
- **Harmonic Conductor:** chord detection, pitch adaptation, voice leading, and product UX.
- **Rhythm Conductor:** Rytm pattern orchestration and Program Change scheduling. The Max for Live reference defines existing behavior. Pattern orchestration is also a standalone product use case.

A hosted service cannot discover physical CV cables or control local MIDI ports without an explicitly authorized local bridge. Real-time MIDI/CV performance must not depend on cloud availability.

## Canonical concepts

**DeviceProfile**: manufacturer/model/optional firmware, capabilities, ports, and provenance of each claim (verified, manufacturer-documented, user-declared, or unverified). Never infer a physical connection from a MIDI device's presence.

**DeviceInstance**: a user's actual piece of equipment, with stable local identifier and assigned profile. No secret, serial number, or machine fingerprint required in shared workflow exports.

**PhysicalConnection**: source and destination ports, signal kind, direction, electrical/protocol compatibility, and confirmation status. Signal kinds include midi, usb-midi, pitch-cv, gate, velocity-cv, clock, and audio. A logical harmony relationship is NOT a physical connection.

**WorkflowIntent**: musical objective, device roles, requested transformations, explicit constraints, and user preferences. May remain unresolved.

**ExecutionPlan**: a compiled graph of operations and actual paths, with status `feasible`, `conditional`, or `infeasible`, evidence, and diagnostics. Only local verification and user approval can activate hardware-affecting changes.

**CapabilityRequirement**: a typed requirement with hard/soft priority and an evidence threshold.

## Typed graph rules

1. Physical edges connect compatible declared port types; logical edges connect musical events or control/context, not voltages.
2. A branch may feed multiple destinations; fan-in must define arbitration/merge semantics.
3. Detect illegal cycles, ambiguous ownership of note-on/note-off, incompatible clocks, and unresolved pitch paths.
4. A device can be assigned multiple roles only when its capabilities and operating modes permit them simultaneously.
5. Unsupported, unverified, or firmware-dependent features produce explicit diagnostics, never silent success.
6. Reconfiguration must be transactional: preview diff, validate, obtain confirmation, then activate. Restore last known good plan on failure.
7. Plans should be reproducible and versioned; do not execute instructions embedded in imported JSON.

## First acceptance fixture: SL61 MkIII + BeatStep Pro + 2600

Known desired physical path: BeatStep Pro pitch CV -> Behringer 2600 pitch input; BeatStep Pro gate -> 2600 gate input; velocity CV routing is optional and must be explicitly confirmed.

Roles: SL61 MkIII supplies chord input; BeatStep Pro owns step sequencing; 2600 owns analog synthesis; Ableton owns arrangement, recording, and transport when present.

Expected validation outcomes:
- Direct BSP-to-2600 CV/Gate sequencing is representable without pretending JUCE processes that analog path.
- Harmony input from SL61 is representable independently of the pitch/gate connections.
- A requested per-note chord-aware pitch transformation is **conditional/unresolved** until a verified controllable pitch path is present.
- Root transposition via external BSP commands remains unverified until bench-tested and must not be advertised as working.
- The system can show alternative execution plans without silently substituting MIDI-to-CV for the user's direct hardware gate path.

## Standalone pattern orchestration

A musician can use Rhythm Conductor to cue a sequence of patterns in a studio with no DAW. A local Rhythm Conductor runtime manages the order and timing of changes over available MIDI connections. Hardware can keep its own patterns, sequencing, sound generation, and clock. Ableton or another DAW can participate when present.

A standalone workflow assigns exactly one clock source for synchronized pattern changes. It requires a verified pattern-selection mechanism and a usable MIDI route for each controlled device. Timing-sensitive changes require checking device-specific switching behavior, including when a command must arrive for a change at a pattern boundary. Missing or unverified control capabilities should appear in the plan diagnostics.

The native tests cover a DAW-free plan and the effect of unverified pattern-selection capability using synthetic profiles. Local MIDI scheduling and device timing verification belong to the product runtime.

## JSON contract strategy

Use published JSON Schema with explicit `schemaVersion`, `kind`, stable IDs, explicit units for CV ranges, and strict validation. Separate documents for `studio.inventory`, `studio.connections`, `workflow.intent`, and `workflow.execution-plan`. Version migrations are explicit. Human-readable JSON is the canonical interchange; a future `.soundmine` package may wrap it.

Cloud and native validators must share conformance fixtures. A cloud proposal must be revalidated locally against the current hardware state before activation.

## UX contract

Three synchronized presentations of one workflow: **Play** (immediate musical performance), **Shape** (musical behavior), **Connect** (complete graph and hardware patching). The user can see why a workflow is possible, what is missing, what is unverified, and what will change. No invisible automatic rewiring of physical hardware.

## AI / MCP contract (future milestone)

LLMs may retrieve authorized studio context, propose workflow intents, compare plans, and save drafts. MCP tools must be narrowly scoped and authenticated; proposal creation is distinct from hardware activation. No arbitrary code or executable scripts in imported workflow packages. Exported studio context excludes credentials and unnecessary identifiers.

## Implementation sequence

1. Define JSON Schemas and conformance fixtures (valid, conditional, invalid).
2. Implement deterministic graph/capability validation in a portable native library with tests.
3. Add additive Laravel Studio/Devices/Workflows domains and user ownership policies.
4. Build local hardware discovery and user-confirmed connection editing.
5. Integrate the first Harmonic Conductor workflow; verify real MIDI/CV behavior on the bench.
6. Add hosted MCP and AI skills against the same validated contracts.

## Non-goals for the foundation milestone

No cloud-hosted real-time sequencing, no claims of CV cable auto-detection, no unverified BeatStep Pro firmware capabilities, no changes to existing license grants, and no assumption that Ableton VST3 MIDI routing works without host-specific validation.


## Foundation review

The draft 1.0 documents describe typed cables and provenance labels. They lack electrical ranges, connector standards, firmware-scoped evidence, and locally verified control paths. Port signal and direction checks establish graph compatibility. Bench confirmation establishes electrical and operational compatibility.

The original intent contract leaves goals as human-readable text. The original plan contract reports diagnostics without identifying assigned devices, retained cables, or logical event routes. Role assignments and cable endpoints must resolve against the supplied inventory. Logical fan-in requires an explicit merge policy, and a physical input accepts one source in the current contract. A physical mixer must be represented as a device with separate inputs.


## Portable workflow core

Build and test with a C++20 toolchain and CMake 3.24 or later:

```sh
cmake -S native/studio -B /tmp/soundmine-studio-build
cmake --build /tmp/soundmine-studio-build --config Release
ctest --test-dir /tmp/soundmine-studio-build -C Release --output-on-failure
python scripts/validate_studio_contracts.py --native-output /tmp/soundmine-studio-build/conformance
```

Consumers can link `Soundmine::Studio` and include `soundmine/studio/workflow.hpp`. CMake uses an installed nlohmann/json 3.12 package when available and otherwise fetches a hash-pinned release. An offline build can provide the extracted release using `FETCHCONTENT_SOURCE_DIR_NLOHMANN_JSON`. The library itself performs parsing and planning in memory with no network or hardware access.

The core checks endpoint references, signal and event types, port direction, physical input conflicts, explicit event merge policies, logical cycles and note/clock fan-in. Physical cycles require port and device behavior context and are outside this check. Consider CV range, trigger polarity, clock standard, role co-existence and electrical limits during hardware verification.

The planner evaluates the assigned studio and records assignments, proposed event routes, retained cable IDs, and stable diagnostic codes. A required capability missing from the assigned profile is infeasible. An existing claim below the requested evidence threshold is conditional. Soft requirements report advisory diagnostics. Verified evidence meets any threshold. Manufacturer documentation and user declarations satisfy their respective thresholds. An unverified threshold accepts any declared claim. The planner does not allocate unlisted equipment or apply configuration to a device.

The reference plan retains the two declared BeatStep Pro to Blue Marvin cables and reports `PITCH_PATH_UNRESOLVED`. A feasible per-note adaptation on this route requires a confirmed active pitch cable, verified sequencer capability, and a separate local verification tied to the harmony source, sequencer and cable. The caller must discard that verification when the routing or connection session changes. Imported JSON has no field for attaching this authorization or making hardware changes. Native event routes are declarative. A product-specific runtime supplies event processing and merge execution.

The original PostgreSQL suite passed with 94 tests and 609 assertions. CI runs both fixture validators and builds and tests the native library on Linux, macOS and Windows. The native suite passed locally on Linux and round-trips the original fixtures. The Python conformance check also accepted the generated intent and plan.
