<script setup lang="ts">
import { isCollapsed, toggleCollapsed } from '@/sheet/prefs';
import { computed } from 'vue';

const props = defineProps<{
    title: string;
    count?: number | null;
    collapseKey: string;
    defaultCollapsed: boolean;
    collapsible: boolean;
    addLabel?: string | null;
}>();
const emit = defineEmits<{ add: [] }>();
const collapsed = computed(
    () =>
        props.collapsible &&
        isCollapsed(props.collapseKey, props.defaultCollapsed),
);
</script>

<template>
    <section class="sheet-block">
        <div class="flex items-center gap-1">
            <button
                v-if="collapsible"
                type="button"
                class="flex min-h-11 min-w-0 flex-1 items-center gap-2 text-left"
                :aria-expanded="!collapsed"
                @click="toggleCollapsed(collapseKey, defaultCollapsed)"
            >
                <svg
                    aria-hidden="true"
                    viewBox="0 0 24 24"
                    class="size-4 shrink-0 text-amber-500 transition-transform"
                    :class="{ 'rotate-90': !collapsed }"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="m9 18 6-6-6-6" />
                </svg>
                <h3
                    class="truncate text-sm font-bold tracking-wider text-amber-400 uppercase"
                >
                    {{ title }}
                </h3>
                <span
                    v-if="count"
                    class="rounded-full bg-stone-800 px-2 text-xs text-stone-300"
                    >{{ count }}</span
                >
            </button>
            <h3
                v-else
                class="min-w-0 flex-1 truncate py-1 text-sm font-bold tracking-wider text-amber-400 uppercase"
            >
                {{ title }}
            </h3>
            <button
                v-if="addLabel"
                type="button"
                class="flex size-11 shrink-0 items-center justify-center rounded-md text-2xl text-amber-400 hover:bg-stone-800"
                :aria-label="addLabel"
                @click="emit('add')"
            >
                +
            </button>
        </div>
        <div v-if="!collapsed" class="pb-2">
            <slot />
        </div>
    </section>
</template>
