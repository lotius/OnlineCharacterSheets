<script setup lang="ts">
import { nextTick, onUnmounted, ref, watch } from 'vue';

/** Phone-friendly dialog: slides up from the bottom on small screens, centered on larger ones. */
const props = defineProps<{ open: boolean; title: string; wide?: boolean }>();
const emit = defineEmits<{ close: [] }>();
const panel = ref<HTMLElement | null>(null);

const onKey = (event: KeyboardEvent) => {
    if (event.key === 'Escape') emit('close');
};

watch(
    () => props.open,
    async (open) => {
        if (open) {
            document.addEventListener('keydown', onKey);
            await nextTick();
            panel.value?.focus();
        } else {
            document.removeEventListener('keydown', onKey);
        }
    },
    { immediate: true },
);
onUnmounted(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <Transition name="sheet">
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center sm:p-4"
                @click.self="emit('close')"
            >
                <section
                    ref="panel"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title"
                    tabindex="-1"
                    class="sheet-panel flex max-h-[88vh] w-full flex-col border border-amber-800/50 bg-stone-900 text-stone-100 shadow-2xl outline-none sm:rounded-lg"
                    :class="wide ? 'sm:max-w-2xl' : 'sm:max-w-md'"
                >
                    <div
                        class="mx-auto mt-2 h-1 w-10 shrink-0 rounded-full bg-stone-700 sm:hidden"
                        aria-hidden="true"
                    />
                    <div
                        class="flex items-center justify-between gap-3 px-4 py-1"
                    >
                        <h2
                            class="hq-rule-heading min-w-0 flex-1 text-lg font-bold"
                        >
                            {{ title }}
                        </h2>
                        <button
                            type="button"
                            class="flex size-11 items-center justify-center text-2xl text-stone-400 hover:text-stone-100"
                            aria-label="Close"
                            @click="emit('close')"
                        >
                            ×
                        </button>
                    </div>
                    <div
                        class="overflow-y-auto px-4 pb-[max(1rem,env(safe-area-inset-bottom))]"
                    >
                        <slot />
                    </div>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>
