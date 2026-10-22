<script setup lang="ts">
import { formatModifier, type DerivedStat } from '@/sheet/derive';
import type { SheetActions } from '@/sheet/store';
import type { SheetMode } from '@/sheet/types';
import { computed, ref, watch } from 'vue';
import AmountPad from './AmountPad.vue';
import BottomSheet from './BottomSheet.vue';
import HoldButton from './HoldButton.vue';

const props = defineProps<{
    stat: DerivedStat;
    mode: SheetMode;
    actions: SheetActions | null;
    compact?: boolean;
}>();
const flash = ref(false);
const padOpen = ref(false);
const detailOpen = ref(false);
const editable = computed(
    () => props.actions !== null && props.mode !== 'overlay',
);
const atMin = computed(
    () =>
        props.stat.minimum !== null &&
        props.stat.effective <= props.stat.minimum,
);
const atMax = computed(() => {
    const max = props.stat.maximum ?? props.stat.max_value;
    return max !== null && props.stat.value >= max;
});
const percent = computed(() =>
    props.stat.effective_max
        ? Math.max(
              0,
              Math.min(
                  100,
                  (props.stat.effective / props.stat.effective_max) * 100,
              ),
          )
        : null,
);

watch(
    () => props.stat.effective,
    () => {
        flash.value = false;
        requestAnimationFrame(() => (flash.value = true));
    },
);
</script>

<template>
    <!-- Resource: Body, Mind — current / max with steppers. -->
    <div
        v-if="stat.kind === 'resource'"
        class="stat-tile flex flex-col rounded-md border border-stone-800 bg-stone-950"
        :class="compact ? 'p-1.5' : 'p-2'"
    >
        <p
            class="truncate text-center text-xs font-semibold tracking-wide text-stone-300 uppercase"
        >
            {{ stat.name }}
        </p>
        <div class="flex items-center justify-between gap-1">
            <HoldButton
                v-if="editable"
                :size="compact ? 'sm' : 'md'"
                :label="`Decrease ${stat.name}`"
                :disabled="atMin"
                @step="actions!.statDelta(stat, -1)"
                >−</HoldButton
            >
            <button
                type="button"
                class="min-w-0 flex-1 rounded-md px-1 text-center leading-none font-black text-amber-400 tabular-nums"
                :class="[
                    { 'stat-value-flash': flash },
                    compact ? 'text-2xl' : 'text-3xl',
                ]"
                :disabled="!editable"
                :aria-label="`${stat.name} ${stat.effective} of ${stat.effective_max ?? 'no maximum'}`"
                @click="padOpen = true"
                @animationend="flash = false"
            >
                {{ stat.effective
                }}<small
                    v-if="stat.effective_max !== null"
                    class="text-base font-bold text-stone-400"
                    >/{{ stat.effective_max }}</small
                >
            </button>
            <HoldButton
                v-if="editable"
                :size="compact ? 'sm' : 'md'"
                :label="`Increase ${stat.name}`"
                :disabled="atMax"
                @step="actions!.statDelta(stat, 1)"
                >+</HoldButton
            >
        </div>
        <div
            v-if="percent !== null"
            class="mt-1.5 h-1 overflow-hidden rounded-full bg-stone-800"
            aria-hidden="true"
        >
            <div
                class="h-full rounded-full transition-[width] duration-300"
                :class="percent <= 25 ? 'bg-red-500' : 'bg-amber-500'"
                :style="{ width: `${percent}%` }"
            />
        </div>
        <AmountPad
            v-if="editable"
            :open="padOpen"
            :title="stat.name"
            :current="stat.value"
            :max="stat.max_value"
            :chips="[1, 2, 3, 4]"
            allow-max
            @close="padOpen = false"
            @delta="actions!.statDelta(stat, $event)"
            @set="actions!.statSet(stat, $event)"
            @set-max="actions!.statMax(stat, $event)"
        />
    </div>

    <!-- Currency: Gold — one tap opens quick add/spend. -->
    <button
        v-else-if="stat.kind === 'currency'"
        type="button"
        class="stat-tile flex min-h-11 items-center justify-between gap-2 rounded-md border border-stone-800 bg-stone-950 px-3 text-left"
        :class="compact ? 'py-1' : 'py-2'"
        :disabled="!editable"
        @click="padOpen = true"
    >
        <span
            class="text-xs font-semibold tracking-wide text-stone-300 uppercase"
            >{{ stat.name }}</span
        >
        <strong
            class="rounded-md px-1 text-xl font-black text-amber-400 tabular-nums"
            :class="{ 'stat-value-flash': flash }"
            @animationend="flash = false"
            >{{ stat.effective }}</strong
        >
        <AmountPad
            v-if="editable"
            :open="padOpen"
            :title="stat.name"
            :current="stat.value"
            @close="padOpen = false"
            @delta="actions!.statDelta(stat, $event)"
            @set="actions!.statSet(stat, $event)"
        />
    </button>

    <!-- Attribute: Attack, Defend, Move — derived from the base value plus equipped items. -->
    <button
        v-else
        type="button"
        class="stat-tile flex min-h-11 flex-col items-center justify-center rounded-md border border-stone-800 bg-stone-950 px-1"
        :class="[
            compact ? 'py-1' : 'py-1.5',
            stat.sources.length ? 'border-amber-900' : '',
        ]"
        :aria-label="`${stat.name} ${stat.effective}. Show breakdown`"
        @click="mode !== 'overlay' && (detailOpen = true)"
    >
        <span
            class="max-w-full truncate text-[11px] font-semibold tracking-wide text-stone-300 uppercase"
            >{{ stat.name }}</span
        >
        <strong
            class="rounded-md px-1 text-xl leading-tight font-black text-stone-100 tabular-nums"
            :class="{ 'stat-value-flash': flash }"
            @animationend="flash = false"
            >{{ stat.effective }}</strong
        >
        <BottomSheet
            v-if="mode !== 'overlay'"
            :open="detailOpen"
            :title="stat.name"
            @close="detailOpen = false"
        >
            <p class="text-4xl font-black text-amber-400">
                {{ stat.effective }}
            </p>
            <ul class="mt-3 space-y-1 text-sm text-stone-300">
                <li>Base {{ stat.value }}</li>
                <li
                    v-for="source in stat.sources"
                    :key="source.name + source.op"
                >
                    {{ formatModifier(source) }}
                </li>
                <li v-if="!stat.sources.length" class="text-stone-500">
                    No equipped items change this.
                </li>
            </ul>
            <div v-if="editable" class="mt-4 mb-1 flex items-center gap-3">
                <span class="flex-1 text-sm text-stone-400"
                    >Adjust base value</span
                >
                <HoldButton
                    :label="`Decrease base ${stat.name}`"
                    :disabled="
                        stat.minimum !== null && stat.value <= stat.minimum
                    "
                    @step="actions!.statDelta(stat, -1)"
                    >−</HoldButton
                >
                <strong class="w-8 text-center text-xl">{{
                    stat.value
                }}</strong>
                <HoldButton
                    :label="`Increase base ${stat.name}`"
                    @step="actions!.statDelta(stat, 1)"
                    >+</HoldButton
                >
            </div>
        </BottomSheet>
    </button>
</template>
