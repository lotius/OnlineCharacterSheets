<script setup lang="ts">
import type { DerivedStat } from '@/sheet/derive';
import type { SheetActions } from '@/sheet/store';
import type { Block, SheetMode } from '@/sheet/types';
import { computed } from 'vue';
import StatTile from './StatTile.vue';

const props = defineProps<{
    block: Block;
    stats: DerivedStat[];
    mode: SheetMode;
    actions: SheetActions | null;
    compact?: boolean;
}>();
const shown = computed(() =>
    props.block.keys?.length
        ? props.stats.filter((stat) => props.block.keys!.includes(stat.key))
        : props.stats,
);
const resources = computed(() =>
    shown.value.filter((stat) => stat.kind === 'resource'),
);
const attributes = computed(() =>
    shown.value.filter((stat) => stat.kind === 'attribute'),
);
const currencies = computed(() =>
    shown.value.filter((stat) => stat.kind === 'currency'),
);
</script>

<template>
    <div class="space-y-2">
        <div v-if="resources.length" class="grid grid-cols-2 gap-2">
            <StatTile
                v-for="stat in resources"
                :key="stat.id"
                :stat="stat"
                :mode="mode"
                :actions="actions"
                :compact="compact"
            />
        </div>
        <div
            v-if="attributes.length || currencies.length"
            class="grid gap-2"
            :style="{
                gridTemplateColumns: `repeat(${attributes.length}, minmax(0, 1fr)) ${currencies.length ? 'minmax(6.5rem, 1.4fr)' : ''}`,
            }"
        >
            <StatTile
                v-for="stat in attributes"
                :key="stat.id"
                :stat="stat"
                :mode="mode"
                :actions="actions"
                :compact="compact"
            />
            <StatTile
                v-for="stat in currencies"
                :key="stat.id"
                :stat="stat"
                :mode="mode"
                :actions="actions"
                :compact="compact"
            />
        </div>
    </div>
</template>
