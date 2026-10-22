import type { Character, Item, Modifier, Stat } from './types';

export type ModifierSource = {
    name: string;
    op: Modifier['op'];
    value: number;
};

export type DerivedStat = Stat & {
    /** Value after item modifiers (attributes/currency), or the raw value (resources). */
    effective: number;
    /** Maximum after item modifiers (resources only). */
    effective_max: number | null;
    sources: ModifierSource[];
};

const active = (item: Item, modifier: Modifier) =>
    (modifier.when ?? 'equipped') === 'carried' || item.slots.length > 0;

/** "set" replaces the base (highest set wins, e.g. the best equipped weapon); "add" stacks on top. */
function apply(base: number, mods: Array<ModifierSource>): number {
    const sets = mods.filter((mod) => mod.op === 'set').map((mod) => mod.value);
    const adds = mods
        .filter((mod) => mod.op === 'add')
        .reduce((sum, mod) => sum + mod.value, 0);
    return (sets.length ? Math.max(...sets) : base) + adds;
}

/**
 * Item modifiers apply to the value of attributes (Attack, Defend) and to the maximum of resources
 * (an artifact granting +1 Body raises max Body, not current Body).
 */
export function deriveStats(character: Character): DerivedStat[] {
    const byStat = new Map<string, ModifierSource[]>();
    for (const item of character.items) {
        for (const modifier of item.modifiers ?? []) {
            if (!active(item, modifier)) continue;
            const list = byStat.get(modifier.stat) ?? [];
            list.push({
                name: item.name,
                op: modifier.op,
                value: Number(modifier.value),
            });
            byStat.set(modifier.stat, list);
        }
    }

    return character.stats.map((stat) => {
        const sources = byStat.get(stat.key) ?? [];
        const value = Number(stat.value);
        if (stat.kind === 'resource') {
            const max = stat.max_value === null ? null : Number(stat.max_value);
            return {
                ...stat,
                effective: value,
                effective_max: max === null ? null : apply(max, sources),
                sources,
            };
        }
        return {
            ...stat,
            effective: apply(value, sources),
            effective_max: null,
            sources,
        };
    });
}

export function itemsInCategory(
    items: Item[],
    category?: string | null,
    exclude: string[] = [],
) {
    return items.filter((item) =>
        category
            ? item.category === category
            : !exclude.includes(item.category ?? ''),
    );
}

/** Slots an item may go into (one of), or every slot when the item has no slot rules. */
export function itemFitsSlot(
    item: Pick<Item, 'allowed_slots' | 'required_slots'>,
    slotKey: string,
) {
    if (item.required_slots.length)
        return item.required_slots.includes(slotKey);
    return !item.allowed_slots.length || item.allowed_slots.includes(slotKey);
}

export function isEquippable(
    item: Pick<Item, 'allowed_slots' | 'required_slots'>,
) {
    return item.allowed_slots.length > 0 || item.required_slots.length > 0;
}

export function countItems(
    character: Character,
    match: { item?: string | null; category?: string | null },
) {
    return character.items
        .filter((item) =>
            match.item
                ? item.key === match.item
                : match.category
                  ? item.category === match.category
                  : false,
        )
        .reduce((sum, item) => sum + item.quantity, 0);
}

export function formatModifier(source: ModifierSource) {
    return source.op === 'set'
        ? `${source.name} = ${source.value}`
        : `${source.name} ${source.value >= 0 ? '+' : ''}${source.value}`;
}
