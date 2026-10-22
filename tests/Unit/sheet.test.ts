import assert from 'node:assert/strict';
import test from 'node:test';
import {
    countItems,
    deriveStats,
    itemFitsSlot,
} from '../../resources/js/sheet/derive.ts';
import { decide } from '../../resources/js/sheet/syncDecision.ts';
import type { Character, Item, Stat } from '../../resources/js/sheet/types.ts';

const notice = (
    revision: number,
    character_id: number | null = 7,
    character_revision: number | null = 3,
) => ({ revision, character_id, character_revision });

void test('old and duplicate notices are ignored', () => {
    assert.equal(decide(notice(10), 10, 2), 'ignore');
    assert.equal(decide(notice(9), 10, 2), 'ignore');
    assert.equal(decide(notice(Number.NaN), 10, 2), 'ignore');
});

void test('the next notice for a known character refreshes only that character', () => {
    assert.equal(decide(notice(11, 7, 3), 10, 2), 'refresh-character');
});

void test('our own mutation response arriving first means the notice only advances the revision', () => {
    assert.equal(decide(notice(11, 7, 3), 10, 3), 'advance');
});

void test('gaps, room-level changes and unknown characters reload everything', () => {
    assert.equal(decide(notice(12), 10, 2), 'reload');
    assert.equal(decide(notice(11, null, null), 10, null), 'reload');
    assert.equal(decide(notice(11, 99, 1), 10, null), 'reload');
});

const stat = (
    key: string,
    kind: Stat['kind'],
    value: number,
    max: number | null = null,
): Stat => ({
    id: key.length,
    key,
    name: key,
    kind,
    icon: null,
    value,
    max_value: max,
    minimum: 0,
    maximum: null,
});
const item = (name: string, overrides: Partial<Item>): Item => ({
    id: name.length,
    definition_id: 1,
    key: name.toLowerCase(),
    name,
    description: null,
    icon: null,
    quantity: 1,
    slots: [],
    category: null,
    cost: null,
    modifiers: [],
    allowed_slots: [],
    required_slots: [],
    consumable: false,
    tags: [],
    properties: {},
    ...overrides,
});
const hero = (items: Item[]): Character => ({
    id: 1,
    revision: 0,
    name: 'Hero',
    class_key: null,
    class_name: null,
    portrait: null,
    can_update: true,
    stats: [
        stat('body', 'resource', 6, 8),
        stat('attack', 'attribute', 1),
        stat('defend', 'attribute', 2),
        stat('gold', 'currency', 30),
    ],
    items,
    spells: [],
    skills: [],
    note: null,
});
const hand = [{ id: 1, key: 'right_hand', name: 'Main Hand' }];

void test('equipped weapons set attack (best wins) and armor adds to defend', () => {
    const derived = deriveStats(
        hero([
            item('Broadsword', {
                slots: hand,
                modifiers: [{ stat: 'attack', op: 'set', value: 3 }],
            }),
            item('Dagger', {
                slots: [{ id: 2, key: 'left_hand', name: 'Off Hand' }],
                modifiers: [{ stat: 'attack', op: 'set', value: 1 }],
            }),
            item('Helmet', {
                slots: [{ id: 3, key: 'head', name: 'Head' }],
                modifiers: [{ stat: 'defend', op: 'add', value: 1 }],
            }),
            item('Shield', {
                slots: [],
                modifiers: [{ stat: 'defend', op: 'add', value: 1 }],
            }),
        ]),
    );
    const by = Object.fromEntries(derived.map((entry) => [entry.key, entry]));
    assert.equal(by.attack.effective, 3);
    assert.equal(
        by.defend.effective,
        3,
        'the unequipped shield does not count',
    );
    assert.equal(by.defend.sources.length, 1);
    assert.equal(by.gold.effective, 30);
});

void test('modifiers raise a resource maximum, not its current value, and carried items count', () => {
    const derived = deriveStats(
        hero([
            item('Talisman', {
                modifiers: [
                    { stat: 'body', op: 'add', value: 1, when: 'carried' },
                ],
            }),
        ]),
    );
    const body = derived.find((entry) => entry.key === 'body')!;
    assert.equal(body.effective, 6);
    assert.equal(body.effective_max, 9);
});

void test('slot fitting and item counting follow item rules', () => {
    assert.ok(
        itemFitsSlot({ allowed_slots: ['head'], required_slots: [] }, 'head'),
    );
    assert.ok(
        !itemFitsSlot({ allowed_slots: ['head'], required_slots: [] }, 'body'),
    );
    assert.ok(
        itemFitsSlot(
            { allowed_slots: [], required_slots: ['left_hand', 'right_hand'] },
            'left_hand',
        ),
    );
    assert.ok(
        itemFitsSlot({ allowed_slots: [], required_slots: [] }, 'anything'),
    );
    const character = hero([
        item('Potion', { category: 'potion', quantity: 2 }),
        item('Elixir', { category: 'potion', quantity: 1, key: 'elixir' }),
    ]);
    assert.equal(countItems(character, { category: 'potion' }), 3);
    assert.equal(countItems(character, { item: 'elixir' }), 1);
});
