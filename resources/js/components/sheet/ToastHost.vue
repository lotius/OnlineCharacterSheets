<script setup lang="ts">
import { dismissToast, toasts } from '@/sheet/toasts';
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 pb-[max(1rem,env(safe-area-inset-bottom))]"
        aria-live="polite"
    >
        <TransitionGroup name="toast">
            <button
                v-for="entry in toasts"
                :key="entry.id"
                type="button"
                class="pointer-events-auto w-full max-w-md rounded-md border px-4 py-3 text-left text-sm shadow-2xl"
                :class="{
                    'border-red-700 bg-red-950 text-red-100':
                        entry.tone === 'error',
                    'border-amber-700 bg-amber-950 text-amber-100':
                        entry.tone === 'warning',
                    'border-stone-700 bg-stone-900 text-stone-100':
                        entry.tone === 'info',
                }"
                @click="dismissToast(entry.id)"
            >
                {{ entry.message }}
            </button>
        </TransitionGroup>
    </div>
</template>
