<script setup lang="ts">
import type { Block, BlockType, Layouts, SystemContext } from '@/sheet/types';
import { router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

/** Room-level sheet configuration: which blocks appear, in what order, with which options. */
const props = defineProps<{
    gameId: number;
    room: Partial<Layouts> | null;
    defaults: Layouts;
    system: SystemContext;
}>();

const names = [
    { key: 'sheet', label: 'Phone sheet' },
    { key: 'card', label: 'Room cards' },
] as const;
type Name = (typeof names)[number]['key'];
const blockTypes: Array<{ type: BlockType; label: string }> = [
    { type: 'stats', label: 'Stats' },
    { type: 'equipment', label: 'Equipment slots' },
    { type: 'items', label: 'Item list' },
    { type: 'abilities', label: 'Spells / skills' },
    { type: 'notes', label: 'Notes' },
];

const clone = (blocks: Block[]) =>
    JSON.parse(JSON.stringify(blocks)) as Block[];
const active = ref<Name>('sheet');
const useDefault = reactive<Record<Name, boolean>>({
    sheet: !props.room?.sheet,
    card: !props.room?.card,
});
const draft = reactive<Record<Name, Block[]>>({
    sheet: clone(props.room?.sheet ?? props.defaults.sheet),
    card: clone(props.room?.card ?? props.defaults.card),
});
const adding = ref<BlockType>('items');
const saving = ref(false);

const edit = () => (useDefault[active.value] = false);
const move = (index: number, offset: number) => {
    const list = draft[active.value];
    const target = index + offset;
    if (target < 0 || target >= list.length) return;
    [list[index], list[target]] = [list[target], list[index]];
    edit();
};
const remove = (index: number) => {
    draft[active.value].splice(index, 1);
    edit();
};
const add = () => {
    const block: Block = {
        id: Math.random().toString(36).slice(2, 10),
        type: adding.value,
        title: null,
        collapsed: false,
    };
    if (adding.value === 'abilities') block.kind = 'spells';
    if (adding.value === 'items') {
        block.category = null;
        block.exclude_categories = [];
    }
    draft[active.value].push(block);
    edit();
};
const toggleIn = (list: string[] | undefined, value: string) => {
    const next = new Set(list ?? []);
    if (next.has(value)) next.delete(value);
    else next.add(value);
    return [...next];
};
const reset = () => {
    useDefault[active.value] = true;
    draft[active.value] = clone(props.defaults[active.value]);
};
const save = () => {
    saving.value = true;
    router.put(
        `/games/${props.gameId}/layouts`,
        {
            sheet: useDefault.sheet ? null : draft.sheet,
            card: useDefault.card ? null : draft.card,
        },
        { preserveScroll: true, onFinish: () => (saving.value = false) },
    );
};
</script>

<template>
    <div>
        <div class="flex gap-1" role="tablist">
            <button
                v-for="name in names"
                :key="name.key"
                type="button"
                role="tab"
                :aria-selected="active === name.key"
                class="min-h-11 flex-1 rounded-md border px-3 text-sm font-semibold"
                :class="
                    active === name.key
                        ? 'border-amber-500 text-amber-400'
                        : 'border-stone-700'
                "
                @click="active = name.key"
            >
                {{ name.label }}
            </button>
        </div>
        <p class="mt-2 text-xs text-stone-400">
            {{
                useDefault[active]
                    ? 'Using the game system default. Any change creates a room-specific layout.'
                    : 'Room-specific layout.'
            }}
        </p>

        <ol class="mt-2 space-y-2">
            <li
                v-for="(block, index) in draft[active]"
                :key="block.id"
                class="rounded-md border border-stone-800 bg-stone-950 p-2"
            >
                <div class="flex items-center gap-1">
                    <span
                        class="w-24 shrink-0 text-xs font-bold tracking-wider text-amber-500 uppercase"
                        >{{
                            blockTypes.find(
                                (entry) => entry.type === block.type,
                            )?.label
                        }}</span
                    >
                    <input
                        v-model="block.title"
                        placeholder="Default title"
                        aria-label="Section title"
                        class="min-h-10 min-w-0 flex-1 rounded border px-2 text-sm"
                        @input="edit"
                    />
                    <button
                        type="button"
                        class="size-10 rounded bg-stone-800 disabled:opacity-30"
                        :disabled="index === 0"
                        aria-label="Move up"
                        @click="move(index, -1)"
                    >
                        ↑
                    </button>
                    <button
                        type="button"
                        class="size-10 rounded bg-stone-800 disabled:opacity-30"
                        :disabled="index === draft[active].length - 1"
                        aria-label="Move down"
                        @click="move(index, 1)"
                    >
                        ↓
                    </button>
                    <button
                        type="button"
                        class="size-10 rounded bg-stone-800 text-red-300"
                        aria-label="Remove section"
                        @click="remove(index)"
                    >
                        ×
                    </button>
                </div>
                <div
                    class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm"
                >
                    <label class="flex min-h-9 items-center gap-2">
                        <input
                            v-model="block.collapsed"
                            type="checkbox"
                            class="size-4"
                            @change="edit"
                        />
                        Start collapsed
                    </label>
                    <template v-if="block.type === 'abilities'">
                        <select
                            v-model="block.kind"
                            aria-label="Ability kind"
                            class="min-h-9 rounded border px-2"
                            @change="edit"
                        >
                            <option value="spells">Spells</option>
                            <option value="skills">Skills</option>
                        </select>
                    </template>
                    <template v-if="block.type === 'items'">
                        <select
                            v-model="block.category"
                            aria-label="Item category"
                            class="min-h-9 rounded border px-2"
                            @change="edit"
                        >
                            <option :value="null">All categories</option>
                            <option
                                v-for="category in system.item_categories"
                                :key="category.key"
                                :value="category.key"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                        <span
                            v-if="
                                !block.category && system.item_categories.length
                            "
                            class="flex flex-wrap items-center gap-2 text-xs text-stone-400"
                        >
                            Except:
                            <label
                                v-for="category in system.item_categories"
                                :key="category.key"
                                class="flex items-center gap-1"
                            >
                                <input
                                    type="checkbox"
                                    class="size-4"
                                    :checked="
                                        block.exclude_categories?.includes(
                                            category.key,
                                        )
                                    "
                                    @change="
                                        ((block.exclude_categories = toggleIn(
                                            block.exclude_categories,
                                            category.key,
                                        )),
                                        edit())
                                    "
                                />
                                {{ category.name }}
                            </label>
                        </span>
                    </template>
                    <template v-if="block.type === 'stats'">
                        <span
                            class="flex flex-wrap items-center gap-2 text-xs text-stone-400"
                        >
                            Show:
                            <label
                                v-for="stat in system.stats"
                                :key="stat.key"
                                class="flex items-center gap-1"
                            >
                                <input
                                    type="checkbox"
                                    class="size-4"
                                    :checked="
                                        !block.keys?.length ||
                                        block.keys.includes(stat.key)
                                    "
                                    @change="
                                        ((block.keys = toggleIn(
                                            block.keys?.length
                                                ? block.keys
                                                : system.stats.map(
                                                      (entry) => entry.key,
                                                  ),
                                            stat.key,
                                        )),
                                        edit())
                                    "
                                />
                                {{ stat.name }}
                            </label>
                        </span>
                    </template>
                </div>
            </li>
        </ol>

        <div class="mt-3 flex flex-wrap gap-2">
            <select
                v-model="adding"
                aria-label="Section type to add"
                class="min-h-11 rounded-md border px-2"
            >
                <option
                    v-for="entry in blockTypes"
                    :key="entry.type"
                    :value="entry.type"
                >
                    {{ entry.label }}
                </option>
            </select>
            <button
                type="button"
                class="min-h-11 rounded-md bg-stone-700 px-4 font-semibold"
                @click="add"
            >
                Add section
            </button>
            <span class="flex-1" />
            <button
                type="button"
                class="min-h-11 rounded-md border border-stone-700 px-4 text-sm"
                @click="reset"
            >
                Use system default
            </button>
            <button
                type="button"
                class="min-h-11 rounded-md bg-amber-600 px-5 font-bold disabled:opacity-50"
                :disabled="saving"
                @click="save"
            >
                {{ saving ? 'Saving…' : 'Save layouts' }}
            </button>
        </div>
    </div>
</template>
