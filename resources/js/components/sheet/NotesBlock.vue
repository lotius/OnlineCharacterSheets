<script setup lang="ts">
import type { SheetActions } from '@/sheet/store';
import type { Character, SheetMode } from '@/sheet/types';
import { onUnmounted, ref, watch } from 'vue';

/** Notes save automatically a moment after typing stops. */
const props = defineProps<{
    character: Character;
    mode: SheetMode;
    actions: SheetActions | null;
    saving: boolean;
}>();
const content = ref(props.character.note?.content ?? '');
const isPrivate = ref(props.character.note?.is_private ?? false);
const focused = ref(false);
let timer: number | undefined;

// Accept remote edits unless this person is typing.
watch(
    () => props.character.note,
    (note) => {
        if (focused.value) return;
        content.value = note?.content ?? '';
        isPrivate.value = note?.is_private ?? false;
    },
);

const save = () => {
    window.clearTimeout(timer);
    if (
        content.value === (props.character.note?.content ?? '') &&
        isPrivate.value === (props.character.note?.is_private ?? false)
    )
        return;
    void props.actions?.noteSave(content.value, isPrivate.value);
};
const schedule = () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(save, 900);
};
onUnmounted(save);
</script>

<template>
    <div v-if="actions && mode !== 'overlay'">
        <textarea
            v-model="content"
            rows="4"
            maxlength="20000"
            aria-label="Notes"
            placeholder="Quest notes, clues, reminders…"
            class="w-full rounded-md border p-3 text-sm"
            @input="schedule"
            @focus="focused = true"
            @blur="((focused = false), save())"
        />
        <div class="flex items-center justify-between text-xs text-stone-400">
            <label class="flex min-h-11 items-center gap-2">
                <input
                    v-model="isPrivate"
                    type="checkbox"
                    class="size-5"
                    @change="save"
                />
                Private (only you and the DM)
            </label>
            <span aria-live="polite">{{ saving ? 'Saving…' : 'Saved' }}</span>
        </div>
    </div>
    <p
        v-else-if="character.note?.content"
        class="text-sm whitespace-pre-wrap text-stone-300"
    >
        {{ character.note.content }}
    </p>
    <p v-else class="px-1 py-2 text-sm text-stone-500">No notes.</p>
</template>
