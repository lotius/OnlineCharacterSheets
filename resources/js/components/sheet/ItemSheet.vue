<script setup lang="ts">
import { isEquippable, itemFitsSlot } from '@/sheet/derive';
import type { SheetActions } from '@/sheet/store';
import type { Item, SystemContext } from '@/sheet/types';
import { computed, ref, watch } from 'vue';
import BottomSheet from './BottomSheet.vue';
import HoldButton from './HoldButton.vue';

const props = defineProps<{
    item: Item | null;
    slots: SystemContext['slots'];
    actions: SheetActions | null;
    busy: boolean;
}>();
const emit = defineEmits<{ close: [] }>();
const confirmRemove = ref(false);
watch(
    () => props.item?.id,
    () => (confirmRemove.value = false),
);

const fitting = computed(() =>
    props.item
        ? props.slots.filter((slot) => itemFitsSlot(props.item!, slot.key))
        : [],
);
const modifiers = computed(() =>
    (props.item?.modifiers ?? []).map(
        (mod) =>
            `${mod.stat} ${mod.op === 'set' ? '=' : mod.value >= 0 ? '+' : ''}${mod.value}${mod.when === 'carried' ? ' (carried)' : ''}`,
    ),
);
const close = () => emit('close');
const run = (action: () => unknown, closeAfter = true) => {
    action();
    if (closeAfter) close();
};
</script>

<template>
    <BottomSheet :open="item !== null" :title="item?.name ?? ''" @close="close">
        <template v-if="item">
            <div class="flex gap-3">
                <img
                    v-if="item.icon"
                    :src="item.icon"
                    alt=""
                    class="size-14 shrink-0 object-contain"
                />
                <div class="min-w-0 text-sm">
                    <p class="flex flex-wrap gap-1.5">
                        <span
                            v-if="item.category"
                            class="rounded bg-stone-800 px-2 py-0.5 capitalize"
                            >{{ item.category }}</span
                        >
                        <span
                            v-if="item.cost !== null"
                            class="rounded bg-stone-800 px-2 py-0.5"
                            >{{ item.cost }} gold</span
                        >
                        <span
                            v-for="tag in item.tags"
                            :key="tag"
                            class="rounded bg-stone-800 px-2 py-0.5"
                            >{{ tag }}</span
                        >
                        <span
                            v-if="item.required_slots.length"
                            class="rounded bg-stone-800 px-2 py-0.5"
                            >two-handed</span
                        >
                    </p>
                    <p
                        v-if="modifiers.length"
                        class="mt-2 font-semibold text-amber-300 capitalize"
                    >
                        {{ modifiers.join(' · ') }}
                    </p>
                </div>
            </div>
            <p
                v-if="item.restriction"
                class="mt-3 rounded border border-amber-800 bg-amber-950/60 px-3 py-2 text-sm text-amber-200"
            >
                {{ item.restriction }}
            </p>
            <p class="mt-3 text-sm whitespace-pre-wrap text-stone-300">
                {{ item.description || 'No description.' }}
            </p>
            <dl
                v-if="Object.keys(item.properties).length"
                class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm"
            >
                <template
                    v-for="(value, label) in item.properties"
                    :key="label"
                >
                    <dt class="text-stone-400">{{ label }}</dt>
                    <dd>{{ value }}</dd>
                </template>
            </dl>
            <p v-if="item.slots.length" class="mt-3 text-sm text-amber-400">
                Equipped: {{ item.slots.map((slot) => slot.name).join(' + ') }}
            </p>

            <div v-if="actions" class="mt-4 mb-1 space-y-2">
                <button
                    v-if="item.consumable"
                    type="button"
                    class="min-h-12 w-full rounded-md bg-emerald-700 font-bold text-white active:scale-[0.98]"
                    @click="run(() => actions!.itemUse(item!))"
                >
                    Use one ({{ item.quantity }} left)
                </button>
                <template v-if="item.slots.length">
                    <button
                        type="button"
                        class="min-h-12 w-full rounded-md bg-stone-700 font-bold"
                        @click="run(() => actions!.itemUnequip(item!))"
                    >
                        Unequip
                    </button>
                </template>
                <template v-else-if="isEquippable(item)">
                    <button
                        type="button"
                        :disabled="busy"
                        class="min-h-12 w-full rounded-md bg-amber-600 font-bold disabled:opacity-50"
                        @click="run(() => actions!.itemEquip(item!))"
                    >
                        Equip
                    </button>
                </template>
                <div
                    v-if="!item.required_slots.length && fitting.length > 1"
                    class="flex flex-wrap gap-2"
                >
                    <span class="w-full text-xs text-stone-400"
                        >Equip to a specific slot:</span
                    >
                    <button
                        v-for="slot in fitting"
                        :key="slot.id"
                        type="button"
                        class="min-h-11 flex-1 rounded-md border border-stone-700 px-3 text-sm"
                        :class="{
                            'border-amber-500 text-amber-400': item.slots.some(
                                (entry) => entry.id === slot.id,
                            ),
                        }"
                        @click="run(() => actions!.itemEquip(item!, slot.id))"
                    >
                        {{ slot.name }}
                    </button>
                </div>
                <div
                    class="flex items-center gap-3 border-t border-stone-800 pt-3"
                >
                    <span class="flex-1 text-sm text-stone-400">Quantity</span>
                    <HoldButton
                        label="Decrease quantity"
                        :disabled="item.quantity <= 1"
                        @step="actions!.itemDelta(item!, -1)"
                        >−</HoldButton
                    >
                    <strong class="w-8 text-center text-xl tabular-nums">{{
                        item.quantity
                    }}</strong>
                    <HoldButton
                        label="Increase quantity"
                        @step="actions!.itemDelta(item!, 1)"
                        >+</HoldButton
                    >
                </div>
                <button
                    type="button"
                    class="min-h-11 w-full rounded-md border text-sm font-semibold"
                    :class="
                        confirmRemove
                            ? 'border-red-500 bg-red-900 text-white'
                            : 'border-red-900 text-red-300'
                    "
                    @click="
                        confirmRemove
                            ? run(() => actions!.itemRemove(item!))
                            : (confirmRemove = true)
                    "
                >
                    {{
                        confirmRemove
                            ? 'Tap again to remove'
                            : 'Remove from inventory'
                    }}
                </button>
            </div>
        </template>
    </BottomSheet>
</template>
