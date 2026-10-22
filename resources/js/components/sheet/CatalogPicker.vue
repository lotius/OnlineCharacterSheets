<script setup lang="ts">
import type { CatalogAbility, CatalogItem, SystemContext } from '@/sheet/types';
import { computed, ref, watch } from 'vue';
import BottomSheet from './BottomSheet.vue';

/** Searchable, category-filtered picker for adding catalog items, spells or skills (plus custom items). */
const props = defineProps<{
    open: boolean;
    kind: 'items' | 'spells' | 'skills';
    system: SystemContext;
    category?: string | null;
    owned?: number[];
}>();
const emit = defineEmits<{
    close: [];
    pick: [{ definitionId: number; quantity: number }];
    custom: [{ name: string; description: string; quantity: number }];
}>();

const search = ref('');
const filter = ref<string | null>(null);
const quantity = ref(1);
const customName = ref('');
const customDescription = ref('');
watch(
    () => props.open,
    (open) => {
        if (!open) return;
        search.value = '';
        quantity.value = 1;
        customName.value = '';
        customDescription.value = '';
        filter.value = props.category ?? null;
    },
);

type Entry = {
    id: number;
    name: string;
    icon: string | null;
    group: string | null;
    detail: string | null;
};
const entries = computed<Entry[]>(() => {
    const catalog = props.system.catalog;
    if (!catalog) return [];
    if (props.kind === 'items') {
        return catalog.items.map((item: CatalogItem) => ({
            id: item.id,
            name: item.name,
            icon: item.icon,
            group: item.category,
            detail:
                [
                    item.cost !== null ? `${item.cost}g` : null,
                    ...item.modifiers.map(
                        (mod) =>
                            `${mod.stat} ${mod.op === 'set' ? '=' : '+'}${mod.value}`,
                    ),
                ]
                    .filter(Boolean)
                    .join(' · ') || null,
        }));
    }
    return catalog[props.kind].map((ability: CatalogAbility) => ({
        id: ability.definition_id,
        name: ability.name,
        icon: ability.icon,
        group: ability.group,
        detail: ability.description,
    }));
});
const groups = computed(() =>
    [
        ...new Set(
            entries.value
                .map((entry) => entry.group)
                .filter((group): group is string => !!group),
        ),
    ].sort(),
);
const groupName = (key: string) =>
    props.system.item_categories.find((category) => category.key === key)
        ?.name ?? key;
const results = computed(() => {
    const term = search.value.trim().toLowerCase();
    return entries.value.filter(
        (entry) =>
            (!filter.value || entry.group === filter.value) &&
            (!term || entry.name.toLowerCase().includes(term)),
    );
});
const title = computed(
    () =>
        ({ items: 'Add item', spells: 'Add spell', skills: 'Add skill' })[
            props.kind
        ],
);
</script>

<template>
    <BottomSheet :open="open" :title="title" wide @close="emit('close')">
        <input
            v-model="search"
            type="search"
            placeholder="Search…"
            aria-label="Search catalog"
            class="min-h-11 w-full rounded-md border px-3"
        />
        <div
            v-if="groups.length > 1"
            class="-mx-1 mt-2 flex gap-1.5 overflow-x-auto px-1 pb-1"
        >
            <button
                type="button"
                class="min-h-9 shrink-0 rounded-full border px-3 text-sm"
                :class="
                    filter === null
                        ? 'border-amber-500 text-amber-400'
                        : 'border-stone-700'
                "
                @click="filter = null"
            >
                All
            </button>
            <button
                v-for="group in groups"
                :key="group"
                type="button"
                class="min-h-9 shrink-0 rounded-full border px-3 text-sm capitalize"
                :class="
                    filter === group
                        ? 'border-amber-500 text-amber-400'
                        : 'border-stone-700'
                "
                @click="filter = group"
            >
                {{ kind === 'items' ? groupName(group) : group }}
            </button>
        </div>
        <div
            v-if="kind === 'items'"
            class="mt-2 flex items-center gap-2 text-sm text-stone-400"
        >
            Quantity
            <button
                type="button"
                class="size-9 rounded bg-stone-800"
                aria-label="Fewer"
                @click="quantity = Math.max(1, quantity - 1)"
            >
                −
            </button>
            <strong class="w-6 text-center text-stone-100">{{
                quantity
            }}</strong>
            <button
                type="button"
                class="size-9 rounded bg-stone-800"
                aria-label="More"
                @click="quantity = Math.min(999, quantity + 1)"
            >
                +
            </button>
        </div>
        <ul class="mt-2 divide-y divide-stone-800">
            <li v-for="entry in results" :key="entry.id">
                <button
                    type="button"
                    class="flex min-h-12 w-full items-center gap-3 py-2 text-left active:bg-stone-800"
                    :class="{
                        'opacity-50':
                            owned?.includes(entry.id) && kind !== 'items',
                    }"
                    @click="
                        (emit('pick', { definitionId: entry.id, quantity }),
                        emit('close'))
                    "
                >
                    <img
                        v-if="entry.icon"
                        :src="entry.icon"
                        alt=""
                        class="size-9 shrink-0 object-contain"
                    />
                    <span
                        v-else
                        class="size-9 shrink-0 rounded bg-stone-800"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold">{{
                            entry.name
                        }}</span>
                        <span
                            v-if="entry.detail"
                            class="block truncate text-xs text-stone-400 capitalize"
                            >{{ entry.detail }}</span
                        >
                    </span>
                    <span
                        v-if="owned?.includes(entry.id) && kind !== 'items'"
                        class="text-xs text-stone-400"
                        >Known</span
                    >
                </button>
            </li>
            <li
                v-if="!results.length"
                class="py-6 text-center text-sm text-stone-500"
            >
                Nothing matches.
            </li>
        </ul>
        <form
            v-if="kind === 'items'"
            class="mt-3 mb-1 space-y-2 border-t border-stone-800 pt-3"
            @submit.prevent="
                customName.trim() &&
                (emit('custom', {
                    name: customName.trim(),
                    description: customDescription.trim(),
                    quantity,
                }),
                emit('close'))
            "
        >
            <p class="text-sm font-semibold text-stone-300">
                Or add a custom item
            </p>
            <input
                v-model="customName"
                placeholder="Name"
                aria-label="Custom item name"
                maxlength="120"
                class="min-h-11 w-full rounded-md border px-3"
            />
            <input
                v-model="customDescription"
                placeholder="Description (optional)"
                aria-label="Custom item description"
                maxlength="2000"
                class="min-h-11 w-full rounded-md border px-3"
            />
            <button
                class="min-h-11 w-full rounded-md bg-amber-600 font-bold"
                :disabled="!customName.trim()"
            >
                Add custom item
            </button>
        </form>
    </BottomSheet>
</template>
