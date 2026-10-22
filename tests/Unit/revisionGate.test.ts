import assert from 'node:assert/strict';
import test from 'node:test';
import { RevisionGate } from '../../resources/js/lib/revisionGate.ts';

test('duplicates and old notices do not fetch or advance applied state', () => {
    const gate = new RevisionGate();
    assert.equal(gate.observe(10, 10), false);
    assert.equal(gate.observe(9, 10), false);
    assert.equal(gate.observe(Number.NaN, 10), false);
    assert.equal(gate.observe(11, 10), true);
    assert.equal(gate.begin(), true);
    assert.equal(gate.finish(11, true), false);
});

test('a revision gap fetches authoritative state and notices during the fetch are retained', () => {
    const gate = new RevisionGate();
    assert.equal(gate.observe(15, 10), true);
    assert.equal(gate.begin(), true);
    assert.equal(gate.observe(16, 10), true);
    assert.equal(gate.begin(), false);
    assert.equal(gate.finish(15, true), true);
    assert.equal(gate.begin(), true);
    assert.equal(gate.finish(16, true), false);
});

test('failed reloads wait for a later retry instead of looping, and reconnect can force a check', () => {
    const gate = new RevisionGate();
    gate.observe(3, 2);
    assert.equal(gate.begin(), true);
    assert.equal(gate.finish(2, false), false);
    assert.equal(gate.begin(), true);
    assert.equal(gate.finish(3, true), false);
    assert.equal(gate.begin(), true);
    assert.equal(gate.finish(3, true), false);
});
