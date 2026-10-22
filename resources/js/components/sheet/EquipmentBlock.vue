<script setup lang="ts">
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
                ? 'grid-cols-[repeat(auto-fill,minmax(6.5rem,1fr))]'
                : 'grid-cols-2 sm:grid-cols-3'
        "
    >
        <button
            v-for="slot in system.slots"
            :key="slot.id"
            type="button"
            class="flex min-h-12 items-center gap-2 rounded-md border px-2 py-1.5 text-left"
            :class="
                occupant(slot.id).length
                    ? 'border-amber-900/80 bg-stone-950'
                    : 'border-dashed border-stone-700 bg-transparent'
            "
            :disabled="
                mode === 'overlay' || (!occupant(slot.id).length && !actions)
            "
            @click="tap(slot)"
        >
            <img
                v-if="occupant(slot.id)[0]?.icon"
                :src="occupant(slot.id)[0].icon!"
                alt=""
                class="size-8 shrink-0 object-contain"
            />
            <span class="min-w-0">
                <span
                    class="block text-[10px] tracking-wider text-stone-400 uppercase"
                    >{{ slot.name }}</span
                >
                <span
                    class="block truncate text-sm font-semibold"
                    :class="
                        occupant(slot.id).length
                            ? 'text-stone-100'
                            : 'text-stone-500'
                    "
                >
                    {{
                        occupant(slot.id)
                            .map((item) => item.name)
                            .join(', ') || 'Empty'
                    }}
                </span>
            </span>
        </button>
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
