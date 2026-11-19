# Studio v1 contracts

This directory contains the first **draft** Soundmine Studio interchange contracts. They are additive to the existing Laravel licensing platform and do not change any activation APIs.

- `device-profile.schema.json`: hardware ports, capabilities and evidence.
- `connections.schema.json`: explicit physical connections. Logical musical dependencies are separate.
- `workflow-intent.schema.json`: requested musical roles, goals and hardware-first constraints.
- `execution-plan.schema.json`: planning status and diagnostics.

Reference fixtures are in `fixtures/studio/v1/`.

From the repository root, run the draft 2020-12 conformance checks and the dependency-free smoke test:

```sh
python -m pip install 'jsonschema>=4.23,<5'
python scripts/validate_studio_contracts.py
node scripts/validate-studio-fixtures.mjs
```

JSON Schema checks document structure. The Python validator also checks selected cross-document constraints, including identifier uniqueness, port direction and signal compatibility, exclusive physical inputs, and reference intent roles. Its reference plan check covers the supplied fixture only.

The example uses the user's declared BeatStep Pro pitch CV and gate connections to the Behringer 2600. It intentionally returns a conditional plan for per-note chord adaptation: the direct analog pitch path has no verified mechanism for per-step software retuning. This fixture must not be interpreted as proof of the hardware's externally controllable sequencing features.

See `docs/studio-workflow-foundation.md` for the intended architecture and implementation sequence.


The draft 1.0 intent accepts optional `requirements` and `eventGraph` fields. A capability requirement names a bound role, capability ID, hard or soft priority, and evidence threshold. Event graphs use typed input/output ports and point-to-point routes. Fan-in on harmony and control inputs requires an explicit merge policy. Note and clock fan-in requires ownership semantics beyond this draft.

A generated execution plan can include `assignments`, `retainedConnections`, and `eventGraph`. Original 1.0 documents remain readable. Older strict validators need the updated schemas to accept documents containing these new fields. The native planner evaluates the supplied inventory and treats generated plans as proposals.
