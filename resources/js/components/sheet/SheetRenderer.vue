<script setup lang="ts">
import { deriveStats, itemsInCategory } from '@/sheet/derive';
import { prefs } from '@/sheet/prefs';
import type { SheetActions } from '@/sheet/store';
import type {
    Ability,
    Block,
    Character,
    SheetMode,
    SystemContext,
} from '@/sheet/types';
import { computed, ref } from 'vue';
import AbilitiesBlock from './AbilitiesBlock.vue';
import BlockFrame from './BlockFrame.vue';
import BottomSheet from './BottomSheet.vue';
import CatalogPicker from './CatalogPicker.vue';
import EquipmentBlock from './EquipmentBlock.vue';
import ItemSheet from './ItemSheet.vue';
import ItemsBlock from './ItemsBlock.vue';
import NotesBlock from './NotesBlock.vue';
import StatsBlock from './StatsBlock.vue';

/**
 * Renders a layout (ordered list of blocks) for one character. The same renderer drives the phone
 * sheet ("sheet"), compact room cards ("card") and hero cards inside stream overlays ("overlay").
 */
const props = defineProps<{
    character: Character;
    system: SystemContext;
    blocks: Block[];
    mode: SheetMode;
    layoutName: string;
    actions: SheetActions | null;
    busy?: Set<string>;
}>();

const compact = computed(
    () => props.mode !== 'sheet' || prefs.density === 'compact',
);
const editable = computed(
    () =>
        props.mode !== 'overlay' &&
        props.actions !== null &&
        props.character.can_update,
);
const actions = computed(() => (editable.value ? props.actions : null));
const busy = computed(() => props.busy ?? new Set<string>());
const stats = computed(() => deriveStats(props.character));
const isEmpty = (block: Block) =>
    (block.type === 'abilities' &&
        !props.character[block.kind ?? 'spells'].length) ||
    (block.type === 'items' &&
        !itemsInCategory(
            props.character.items,
            block.category,
            block.exclude_categories ?? [],
        ).length);
const classHides = (block: Block) => {
    const hidden = props.character.hidden_sections ?? [];
    const keys = [
        block.id,
        block.type === 'abilities' ? (block.kind ?? 'spells') : null,
        block.type === 'items' ? block.category : null,
    ];
    return keys.some((key) => key && hidden.includes(key)) && isEmpty(block);
};
const visible = computed(() =>
    props.blocks.filter(
        (block) =>
            !classHides(block) &&
            (props.mode === 'overlay' ||
                !prefs.hidden[`${props.layoutName}:${block.id}`]),
    ),
);

const itemId = ref<number | null>(null);
const selectedItem = computed(
    () =>
        props.character.items.find((item) => item.id === itemId.value) ?? null,
);
const ability = ref<{ kind: 'spells' | 'skills'; id: number } | null>(null);
const selectedAbility = computed<Ability | null>(() =>
    ability.value
        ? (props.character[ability.value.kind].find(
              (entry) => entry.id === ability.value!.id,
          ) ?? null)
        : null,
);
const picker = ref<{
    kind: 'items' | 'spells' | 'skills';
    category: string | null;
} | null>(null);

const categoryName = (key: string | null | undefined) =>
    props.system.item_categories.find((category) => category.key === key)
        ?.name ?? key;
const title = (block: Block) =>
    block.title ??
    {
        stats: 'Stats',
        equipment: 'Equipment',
        items: block.category
            ? (categoryName(block.category) ?? 'Items')
            : 'Inventory',
        abilities: block.kind === 'skills' ? 'Skills' : 'Spells',
        notes: 'Notes',
    }[block.type];
const count = (block: Block) => {
    if (block.type === 'items')
        return itemsInCategory(
            props.character.items,
            block.category,
            block.exclude_categories ?? [],
        ).reduce((sum, item) => sum + item.quantity, 0);
    if (block.type === 'abilities')
        return props.character[block.kind ?? 'spells'].filter(
            (entry) => !entry.is_used,
        ).length;
    return null;
};
const addLabel = (block: Block) => {
    if (!actions.value || !props.system.catalog) return null;
    if (block.type === 'items') return `Add to ${title(block)}`;
    if (block.type === 'abilities')
        return `Add ${block.kind === 'skills' ? 'skill' : 'spell'}`;
    return null;
};
const openPicker = (block: Block) => {
    picker.value =
        block.type === 'items'
            ? { kind: 'items', category: block.category ?? null }
            : { kind: block.kind ?? 'spells', category: null };
};
const owned = computed(() =>
    picker.value && picker.value.kind !== 'items'
        ? props.character[picker.value.kind].map(
              (entry) => entry.definition_id ?? 0,
          )
        : [],
);
const pick = ({
    definitionId,
    quantity,
}: {
    definitionId: number;
    quantity: number;
}) => {
    if (!picker.value || !actions.value) return;
    if (picker.value.kind === 'items')
        void actions.value.itemAdd({
            item_definition_id: definitionId,
            quantity,
        });
    else void actions.value.abilityAdd(picker.value.kind, definitionId);
};
</script>

