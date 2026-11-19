#!/usr/bin/env python3
"""Soundmine Studio v1: JSON Schema and typed physical graph conformance checks.

Run: python -m pip install 'jsonschema>=4.23,<5'
     python scripts/validate_studio_contracts.py
"""
from __future__ import annotations

import copy
import json
from pathlib import Path

from jsonschema import Draft202012Validator, ValidationError

ROOT = Path(__file__).resolve().parents[1]
SCHEMAS = ROOT / "schemas/studio/v1"
FIXTURES = ROOT / "fixtures/studio/v1"
KINDS = {
    "studio.device-profile": "device-profile",
    "studio.connections": "connections",
    "workflow.intent": "workflow-intent",
    "workflow.execution-plan": "execution-plan",
}


def read(path: Path) -> dict:
    return json.loads(path.read_text(encoding="utf-8"))


def validate_schema(document: dict) -> None:
    kind = document.get("kind")
    if kind not in KINDS:
        raise ValueError(f"Unsupported document kind: {kind}")
    schema = read(SCHEMAS / f"{KINDS[kind]}.schema.json")
    Draft202012Validator.check_schema(schema)
    Draft202012Validator(schema).validate(document)


def validate_connections(graph: dict, profiles: list[dict]) -> None:
    validate_schema(graph)
    catalog = {p["id"]: p for p in profiles}
    instances = {}
    for device in graph["devices"]:
        if device["id"] in instances:
            raise ValueError(f"Duplicate device instance: {device['id']}")
        if device["profileId"] not in catalog:
            raise ValueError(f"Unknown device profile: {device['profileId']}")
        instances[device["id"]] = catalog[device["profileId"]]

    seen = set()
    for edge in graph["connections"]:
        if edge["id"] in seen:
            raise ValueError(f"Duplicate connection: {edge['id']}")
        seen.add(edge["id"])
        ends = []
        for key in ("from", "to"):
            endpoint = edge[key]
            device = instances.get(endpoint["deviceId"])
            if device is None:
                raise ValueError(f"Unknown device: {endpoint['deviceId']}")
            port = next((p for p in device["ports"] if p["id"] == endpoint["portId"]), None)
            if port is None:
                raise ValueError(f"Unknown port: {endpoint['portId']}")
            if port["signal"] != edge["signal"]:
                raise ValueError(f"Signal mismatch at {endpoint['portId']}")
            ends.append(port)
        if ends[0]["direction"] not in ("output", "bidirectional"):
            raise ValueError("Source port cannot transmit")
        if ends[1]["direction"] not in ("input", "bidirectional"):
            raise ValueError("Destination port cannot receive")


def reference_plan(intent: dict, graph: dict) -> dict:
    """Only the first documented acceptance scenario, not a general planner."""
    validate_schema(intent)
    validate_schema(graph)
    roles = intent["roles"]
    pattern = roles.get("patternSource")
    synth = roles.get("soundGenerator")
    direct_pitch = any(
        e["signal"] == "pitch-cv"
        and e["from"]["deviceId"] == pattern
        and e["to"]["deviceId"] == synth
        for e in graph["connections"]
    )
    direct_gate = any(
        e["signal"] == "gate"
        and e["from"]["deviceId"] == pattern
        and e["to"]["deviceId"] == synth
        for e in graph["connections"]
    )
    constraints = intent["constraints"]
    diagnostics = []
    if constraints.get("requirePerNoteHarmonicAdaptation") and constraints.get("preserveDirectCvGate") and direct_pitch and direct_gate:
        diagnostics.append({
            "code": "PITCH_PATH_UNRESOLVED",
            "severity": "warning",
            "message": "Per-note harmonic adaptation is not established through the declared direct pitch CV path.",
        })
    if constraints.get("preserveDirectCvGate") and direct_pitch and direct_gate:
        diagnostics.append({
            "code": "HARDWARE_PATH_PRESERVED",
            "severity": "info",
            "message": "Direct BSP pitch CV and gate connections are retained as declared.",
        })
    return {
        "schemaVersion": "1.0",
        "kind": "workflow.execution-plan",
        "intentId": intent["id"],
        "status": "conditional" if diagnostics and diagnostics[0]["code"] == "PITCH_PATH_UNRESOLVED" else "infeasible",
        "diagnostics": diagnostics,
    }


def must_fail(fn, error_type: type[Exception]) -> None:
    try:
        fn()
    except error_type:
        return
    raise AssertionError(f"Expected {error_type.__name__}")


def main() -> None:
    docs = {p.stem: read(p) for p in FIXTURES.glob("*.json")}
    assert len(docs) == 6, f"Expected six reference fixtures, found {len(docs)}"
    for document in docs.values():
        validate_schema(document)
    profiles = [doc for doc in docs.values() if doc["kind"] == "studio.device-profile"]
    graph = docs["bsp-2600-connections"]
    intent = docs["adaptive-bass-intent"]
    validate_connections(graph, profiles)
    actual = reference_plan(intent, graph)
    assert actual == docs["adaptive-bass-expected-plan"]
    validate_schema(actual)

    mismatch = copy.deepcopy(graph)
    mismatch["connections"][0]["signal"] = "gate"
    must_fail(lambda: validate_connections(mismatch, profiles), ValueError)

    unknown_port = copy.deepcopy(graph)
    unknown_port["connections"][0]["to"]["portId"] = "missing"
    must_fail(lambda: validate_connections(unknown_port, profiles), ValueError)

    unknown_profile = copy.deepcopy(graph)
    unknown_profile["devices"][0]["profileId"] = "missing"
    must_fail(lambda: validate_connections(unknown_profile, profiles), ValueError)

    wrong_version = copy.deepcopy(intent)
    wrong_version["schemaVersion"] = "99.0"
    must_fail(lambda: validate_schema(wrong_version), ValidationError)

    extra_property = copy.deepcopy(intent)
    extra_property["untrustedCommand"] = "execute"
    must_fail(lambda: validate_schema(extra_property), ValidationError)

    wrong_direction = copy.deepcopy(graph)
    wrong_direction["connections"][0]["from"]["deviceId"] = "marvin"
    wrong_direction["connections"][0]["from"]["portId"] = "pitch-in"
    wrong_direction["connections"][0]["to"]["deviceId"] = "bsp"
    wrong_direction["connections"][0]["to"]["portId"] = "seq1-pitch"
    must_fail(lambda: validate_connections(wrong_direction, profiles), ValueError)

    print("PASS: 6 JSON Schema fixtures, physical graph, reference plan, 6 negative cases")


if __name__ == "__main__":
    main()
