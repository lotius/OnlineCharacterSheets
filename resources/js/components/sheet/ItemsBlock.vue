<script setup lang="ts">
import { isEquippable, itemsInCategory } from '@/sheet/derive';
import type { SheetActions } from '@/sheet/store';
import type { Block, Character, Item, SheetMode } from '@/sheet/types';
import { computed } from 'vue';

const props = defineProps<{
    block: Block;
    character: Character;
    mode: SheetMode;
    actions: SheetActions | null;
    busy: Set<string>;
    compact?: boolean;
}>();
const emit = defineEmits<{ details: [Item] }>();
const items = computed(() =>
    itemsInCategory(
        props.character.items,
        props.block.category,
        props.block.exclude_categories ?? [],
    ),
);
</script>

<template>
    <ul v-if="items.length" class="space-y-1">
        <li
            v-for="item in items"
            :key="item.id"
            class="flex items-center gap-2 rounded-md bg-stone-950"
            :class="compact ? 'min-h-11 px-1.5' : 'min-h-12 px-2'"
        >
            <button
                type="button"
                class="flex min-h-11 min-w-0 flex-1 items-center gap-2 text-left"
                :disabled="mode === 'overlay'"
                @click="emit('details', item)"
            >
                <img
                    v-if="item.icon"
                    :src="item.icon"
                    alt=""
                    class="shrink-0 object-contain"
                    :class="compact ? 'size-7' : 'size-9'"
                />
                <span class="min-w-0">
                    <span class="flex items-center gap-1.5">
                        <span class="truncate font-semibold">{{
                            item.name
                        }}</span>
                        <span
                            v-if="item.quantity > 1"
                            class="shrink-0 rounded bg-stone-800 px-1.5 text-xs font-bold text-amber-300 tabular-nums"
                            >×{{ item.quantity }}</span
                        >
                        <span
                            v-if="item.restriction"
                            class="shrink-0 text-amber-400"
                            :title="item.restriction"
                            aria-label="Class restriction"
                            >⚠</span
                        >
                    </span>
                    <span
                        v-if="
                            item.slots.length || (!compact && item.tags.length)
                        "
                        class="block truncate text-xs text-stone-400"
                    >
                        <span v-if="item.slots.length" class="text-amber-500">{{
                            item.slots.map((slot) => slot.name).join(' + ')
                        }}</span>
                        <template v-if="!compact && item.tags.length"
                            ><template v-if="item.slots.length"> · </template
                            >{{ item.tags.join(', ') }}</template
                        >
                    </span>
                </span>
            </button>
            <template v-if="actions && mode !== 'overlay'">
                <button
                    v-if="item.consumable"
                    type="button"
                    class="min-h-10 shrink-0 rounded-md bg-emerald-800 px-3 text-sm font-bold text-emerald-50 active:scale-95"
                    :aria-label="`Use ${item.name}`"
                    @click="actions.itemUse(item)"
                >
                    Use
                </button>
                <button
                    v-else-if="isEquippable(item) && !item.slots.length"
                    type="button"
                    class="min-h-10 shrink-0 rounded-md border border-amber-800 px-3 text-sm font-semibold text-amber-400 active:scale-95 disabled:opacity-50"
                    :disabled="busy.has(`item:${item.id}`)"
                    :aria-label="`Equip ${item.name}`"
                    @click="actions.itemEquip(item)"
                >
                    {{ busy.has(`item:${item.id}`) ? '…' : 'Equip' }}
                </button>
            </template>
        </li>
    </ul>
    <p v-else-if="!compact" class="px-1 py-2 text-sm text-stone-500">
        Nothing here yet.
    </p>
</template>
