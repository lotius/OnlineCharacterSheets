<script setup lang="ts">
import SheetRenderer from '@/components/sheet/SheetRenderer.vue';
import type { SheetActions } from '@/sheet/store';
import type { Character, SystemContext } from '@/sheet/types';
import { Link } from '@inertiajs/vue3';

defineProps<{
    gameId: number;
    character: Character;
    system: SystemContext;
    isCurrent: boolean;
    isMine: boolean;
    actions: SheetActions | null;
    busy: Set<string>;
    position: number;
    sortable: boolean;
    dragging: boolean;
}>();
const emit = defineEmits<{ grab: [PointerEvent]; nudge: [-1 | 1] }>();
</script>

<template>
    <article
        :data-sort-id="character.id"
        class="rounded-lg border-2 p-2.5 transition"
        :class="[
            isCurrent ? 'turn-card' : 'border-stone-800 bg-stone-900',
            {
                'relative z-10 scale-[1.02] opacity-80 shadow-2xl ring-2 ring-amber-500':
                    dragging,
            },
        ]"
    >
        <!-- Drawn inside the card so the sticky turn bar can never cover it. -->
        <div
            v-if="isCurrent"
            class="turn-banner -mx-2.5 -mt-2.5 mb-2 flex items-center gap-1.5 rounded-t-[4px] px-3 py-1 text-xs font-black tracking-wider uppercase"
        >
            <svg
                aria-hidden="true"
                viewBox="0 0 24 24"
                class="size-3.5"
                fill="currentColor"
            >
                <path d="M8 5v14l11-7z" />
            </svg>
            {{ isMine ? 'Your turn' : 'Current turn' }}
        </div>
        <header class="mb-1.5 flex items-center gap-2">
            <button
                v-if="sortable"
                type="button"
                class="-ml-1 flex h-11 w-8 shrink-0 cursor-grab touch-none flex-col items-center justify-center rounded-md text-stone-500 hover:bg-stone-800 hover:text-amber-400 active:cursor-grabbing"
                :aria-label="`Turn ${position} of ${character.name}. Drag, or use arrow keys, to change turn order`"
                title="Drag to change turn order"
                @pointerdown="emit('grab', $event)"
                @keydown.up.prevent="emit('nudge', -1)"
                @keydown.left.prevent="emit('nudge', -1)"
                @keydown.down.prevent="emit('nudge', 1)"
                @keydown.right.prevent="emit('nudge', 1)"
            >
                <svg
                    aria-hidden="true"
                    viewBox="0 0 12 20"
                    class="h-5 w-3"
                    fill="currentColor"
                >
                    <circle cx="3" cy="4" r="1.6" />
                    <circle cx="9" cy="4" r="1.6" />
                    <circle cx="3" cy="10" r="1.6" />
                    <circle cx="9" cy="10" r="1.6" />
                    <circle cx="3" cy="16" r="1.6" />
                    <circle cx="9" cy="16" r="1.6" />
                </svg>
            </button>
            <span
                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-stone-800 text-xs font-black text-stone-300 tabular-nums"
                :title="`Turn order ${position}`"
                >{{ position }}</span
            >
            <img
                v-if="character.portrait"
                :src="character.portrait"
                alt=""
                class="size-10 shrink-0 rounded-full border border-amber-800 object-cover"
            />
            <div class="min-w-0 flex-1">
                <h3
                    class="flex items-center gap-2 truncate text-lg leading-tight font-black"
                >
                    {{ character.name }}
                </h3>
                <p class="truncate text-xs text-stone-400">
                    {{ character.class_name || 'Unclassed' }} ·
                    {{ character.assigned_user_name || 'Unassigned' }}
                </p>
            </div>
            <Link
                :href="`/games/${gameId}/characters/${character.id}`"
                class="flex min-h-11 shrink-0 items-center rounded-md border border-stone-700 px-3 text-sm font-semibold text-amber-400"
                :aria-label="`Open ${character.name}'s full sheet`"
            >
                Sheet
            </Link>
        </header>
        <SheetRenderer
            :character="character"
            :system="system"
            :blocks="system.layouts.card"
            mode="card"
            layout-name="card"
            :actions="actions"
            :busy="busy"
        />
    </article>
</template>
