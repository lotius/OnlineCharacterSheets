<script setup lang="ts">
import type { SheetActions } from '@/sheet/store';
import type { Ability, Block, Character, SheetMode } from '@/sheet/types';
import { computed } from 'vue';

/** Tap a spell/skill to mark it used (the most common action); the ⓘ button shows details. */
const props = defineProps<{
    block: Block;
    character: Character;
    mode: SheetMode;
    actions: SheetActions | null;
    compact?: boolean;
}>();
const emit = defineEmits<{ details: [Ability] }>();
const kind = computed(() => props.block.kind ?? 'spells');
const groups = computed(() => {
    const map = new Map<string, Ability[]>();
    for (const ability of props.character[kind.value]) {
        const key = ability.group ?? '';
        map.set(key, [...(map.get(key) ?? []), ability]);
    }
    return [...map.entries()];
});
</script>

<template>
    <div v-if="character[kind].length" class="space-y-2">
        <div v-for="[group, abilities] in groups" :key="group">
            <p
                v-if="group"
                class="mb-1 text-[11px] tracking-wider text-stone-400 uppercase"
            >
                {{ group }}
            </p>
            <div
                class="grid grid-cols-[repeat(auto-fill,minmax(9rem,1fr))] gap-1.5"
            >
                <div
                    v-for="ability in abilities"
                    :key="ability.id"
                    class="flex min-h-11 items-center rounded-md border transition"
                    :class="
                        ability.is_used
                            ? 'border-stone-800 bg-stone-950/50 text-stone-500'
                            : 'border-amber-900/70 bg-stone-950'
                    "
                >
                    <button
                        type="button"
                        class="flex min-h-11 min-w-0 flex-1 items-center gap-2 px-2 text-left"
                        :disabled="!actions || mode === 'overlay'"
                        :aria-pressed="ability.is_used"
                        :aria-label="`${ability.name}${ability.is_used ? ' (used)' : ''}. Tap to mark ${ability.is_used ? 'available' : 'used'}`"
                        @click="actions?.abilityToggle(kind, ability)"
                    >
                        <img
                            v-if="ability.icon"
                            :src="ability.icon"
                            alt=""
                            class="size-6 shrink-0 object-contain"
                            :class="{ 'opacity-40 grayscale': ability.is_used }"
                        />
                        <span
                            class="truncate text-sm font-semibold"
                            :class="{ 'line-through': ability.is_used }"
                            >{{ ability.name }}</span
                        >
                    </button>
                    <button
                        v-if="mode !== 'overlay'"
                        type="button"
                        class="flex size-10 shrink-0 items-center justify-center text-stone-400"
                        :aria-label="`About ${ability.name}`"
                        @click="emit('details', ability)"
                    >
                        ⓘ
                    </button>
                </div>
            </div>
        </div>
    </div>
    <p v-else-if="!compact" class="px-1 py-2 text-sm text-stone-500">
        None yet.
    </p>
</template>
