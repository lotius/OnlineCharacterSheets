<script setup lang="ts">
import { onUnmounted } from 'vue';

/** A 44px+ touch button that fires once on press and repeats (accelerating) while held. */
const props = defineProps<{
    label: string;
    disabled?: boolean;
    size?: 'md' | 'sm';
}>();
const emit = defineEmits<{ step: [] }>();
let delay: number | undefined;
let repeat: number | undefined;

const stop = () => {
    window.clearTimeout(delay);
    window.clearTimeout(repeat);
};
const tick = (interval: number) => {
    repeat = window.setTimeout(() => {
        if (props.disabled) return stop();
        emit('step');
        tick(Math.max(60, interval * 0.85));
    }, interval);
};
const start = (event: PointerEvent) => {
    if (props.disabled || event.button !== 0) return;
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    emit('step');
    delay = window.setTimeout(() => tick(140), 420);
};
// Keyboard activation (Enter/Space) arrives as a click with detail 0.
const keyboard = (event: MouseEvent) => {
    if (event.detail === 0 && !props.disabled) emit('step');
};
onUnmounted(stop);
</script>

<template>
    <button
        type="button"
        :aria-label="label"
        :disabled="disabled"
        class="flex shrink-0 touch-none items-center justify-center rounded-md bg-stone-800 text-xl font-bold text-stone-100 transition select-none enabled:active:scale-90 enabled:active:bg-amber-600 enabled:active:text-stone-950 disabled:opacity-30"
        :class="size === 'sm' ? 'size-10' : 'size-11'"
        @pointerdown="start"
        @pointerup="stop"
        @pointercancel="stop"
        @lostpointercapture="stop"
        @contextmenu.prevent
        @click="keyboard"
    >
        <slot />
    </button>
</template>
