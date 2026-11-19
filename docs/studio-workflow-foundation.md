# Soundmine Studio and Workflow Foundation — Architecture Contract v0.1

Status: draft Studio contracts and a portable native workflow core. The Studio persistence and local hardware integration described below are future work.

## Product principle

Hardware-biased means the distribution of musical work changes as the connected equipment and physical connections change. Preserve native hardware sequencing, gates, articulation, clock and synthesis wherever practical. Software supplies missing capabilities, not automatic replacement of hardware signal paths.

A workflow is a musical goal and its constraints; an execution plan is one validated realization of that goal using actual devices and connections. Never confuse a proposed plan with a verified physical patch.

## Ownership boundaries

- **Platform cloud (existing Laravel modular monolith):** user-owned studio inventories, verified and user-declared device capability profiles, saved workflow intents, versioned schemas, hosted MCP endpoints in a later milestone, and authenticated synchronization. Do not introduce MCP endpoints before authorization and privacy contracts are defined.
- **Platform native shared library (C++):** deterministic schema parsing, typed graph validation, capability matching, plan diagnostics, and non-real-time configuration compilation. Keep this library independent of Laravel and product-specific musical rules.
- **Harmonic Conductor:** chord detection, pitch adaptation, voice leading, and product UX.
- **Rhythm Conductor:** Rytm pattern orchestration and Program Change scheduling. The JUCE plug-in is the Ableton Live product target. The existing Max for Live device is a behavior reference. Pattern orchestration is also a standalone product use case.

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

## Pattern orchestration in Ableton and standalone

Rhythm Conductor has two ways to run:

- **Inside Ableton Live.** A JUCE plug-in hosted in the Live set follows Live's transport and schedules pattern changes. This mode runs entirely within Live. The plug-in's route to the hardware must be verified in Live before considering the workflow feasible.
- **Standalone.** The desktop app runs the workflow in a studio without a DAW. It connects to the studio's MIDI devices, follows the selected clock, and schedules each pattern change. An optional mobile companion can request a scene change and display what the desktop has queued or activated. The desktop determines the launch boundary and continues managing playback if the mobile connection drops.

In either mode, hardware can keep its own patterns, sequencing, and sound generation.

A scene identifies a pattern target separately for each device. One scene could cue Analog Rytm pattern B1, BeatStep Pro pattern A4, and a separately selected SL61 MkIII pattern, with all three intended to start at the next bar. Pattern identifiers belong to each device. Hardware continues to play and repeat its own patterns between scene changes.

A standalone workflow assigns one clock source for synchronized pattern changes. It requires a verified pattern-selection mechanism and a usable MIDI route for each controlled device. Different devices may need commands at different times to switch at the same musical boundary. Exact pattern addressing, external switching support, and cue timing need verification for the selected firmware and operating mode. Each target with missing or unverified control should appear in the plan diagnostics.

The native tests cover DAW-free routing to multiple sequencers and check pattern-selection capability per target using synthetic profiles. Scene data, local MIDI scheduling, and device timing verification belong to subsequent product work.

### JUCE VST3 bench prototype

The first prototype lives in `native/rhythm-conductor`. It reads the host playhead, accepts a single queued MIDI Program Change, and schedules it with a configurable lead before a bar boundary. It requires a playing host with valid position, bar start, tempo and meter. A request with insufficient lead moves to the following bar. A seek, stop, meter change, or loss of transport cancels a pending cue. The prototype disables cues while looping or rendering offline. It uses no Max components.

Build the unsigned development plug-in with CMake 3.24 or later, a C++20 compiler, and the platform's JUCE prerequisites. CMake uses JUCE 8.0.12 or fetches the hash-pinned source at configure time.

```sh
cmake -S native/rhythm-conductor -B /tmp/rhythm-build -DCMAKE_BUILD_TYPE=Release
cmake --build /tmp/rhythm-build --config Release
ctest --test-dir /tmp/rhythm-build -C Release --output-on-failure
```

The bundle is under `RhythmConductor_artefacts/Release/VST3` in the build directory. Set `-DSOUNDMINE_BUILD_VST3=OFF` on a separate configure to run the scheduler tests without JUCE. The Rhythm Conductor GitHub Actions workflow builds development bundles for macOS and Windows.

1. Load Rhythm Conductor on a Live MIDI track and configure a route from its MIDI output to the Analog Rytm. The instrument has a silent audio output bus. Check the MIDI route with a monitor on the hardware output.
2. Put the Rytm in the desired sync and pattern receive mode. Select its MIDI channel and the program corresponding to the target on that device. The interface uses program numbers 1–128, with MIDI data bytes 0–127.
3. Disable Live's loop, set the cue lead in quarter notes, start playback, and click **Queue program**. Confirm the outgoing Program Change, then confirm whether the Rytm actually switched on the intended pattern boundary. Compare the observed lead and switching behavior with the Max for Live reference.
4. If the outgoing message is missing, record the Live version, platform, MIDI route and plug-in settings. A separate test can evaluate direct MIDI output while the plug-in follows Live transport.

The plug-in status reports submission to the host. Hardware routing, pattern addressing and switching remain bench checks. Multi-device scene scheduling and the standalone runtime will use the same planning rules once the single-device path is validated.

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
