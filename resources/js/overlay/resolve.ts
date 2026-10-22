import type { Character } from '@/sheet/types';
import type { OverlayConfig, OverlayElement, OverlayState } from './types';

/** Hero slot n → character: an explicit mapping when set (null = auto), otherwise nth in turn order. */
export function heroFor(
    config: OverlayConfig,
    characters: Character[],
    currentId: number | null,
    hero: OverlayElement['hero'],
): Character | null {
    if (hero === null) return null;
    if (hero === 'current')
        return (
            characters.find((character) => character.id === currentId) ?? null
        );
    const mapped = config.hero_slots?.[hero - 1];
    if (mapped !== undefined && mapped !== null)
        return characters.find((character) => character.id === mapped) ?? null;
    return characters[hero - 1] ?? null;
}

export function isVisible(
    element: OverlayElement,
    character: Character | null,
    currentId: number | null,
): boolean {
    switch (element.show) {
        case 'occupied':
            return character !== null;
        case 'empty':
            return element.hero !== null && character === null;
        case 'current':
            return character !== null && character.id === currentId;
        case 'not-current':
            return character !== null && character.id !== currentId;
        default:
            return true;
    }
}

/** A stand-in party so layouts can be designed without touching (or having) a live game. */
export function sampleState(base: OverlayState): OverlayState {
    const heroes = [
        ['Barbarian', 7, 8, 2, 3],
        ['Dwarf', 5, 7, 3, 1],
        ['Elf', 6, 6, 4, 2],
        ['Wizard', 2, 4, 6, 0],
    ] as const;
    const characters: Character[] = heroes.map(
        ([name, body, maxBody, mind, potions], index) => ({
            id: -(index + 1),
            revision: 0,
            name,
            class_key: name.toLowerCase(),
            class_name: name,
            portrait: null,
            can_update: false,
            assigned_user_name: `Player ${index + 1}`,
            stats: base.system.stats.map((stat, statIndex) => ({
                id: -(index * 100 + statIndex + 1),
                key: stat.key,
                name: stat.name,
                kind: stat.kind,
                icon: null,
                value:
                    stat.key === 'body'
                        ? body
                        : stat.key === 'mind'
                          ? mind
                          : stat.kind === 'currency'
                            ? 75 * (index + 1)
                            : stat.key === 'defend'
                              ? 2
                              : stat.key === 'move'
                                ? 2
                                : 1,
                max_value:
                    stat.key === 'body'
                        ? maxBody
                        : stat.key === 'mind'
                          ? mind
                          : null,
                minimum: 0,
                maximum: null,
            })),
            items: potions
                ? [
                      {
                          id: -(index * 100 + 50),
                          definition_id: null,
                          key: 'healing_potion',
                          name: 'Potion of Healing',
                          description: null,
                          icon: '/icons/potion-ball.svg',
                          quantity: potions,
                          slots: [],
                          category: 'potion',
                          cost: null,
                          modifiers: [],
                          allowed_slots: [],
                          required_slots: [],
                          consumable: true,
                          tags: [],
                          properties: {},
                      },
                  ]
                : [],
            spells: [],
            skills: [],
            note: null,
        }),
    );

    return {
        ...base,
        characters,
        game: { ...base.game, current_character_id: characters[1].id },
    };
}
