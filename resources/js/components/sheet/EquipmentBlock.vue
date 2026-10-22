<script setup lang="ts">
import { vFit } from '@/lib/fitText';
import { itemFitsSlot } from '@/sheet/derive';
import type { SheetActions } from '@/sheet/store';
import type { Character, Item, SheetMode, SystemContext } from '@/sheet/types';
import { computed, ref } from 'vue';
import BottomSheet from './BottomSheet.vue';

/** Paper-doll style slot grid: tap an empty slot to see only the items that fit it. */
const props = defineProps<{
    character: Character;
    system: SystemContext;
    mode: SheetMode;
    actions: SheetActions | null;
    compact?: boolean;
}>();
const emit = defineEmits<{ details: [Item] }>();
const choosing = ref<SystemContext['slots'][number] | null>(null);

const occupant = (slotId: number) =>
    props.character.items.filter((item) =>
        item.slots.some((slot) => slot.id === slotId),
    );
const candidates = computed(() =>
    choosing.value
        ? props.character.items.filter(
              (item) =>
                  !item.consumable &&
                  itemFitsSlot(item, choosing.value!.key) &&
                  (item.allowed_slots.length || item.required_slots.length),
          )
        : [],
);
const others = computed(() =>
    choosing.value
        ? props.character.items.filter(
              (item) =>
                  !item.consumable &&
                  !item.allowed_slots.length &&
                  !item.required_slots.length &&
                  !item.slots.length,
          )
        : [],
);
// Compact cards show only filled slots (a two-handed item once), plus a "+N empty" tile that expands.
const expanded = ref(false);
type Tile = {
    key: string;
    label: string;
    slot: SystemContext['slots'][number];
    items: Item[];
};
const tiles = computed<Tile[]>(() => {
    const all = props.system.slots.map((slot) => ({
        key: `slot-${slot.id}`,
        label: slot.name,
        slot,
        items: occupant(slot.id),
    }));
    if (!props.compact && props.mode !== 'overlay') return all;
    if (expanded.value) return all;
    const filled: Tile[] = [];
    for (const tile of all.filter((entry) => entry.items.length)) {
        const item = tile.items[0];
        const existing = filled.find(
            (entry) =>
                entry.items.length === 1 &&
                entry.items[0].id === item.id &&
                tile.items.length === 1,
        );
        if (existing) existing.label = `${existing.label} + ${tile.label}`;
        else
            filled.push({
                ...tile,
                key: `item-${tile.items.map((entry) => entry.id).join('-')}`,
            });
    }
    return filled;
});
const emptyCount = computed(
    () => props.system.slots.filter((slot) => !occupant(slot.id).length).length,
);
const tap = (slot: SystemContext['slots'][number]) => {
    if (props.mode === 'overlay') return;
    const [item] = occupant(slot.id);
    if (item) emit('details', item);
    else if (props.actions) choosing.value = slot;
};
const choose = (item: Item) => {
    if (choosing.value) void props.actions?.itemEquip(item, choosing.value.id);
    choosing.value = null;
};
</script>

<template>
    <div
        class="grid gap-1.5"
        :class="
            compact || mode === 'overlay'
                ? 'grid-cols-[repeat(auto-fill,minmax(9.5rem,1fr))]'
                : 'grid-cols-2 sm:grid-cols-3'
        "
    >
        <button
            v-for="tile in tiles"
            :key="tile.key"
            type="button"
            class="flex min-h-12 items-center gap-2 rounded-md border px-2 py-1.5 text-left"
            :class="
                tile.items.length
                    ? 'border-amber-900/80 bg-stone-950'
                    : 'border-dashed border-stone-700 bg-transparent'
            "
            :disabled="mode === 'overlay' || (!tile.items.length && !actions)"
            @click="tap(tile.slot)"
        >
            <img
                v-if="tile.items[0]?.icon"
                :src="tile.items[0].icon!"
                alt=""
                class="size-8 shrink-0 object-contain"
            />
            <span class="min-w-0">
                <span
                    v-fit
                    class="block truncate text-[11px] leading-tight tracking-wide text-stone-300 uppercase"
                    >{{ tile.label }}</span
                >
                <span
                    v-fit
                    class="block truncate text-sm leading-tight font-semibold"
                    :class="
                        tile.items.length ? 'text-stone-100' : 'text-stone-500'
                    "
                >
                    {{
                        tile.items.map((item) => item.name).join(', ') ||
                        'Empty'
                    }}
                </span>
            </span>
        </button>
        <button
            v-if="
                (compact || mode === 'overlay') &&
                mode !== 'overlay' &&
                emptyCount &&
                (actions || expanded)
            "
            type="button"
            class="flex min-h-12 items-center justify-center rounded-md border border-dashed border-stone-700 px-2 text-sm text-stone-400"
            :aria-expanded="expanded"
            @click="expanded = !expanded"
        >
            {{ expanded ? 'Hide empty' : `+${emptyCount} empty` }}
        </button>
        <p
            v-if="!tiles.length && !(compact && actions && emptyCount)"
            class="col-span-full px-1 py-1 text-sm text-stone-500"
        >
            Nothing equipped.
        </p>
    </div>
    <BottomSheet
        v-if="actions"
        :open="choosing !== null"
        :title="`Equip ${choosing?.name ?? ''}`"
        @close="choosing = null"
    >
        <ul class="divide-y divide-stone-800">
            <li v-for="item in [...candidates, ...others]" :key="item.id">
                <button
                    type="button"
                    class="flex min-h-12 w-full items-center gap-3 py-2 text-left active:bg-stone-800"
                    @click="choose(item)"
                >
                    <img
                        v-if="item.icon"
                        :src="item.icon"
                        alt=""
                        class="size-9 shrink-0 object-contain"
                    />
                    <span
                        v-else
                        class="size-9 shrink-0 rounded bg-stone-800"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold">{{ item.name }}</span>
                        <span class="block text-xs text-stone-400">
                            <template v-if="item.slots.length"
                                >In
                                {{
                                    item.slots
                                        .map((slot) => slot.name)
                                        .join(' + ')
                                }}
                                ·
                            </template>
                            <template v-if="item.required_slots.length"
                                >Two-handed ·
                            </template>
                            <template v-if="item.restriction">{{
                                item.restriction
                            }}</template>
                        </span>
                    </span>
                </button>
            </li>
            <li
                v-if="!candidates.length && !others.length"
                class="py-6 text-center text-sm text-stone-500"
            >
                No carried item fits this slot.
            </li>
        </ul>
    </BottomSheet>
</template>
