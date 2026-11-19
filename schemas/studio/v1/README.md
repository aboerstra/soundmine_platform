# Studio v1 contracts

This directory contains the first **draft** Soundmine Studio interchange contracts. They are additive to the existing Laravel licensing platform and do not change any activation APIs.

- `device-profile.schema.json`: hardware ports, capabilities and evidence.
- `connections.schema.json`: explicit physical connections. Logical musical dependencies are separate.
- `workflow-intent.schema.json`: requested musical roles, goals and hardware-first constraints.
- `execution-plan.schema.json`: planning status and diagnostics.

Reference fixtures are in `fixtures/studio/v1/`.

Run the dependency-free **smoke test** from the repository root:

```sh
node scripts/validate-studio-fixtures.mjs
```

The smoke test checks selected required fields and typed graph semantics, including a negative mismatched-signal case. **It is not a full JSON Schema validator** and does not compile arbitrary workflows. Add full draft-2020-12 schema conformance validation, C++ parity tests, stable diagnostic identifiers, cycle/merge handling, and user authorization before treating these contracts as production-ready.

The example uses the user's declared BeatStep Pro pitch CV and gate connections to the Behringer 2600. It intentionally returns a conditional plan for per-note chord adaptation: the direct analog pitch path has no verified mechanism for per-step software retuning. This fixture must not be interpreted as proof of the hardware's externally controllable sequencing features.

See `docs/studio-workflow-foundation.md` for the intended architecture and implementation sequence.
