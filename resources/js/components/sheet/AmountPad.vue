<script setup lang="ts">
import { ref, watch } from 'vue';
import BottomSheet from './BottomSheet.vue';

/** Quick number entry: chip deltas for the common cases, a field for anything else. */
const props = defineProps<{
    open: boolean;
    title: string;
    current: number;
    max?: number | null;
    chips?: number[];
    allowMax?: boolean;
}>();
const emit = defineEmits<{
    close: [];
    delta: [number];
    set: [number];
    setMax: [number];
}>();
const amount = ref<number | null>(null);
const maxValue = ref<number | null>(null);

watch(
    () => props.open,
    (open) => {
        if (open) {
            amount.value = null;
            maxValue.value = props.max ?? null;
        }
    },
);

const apply = (sign: 1 | -1) => {
    if (!amount.value) return;
    emit('delta', sign * Math.abs(Math.floor(amount.value)));
    emit('close');
};
</script>

<template>
    <BottomSheet :open="open" :title="title" @close="emit('close')">
        <p class="text-sm text-stone-400">
            Current:
            <strong class="text-2xl text-amber-400">{{ current }}</strong>
            <span v-if="max !== null && max !== undefined"> / {{ max }}</span>
        </p>
        <div class="mt-3 grid grid-cols-4 gap-2">
            <button
                v-for="chip in chips ?? [1, 5, 10, 25]"
                :key="`minus-${chip}`"
                type="button"
                class="min-h-11 rounded-md bg-stone-800 font-bold text-red-300 active:scale-95"
                @click="emit('delta', -chip)"
            >
                −{{ chip }}
            </button>
            <button
                v-for="chip in chips ?? [1, 5, 10, 25]"
                :key="`plus-${chip}`"
                type="button"
                class="min-h-11 rounded-md bg-stone-800 font-bold text-emerald-300 active:scale-95"
                @click="emit('delta', chip)"
            >
                +{{ chip }}
            </button>
        </div>
        <form class="mt-4 flex gap-2" @submit.prevent="apply(1)">
            <input
                v-model.number="amount"
                type="number"
                inputmode="numeric"
                min="1"
                placeholder="Amount"
                aria-label="Amount"
                class="min-h-11 w-full min-w-0 flex-1 rounded-md border px-3 text-lg"
            />
            <button
                type="button"
                class="min-h-11 rounded-md bg-stone-700 px-4 font-bold"
                @click="apply(-1)"
            >
                Spend
            </button>
            <button class="min-h-11 rounded-md bg-amber-600 px-4 font-bold">
                Add
            </button>
        </form>
        <form
            v-if="allowMax"
            class="mt-4 flex items-center gap-2 border-t border-stone-800 pt-4"
            @submit.prevent="
                maxValue !== null && (emit('setMax', maxValue), emit('close'))
            "
        >
            <label class="flex-1 text-sm text-stone-400">
                Maximum
                <input
                    v-model.number="maxValue"
                    type="number"
                    inputmode="numeric"
                    class="mt-1 min-h-11 w-full rounded-md border px-3 text-lg"
                />
            </label>
            <button
                class="mt-5 min-h-11 rounded-md bg-stone-700 px-4 font-bold"
            >
                Set max
            </button>
        </form>
        <button
            type="button"
            class="mt-3 mb-1 min-h-11 w-full rounded-md border border-stone-700 text-sm text-stone-300"
            @click="amount && (emit('set', Math.floor(amount)), emit('close'))"
        >
            Set to exactly this amount
        </button>
    </BottomSheet>
</template>