<template>
    <div class="sheet-renderer" :class="compact ? 'space-y-1' : 'space-y-2'">
        <template v-for="block in visible" :key="block.id">
            <StatsBlock
                v-if="block.type === 'stats' && !block.title"
                :block="block"
                :stats="stats"
                :mode="mode"
                :actions="actions"
                :compact="compact"
            />
            <BlockFrame
                v-else
                :title="title(block)"
                :count="count(block)"
                :collapse-key="`${layoutName}:${block.id}`"
                :default-collapsed="block.collapsed"
                :collapsible="mode !== 'overlay'"
                :add-label="addLabel(block)"
                @add="openPicker(block)"
            >
                <StatsBlock
                    v-if="block.type === 'stats'"
                    :block="block"
                    :stats="stats"
                    :mode="mode"
                    :actions="actions"
                    :compact="compact"
                />
                <EquipmentBlock
                    v-else-if="block.type === 'equipment'"
                    :character="character"
                    :system="system"
                    :mode="mode"
                    :actions="actions"
                    :compact="compact"
                    @details="itemId = $event.id"
                />
                <ItemsBlock
                    v-else-if="block.type === 'items'"
                    :block="block"
                    :character="character"
                    :mode="mode"
                    :actions="actions"
                    :busy="busy"
                    :compact="compact"
                    @details="itemId = $event.id"
                />
                <AbilitiesBlock
                    v-else-if="block.type === 'abilities'"
                    :block="block"
                    :character="character"
                    :mode="mode"
                    :actions="actions"
                    :compact="compact"
                    @details="
                        ability = {
                            kind: block.kind ?? 'spells',
                            id: $event.id,
                        }
                    "
                />
                <NotesBlock
                    v-else-if="block.type === 'notes'"
                    :character="character"
                    :mode="mode"
                    :actions="actions"
                    :saving="busy.has('note')"
                />
            </BlockFrame>
        </template>

        <template v-if="mode !== 'overlay'">
            <ItemSheet
                :item="selectedItem"
                :slots="system.slots"
                :actions="actions"
                :busy="
                    selectedItem ? busy.has(`item:${selectedItem.id}`) : false
                "
                @close="itemId = null"
            />
            <BottomSheet
                :open="selectedAbility !== null"
                :title="selectedAbility?.name ?? ''"
                @close="ability = null"
            >
                <template v-if="selectedAbility && ability">
                    <p
                        v-if="selectedAbility.group"
                        class="text-xs tracking-wider text-stone-400 uppercase"
                    >
                        {{ selectedAbility.group }}
                    </p>
                    <p class="mt-2 text-sm whitespace-pre-wrap text-stone-300">
                        {{ selectedAbility.description || 'No description.' }}
                    </p>
                    <div v-if="actions" class="mt-4 mb-1 space-y-2">
                        <button
                            type="button"
                            class="min-h-12 w-full rounded-md bg-amber-600 font-bold"
                            @click="
                                (actions.abilityToggle(
                                    ability.kind,
                                    selectedAbility,
                                ),
                                (ability = null))
                            "
                        >
                            {{
                                selectedAbility.is_used
                                    ? 'Mark available'
                                    : 'Mark used'
                            }}
                        </button>
                        <button
                            type="button"
                            class="min-h-11 w-full rounded-md border border-red-900 text-sm text-red-300"
                            @click="
                                (actions.abilityRemove(
                                    ability.kind,
                                    selectedAbility,
                                ),
                                (ability = null))
                            "
                        >
                            Remove
                        </button>
                    </div>
                </template>
            </BottomSheet>
            <CatalogPicker
                v-if="actions && system.catalog"
                :open="picker !== null"
                :kind="picker?.kind ?? 'items'"
                :category="picker?.category"
                :system="system"
                :owned="owned"
                @close="picker = null"
                @pick="pick"
                @custom="
                    actions.itemAdd({
                        custom_name: $event.name,
                        custom_description: $event.description,
                        quantity: $event.quantity,
                    })
                "
            />
        </template>
    </div>
</template>
