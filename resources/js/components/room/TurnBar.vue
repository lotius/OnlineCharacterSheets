<script setup lang="ts">
import BottomSheet from '@/components/sheet/BottomSheet.vue';
import { toast } from '@/sheet/toasts';
import type { Character } from '@/sheet/types';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

/** Sticky turn tracker. The DM's Previous/Next update instantly and reconcile with the server. */
const props = defineProps<{
    gameId: number;
    characters: Character[];
    currentId: number | null;
    canManage: boolean;
    myUserId: number | null;
}>();
const optimistic = ref<number | null | undefined>(undefined);
const picking = ref(false);
watch(
    () => props.currentId,
    () => (optimistic.value = undefined),
);

const current = computed(() => {
    const id =
        optimistic.value === undefined ? props.currentId : optimistic.value;
    return props.characters.find((character) => character.id === id) ?? null;
});
const mine = computed(
    () =>
        current.value !== null &&
        props.myUserId !== null &&
        current.value.assigned_user_id === props.myUserId,
);

function predict(
    direction: 'next' | 'previous' | 'first' | 'select',
    characterId: number | null,
) {
    const ids = props.characters.map((character) => character.id);
    if (direction === 'select' || !ids.length) return characterId;
    if (direction === 'first') return ids[0];
    const index = current.value ? ids.indexOf(current.value.id) : -1;
    if (index === -1)
        return direction === 'next' ? ids[0] : ids[ids.length - 1];
    return ids[
        (index + (direction === 'next' ? 1 : -1) + ids.length) % ids.length
    ];
}

function change(
    direction: 'next' | 'previous' | 'first' | 'select',
    characterId: number | null = null,
) {
    optimistic.value = predict(direction, characterId);
    picking.value = false;
    router.patch(
        `/games/${props.gameId}/turn`,
        { direction, character_id: characterId },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['game'],
            onError: (errors) => {
                optimistic.value = undefined;
                toast(
                    Object.values(errors)[0] ??
                        'The turn could not be changed.',
                    'error',
                );
            },
        },
    );
}
</script>

<template>
    <div
        class="flex items-center gap-2 rounded-lg border border-amber-800/70 bg-stone-900 p-1.5 shadow-2xl"
        :class="{ 'turn-glow': mine }"
        aria-label="Turn tracker"
    >
        <button
            v-if="canManage"
            type="button"
            class="flex size-12 shrink-0 items-center justify-center rounded-md bg-stone-800 text-xl active:scale-95 disabled:opacity-40"
            aria-label="Previous turn"
            :disabled="!characters.length"
            @click="change('previous')"
        >
            <svg
                aria-hidden="true"
                viewBox="0 0 24 24"
                class="size-5"
                fill="none"
                stroke="currentColor"
                stroke-width="3"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="m15 18-6-6 6-6" />
            </svg>
        </button>
        <button
            type="button"
            class="flex min-h-12 min-w-0 flex-1 items-center gap-2 rounded-md px-2 text-left"
            :disabled="!canManage"
            aria-live="polite"
            @click="picking = true"
        >
            <img
                v-if="current?.portrait"
                :src="current.portrait"
                alt=""
                class="size-9 shrink-0 rounded-full border border-amber-700 object-cover"
            />
            <span class="min-w-0">
                <span
                    class="block text-[10px] tracking-widest text-stone-400 uppercase"
                    >{{ mine ? 'Your turn' : 'Current turn' }}</span
                >
                <span
                    class="block truncate text-lg leading-tight font-black"
                    :class="current ? 'text-amber-300' : 'text-stone-500'"
                    >{{ current?.name ?? 'Not started' }}</span
                >
            </span>
        </button>
        <button
            v-if="canManage"
            type="button"
            class="flex min-h-12 shrink-0 items-center justify-center rounded-md bg-amber-600 px-4 text-lg font-black active:scale-95 disabled:opacity-40"
            aria-label="Next turn"
            :disabled="!characters.length"
            @click="change('next')"
        >
            Next
            <svg
                aria-hidden="true"
                viewBox="0 0 24 24"
                class="size-5"
                fill="none"
                stroke="currentColor"
                stroke-width="3"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="m9 18 6-6-6-6" />
            </svg>
        </button>

        <BottomSheet
            v-if="canManage"
            :open="picking"
            title="Whose turn?"
            @close="picking = false"
        >
            <ul class="divide-y divide-stone-800">
                <li v-for="character in characters" :key="character.id">
                    <button
                        type="button"
                        class="flex min-h-12 w-full items-center gap-3 py-2 text-left"
                        :class="{
                            'text-amber-400': current?.id === character.id,
                        }"
                        @click="change('select', character.id)"
                    >
                        <img
                            v-if="character.portrait"
                            :src="character.portrait"
                            alt=""
                            class="size-9 rounded-full object-cover"
                        />
                        <span class="flex-1 font-semibold">{{
                            character.name
                        }}</span>
                        <span class="text-xs text-stone-400">{{
                            character.assigned_user_name ?? ''
                        }}</span>
                    </button>
                </li>
            </ul>
            <button
                type="button"
                class="mt-2 mb-1 min-h-11 w-full rounded-md border border-stone-700 text-sm"
                @click="change('first')"
            >
                Restart from first
            </button>
        </BottomSheet>
    </div>
</template>
