<script setup lang="ts">
import { heroFor, isVisible } from '@/overlay/resolve';
import type {
    OverlayConfig,
    OverlayElement,
    OverlayState,
} from '@/overlay/types';
import { computed } from 'vue';
import OverlayElementView from './OverlayElementView.vue';

/**
 * Renders an overlay at its logical size (e.g. 1920×1080) scaled by `scale`. In the designer
 * (`editable`) hidden elements stay faintly visible so they can still be selected and moved.
 */
const props = defineProps<{
    config: OverlayConfig;
    state: Pick<OverlayState, 'characters' | 'game' | 'system'>;
    scale: number;
    editable?: boolean;
    selectedId?: string | null;
}>();
const emit = defineEmits<{
    grab: [OverlayElement, PointerEvent, 'move' | 'resize'];
}>();

const rendered = computed(() =>
    [...props.config.elements]
        .sort((a, b) => a.z - b.z)
        .map((element) => {
            const character = heroFor(
                props.config,
                props.state.characters,
                props.state.game.current_character_id,
                element.hero,
            );
            return {
                element,
                character,
                visible: isVisible(
                    element,
                    character,
                    props.state.game.current_character_id,
                ),
            };
        })
        .filter((entry) => entry.visible || props.editable),
);
</script>

<template>
    <div
        class="relative origin-top-left"
        :style="{
            width: `${config.width}px`,
            height: `${config.height}px`,
            transform: `scale(${scale})`,
        }"
    >
        <div
            v-for="{ element, character, visible } in rendered"
            :key="element.id"
            class="absolute"
            :class="{
                'opacity-10': editable && !visible && selectedId !== element.id,
                'opacity-60': editable && !visible && selectedId === element.id,
                'cursor-move outline-2 outline-offset-2 outline-dashed':
                    editable,
                'outline-amber-400': editable && selectedId === element.id,
                'outline-transparent hover:outline-stone-500':
                    editable && selectedId !== element.id,
            }"
            :style="{
                left: `${element.x}px`,
                top: `${element.y}px`,
                width: `${element.w}px`,
                height: `${element.h}px`,
            }"
            @pointerdown="editable && emit('grab', element, $event, 'move')"
        >
            <OverlayElementView
                :element="element"
                :character="character"
                :system="state.system"
            />
            <span
                v-if="editable && selectedId === element.id"
                class="absolute -right-3 -bottom-3 size-6 cursor-se-resize rounded-sm border-2 border-stone-950 bg-amber-400"
                :style="{ transform: `scale(${1 / scale})` }"
                @pointerdown.stop="emit('grab', element, $event, 'resize')"
            />
        </div>
    </div>
</template>
