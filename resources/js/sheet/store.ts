import { computed, reactive, ref } from 'vue';
import { RequestError, sendJson } from './api';
import { toast } from './toasts';
import type { Ability, Character, Item, Stat } from './types';

type Op = { id: number; busy?: string; apply?: (character: Character) => void };
type MutationResponse = { character: Character; warnings: string[] };

const ROOM_FIELDS = [
    'session_character_id',
    'assigned_user_id',
    'assigned_user_name',
] as const;

export type SheetActions = ReturnType<
    ReturnType<typeof createSheetStore>['actionsFor']
>;

/**
 * Optimistic character state.
 *
 * - `server` holds the latest authoritative snapshot per character (accepted only if its revision is not older).
 * - `pending` holds local operations that are applied on top of the snapshot until the server answers.
 * - Requests for one character run strictly in order, so every response already includes all earlier
 *   operations and each pending op is applied exactly once.
 */
export function createSheetStore(gameId: () => number) {
    const server = ref<Record<number, Character>>({});
    const pending = ref<Record<number, Op[]>>({});
    const queues = new Map<number, Promise<unknown>>();
    const dirty = new Set<number>();
    let nextOp = 1;

    const characters = computed<Record<number, Character>>(() => {
        const views: Record<number, Character> = {};
        for (const [id, snapshot] of Object.entries(server.value)) {
            const ops = pending.value[Number(id)] ?? [];
            if (!ops.some((op) => op.apply)) {
                views[Number(id)] = snapshot;
                continue;
            }
            const draft = JSON.parse(JSON.stringify(snapshot)) as Character;
            for (const op of ops) op.apply?.(draft);
            views[Number(id)] = draft;
        }
        return views;
    });

    const busy = computed(() => {
        const keys = new Set<string>();
        for (const ops of Object.values(pending.value))
            for (const op of ops) if (op.busy) keys.add(op.busy);
        return keys;
    });

    function accept(incoming: Character, force = false) {
        const current = server.value[incoming.id];
        if (current && !force && incoming.revision < current.revision) return;
        const merged = { ...incoming } as Character;
        for (const field of ROOM_FIELDS) {
            if (merged[field] === undefined && current?.[field] !== undefined) {
                (merged as Record<string, unknown>)[field] = current[field];
            }
        }
        server.value = { ...server.value, [incoming.id]: merged };
    }

    /** Replace snapshots from a full page load (authoritative); keeps newer local snapshots. */
    function load(list: Character[]) {
        const next: Record<number, Character> = {};
        for (const character of list) {
            const current = server.value[character.id];
            next[character.id] =
                current && current.revision > character.revision
                    ? {
                          ...current,
                          ...Object.fromEntries(
                              ROOM_FIELDS.map((field) => [
                                  field,
                                  character[field],
                              ]),
                          ),
                      }
                    : character;
        }
        server.value = next;
    }

    function hasPending(characterId: number) {
        return (pending.value[characterId]?.length ?? 0) > 0;
    }

    async function refresh(characterId: number) {
        if (hasPending(characterId)) {
            dirty.add(characterId); // The mutation response will be fresher; re-check afterwards.
            return;
        }
        try {
            const state = await sendJson<{ character: Character }>(
                'GET',
                `/games/${gameId()}/characters/${characterId}/state?system=0`,
            );
            if (!hasPending(characterId)) accept(state.character);
        } catch {
            // The periodic revision check will recover.
        }
    }

    function mutate(
        characterId: number,
        request: () => Promise<MutationResponse>,
        op: Omit<Op, 'id'> = {},
    ) {
        const entry: Op = { id: nextOp++, ...op };
        pending.value = {
            ...pending.value,
            [characterId]: [...(pending.value[characterId] ?? []), entry],
        };

        const run = async () => {
            try {
                const response = await request();
                accept(response.character);
                for (const warning of response.warnings ?? [])
                    toast(warning, 'warning', 6000);
            } catch (error) {
                toast(
                    error instanceof RequestError
                        ? error.message
                        : 'The change was not saved.',
                    'error',
                    6000,
                );
                dirty.add(characterId);
            } finally {
                pending.value = {
                    ...pending.value,
                    [characterId]: (pending.value[characterId] ?? []).filter(
                        (item) => item.id !== entry.id,
                    ),
                };
                if (!hasPending(characterId) && dirty.delete(characterId))
                    void refresh(characterId);
            }
        };
        const queued = (queues.get(characterId) ?? Promise.resolve()).then(run);
        queues.set(characterId, queued);
        return queued;
    }

    const stat = (character: Character, id: number) =>
        character.stats.find((entry) => entry.id === id);
    const item = (character: Character, id: number) =>
        character.items.find((entry) => entry.id === id);
    const clamp = (entry: Stat, value: number) => {
        const max =
            entry.maximum ??
            (entry.kind === 'resource' ? entry.max_value : null);
        return Math.min(
            max ?? Infinity,
            Math.max(entry.minimum ?? -Infinity, value),
        );
    };

    function actionsFor(characterId: number) {
        const base = `/characters/${characterId}`;
        const send =
            (
                method: 'POST' | 'PUT' | 'PATCH' | 'DELETE',
                url: string,
                body?: Record<string, unknown> | FormData,
            ) =>
            () =>
                sendJson<MutationResponse>(method, url, body);

        return {
            statDelta: (entry: Stat, delta: number) =>
                mutate(
                    characterId,
                    send('PATCH', `${base}/stats/${entry.id}`, { delta }),
                    {
                        apply: (draft) => {
                            const target = stat(draft, entry.id);
                            if (target)
                                target.value = clamp(
                                    target,
                                    Number(target.value) + delta,
                                );
                        },
                    },
                ),
            statSet: (entry: Stat, value: number) =>
                mutate(
                    characterId,
                    send('PATCH', `${base}/stats/${entry.id}`, { value }),
                    {
                        apply: (draft) => {
                            const target = stat(draft, entry.id);
                            if (target) target.value = value;
                        },
                    },
                ),
            statMax: (entry: Stat, max: number) =>
                mutate(
                    characterId,
                    send('PATCH', `${base}/stats/${entry.id}`, {
                        max_value: max,
                    }),
                    {
                        apply: (draft) => {
                            const target = stat(draft, entry.id);
                            if (target) {
                                target.max_value = max;
                                target.value = Math.min(
                                    Number(target.value),
                                    max,
                                );
                            }
                        },
                    },
                ),
            itemDelta: (entry: Item, delta: number) =>
                mutate(
                    characterId,
                    send('PATCH', `${base}/items/${entry.id}`, { delta }),
                    {
                        apply: (draft) => {
                            const target = item(draft, entry.id);
                            if (target)
                                target.quantity = Math.min(
                                    999,
                                    Math.max(1, target.quantity + delta),
                                );
                        },
                    },
                ),
            itemUse: (entry: Item) =>
                mutate(
                    characterId,
                    send('POST', `${base}/items/${entry.id}/use`),
                    {
                        apply: (draft) => {
                            const target = item(draft, entry.id);
                            if (!target) return;
                            if (target.quantity > 1) target.quantity -= 1;
                            else
                                draft.items = draft.items.filter(
                                    (candidate) => candidate.id !== entry.id,
                                );
                        },
                    },
                ),
            itemRemove: (entry: Item) =>
                mutate(
                    characterId,
                    send('DELETE', `${base}/items/${entry.id}`),
                    {
                        apply: (draft) => {
                            draft.items = draft.items.filter(
                                (candidate) => candidate.id !== entry.id,
                            );
                        },
                    },
                ),
            // Slot choice and swaps are decided by server rules, so equip shows a busy state instead of guessing.
            itemEquip: (entry: Item, slotId?: number) =>
                mutate(
                    characterId,
                    send(
                        'PUT',
                        slotId
                            ? `${base}/items/${entry.id}/slots/${slotId}`
                            : `${base}/items/${entry.id}/equip`,
                    ),
                    { busy: `item:${entry.id}` },
                ),
            itemUnequip: (entry: Item, slotId?: number) =>
                mutate(
                    characterId,
                    send(
                        'DELETE',
                        slotId
                            ? `${base}/items/${entry.id}/slots/${slotId}`
                            : `${base}/items/${entry.id}/equip`,
                    ),
                    {
                        apply: (draft) => {
                            const target = item(draft, entry.id);
                            if (target)
                                target.slots = slotId
                                    ? target.slots.filter(
                                          (slot) => slot.id !== slotId,
                                      )
                                    : [];
                        },
                    },
                ),
            itemAdd: (payload: {
                item_definition_id?: number | null;
                custom_name?: string;
                custom_description?: string;
                quantity: number;
            }) =>
                mutate(characterId, send('POST', `${base}/items`, payload), {
                    busy: 'items:add',
                }),
            abilityToggle: (kind: 'spells' | 'skills', entry: Ability) =>
                mutate(
                    characterId,
                    send('PATCH', `${base}/${kind}/${entry.id}`, {
                        is_used: !entry.is_used,
                    }),
                    {
                        apply: (draft) => {
                            const target = draft[kind].find(
                                (candidate) => candidate.id === entry.id,
                            );
                            if (target) target.is_used = !entry.is_used;
                        },
                    },
                ),
            abilityAdd: (kind: 'spells' | 'skills', definitionId: number) =>
                mutate(
                    characterId,
                    send('POST', `${base}/${kind}`, {
                        [kind === 'spells'
                            ? 'spell_definition_id'
                            : 'skill_definition_id']: definitionId,
                    }),
                    { busy: `${kind}:add` },
                ),
            abilityRemove: (kind: 'spells' | 'skills', entry: Ability) =>
                mutate(
                    characterId,
                    send('DELETE', `${base}/${kind}/${entry.id}`),
                    {
                        apply: (draft) => {
                            draft[kind] = draft[kind].filter(
                                (candidate) => candidate.id !== entry.id,
                            );
                        },
                    },
                ),
            noteSave: (content: string, isPrivate: boolean) =>
                mutate(
                    characterId,
                    send('PUT', `${base}/notes`, {
                        content,
                        is_private: isPrivate,
                    }),
                    {
                        busy: 'note',
                        apply: (draft) => {
                            draft.note = { content, is_private: isPrivate };
                        },
                    },
                ),
            portraitUpload: (file: File) => {
                const form = new FormData();
                form.append('image', file);
                return mutate(
                    characterId,
                    send('POST', `${base}/portrait`, form),
                    { busy: 'portrait' },
                );
            },
            portraitRemove: () =>
                mutate(characterId, send('DELETE', `${base}/portrait`), {
                    apply: (draft) => {
                        draft.portrait = null;
                    },
                }),
        };
    }

    return reactive({
        characters,
        busy,
        server,
        accept,
        load,
        refresh,
        hasPending,
        actionsFor,
    });
}
