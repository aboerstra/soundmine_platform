#!/usr/bin/env node
// Dependency-free reference validator for Soundmine Studio v1 fixtures.
// This is a contract test, not the eventual C++ workflow compiler.
import { readFileSync } from 'node:fs';
import assert from 'node:assert/strict';

const load = (name) => JSON.parse(readFileSync(new URL('../fixtures/studio/v1/' + name + '.json', import.meta.url), 'utf8'));
const fail = (message) => { throw new Error(message); };
const requireFields = (value, fields) => {
  assert(value && typeof value === 'object' && !Array.isArray(value), 'Expected object');
  for (const field of fields) assert(Object.hasOwn(value, field), 'Missing field: ' + field);
};
const validKinds = {
  'studio.device-profile': ['id','manufacturer','model','ports','capabilities'],
  'studio.connections': ['devices','connections'],
  'workflow.intent': ['id','name','roles','goals','constraints'],
  'workflow.execution-plan': ['intentId','status','diagnostics']
};
function validateDocument(doc) {
  requireFields(doc, ['schemaVersion','kind']);
  assert.equal(doc.schemaVersion, '1.0');
  assert(validKinds[doc.kind], 'Unknown document kind');
  requireFields(doc, validKinds[doc.kind]);
  if (doc.kind === 'studio.device-profile') {
    assert(Array.isArray(doc.ports) && Array.isArray(doc.capabilities));
    for (const port of doc.ports) {
      requireFields(port, ['id','signal','direction','evidence']);
      assert(['input','output','bidirectional'].includes(port.direction));
    }
  }
  if (doc.kind === 'studio.connections') {
    assert(Array.isArray(doc.devices) && Array.isArray(doc.connections));
    for (const c of doc.connections) {
      requireFields(c, ['id','from','to','signal','confirmation']);
      requireFields(c.from, ['deviceId','portId']);
      requireFields(c.to, ['deviceId','portId']);
    }
  }
  if (doc.kind === 'workflow.intent') {
    assert(Array.isArray(doc.goals) && doc.goals.length > 0);
    assert(typeof doc.roles === 'object');
  }
  if (doc.kind === 'workflow.execution-plan') {
    assert(['feasible','conditional','infeasible'].includes(doc.status));
    assert(Array.isArray(doc.diagnostics));
  }
  return true;
}
function validatePhysicalGraph(profiles, graph) {
  validateDocument(graph);
  const instances = new Map(graph.devices.map(d => [d.id, d.profileId]));
  const catalog = new Map(profiles.map(p => [p.id, p]));
  const seen = new Set();
  for (const c of graph.connections) {
    if (seen.has(c.id)) fail('Duplicate connection id: ' + c.id);
    seen.add(c.id);
    const src = catalog.get(instances.get(c.from.deviceId));
    const dst = catalog.get(instances.get(c.to.deviceId));
    if (!src || !dst) fail('Unknown device instance/profile');
    const out = src.ports.find(p => p.id === c.from.portId);
    const input = dst.ports.find(p => p.id === c.to.portId);
    if (!out || !input) fail('Unknown physical port');
    if (out.signal !== c.signal || input.signal !== c.signal) fail('Signal type mismatch');
    if (!['output','bidirectional'].includes(out.direction)) fail('Source is not an output');
    if (!['input','bidirectional'].includes(input.direction)) fail('Destination is not an input');
  }
  return true;
}
function planReferenceCase(intent, graph) {
  validateDocument(intent);
  const pitch = graph.connections.some(c => c.signal === 'pitch-cv' && c.from.deviceId === 'bsp' && c.to.deviceId === 'marvin');
  const gate = graph.connections.some(c => c.signal === 'gate' && c.from.deviceId === 'bsp' && c.to.deviceId === 'marvin');
  const preserve = intent.constraints.preserveDirectCvGate;
  const adaptation = intent.constraints.requirePerNoteHarmonicAdaptation;
  const diagnostics = [];
  if (adaptation && preserve && pitch && gate) {
    diagnostics.push({code:'PITCH_PATH_UNRESOLVED',severity:'warning',message:'Per-note harmonic adaptation is not established through the declared direct pitch CV path.'});
  }
  if (preserve && pitch && gate) {
    diagnostics.push({code:'HARDWARE_PATH_PRESERVED',severity:'info',message:'Direct BSP pitch CV and gate connections are retained as declared.'});
  }
  return {schemaVersion:'1.0',kind:'workflow.execution-plan',intentId:intent.id,status:adaptation ? 'conditional' : 'feasible',diagnostics};
}
const profiles = ['bsp-2600-device-profile','blue-marvin-device-profile','sl61-device-profile'].map(load);
const graph = load('bsp-2600-connections');
const intent = load('adaptive-bass-intent');
const expected = load('adaptive-bass-expected-plan');
for (const doc of [...profiles,graph,intent,expected]) validateDocument(doc);
validatePhysicalGraph(profiles,graph);
assert.deepEqual(planReferenceCase(intent,graph),expected);
const invalidGraph = structuredClone(graph);
invalidGraph.connections[0].signal = 'gate';
assert.throws(() => validatePhysicalGraph(profiles,invalidGraph), /Signal type mismatch/);
const invalidIntent = structuredClone(intent);
invalidIntent.schemaVersion = '99.0';
assert.throws(() => validateDocument(invalidIntent), /Expected values to be strictly equal/);
console.log('PASS: 6 fixture documents, physical CV/Gate graph, conditional pitch plan, 2 negative cases');
