<script setup lang="ts">
import SheetRenderer from '@/components/sheet/SheetRenderer.vue';
import { countItems, deriveStats } from '@/sheet/derive';
import type { Block, Character, SystemContext } from '@/sheet/types';
import type { OverlayElement } from '@/overlay/types';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    element: OverlayElement;
    character: Character | null;
    system: Pick<
        SystemContext,
        'slots' | 'stats' | 'item_categories' | 'layouts'
    >;
}>();

const fonts = {
    sans: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
    serif: "Georgia, 'Times New Roman', serif",
    display: "'Palatino Linotype', Palatino, 'Book Antiqua', Georgia, serif",
    mono: 'ui-monospace, Consolas, monospace',
};

const style = computed(() => {
    const s = props.element.style;
    return {
        fontSize: s.fontSize ? `${s.fontSize}px` : undefined,
        fontWeight: s.fontWeight,
        fontFamily: s.fontFamily ? fonts[s.fontFamily] : undefined,
        color: s.color,
        background: s.background,
        border: s.borderWidth
            ? `${s.borderWidth}px solid ${s.borderColor ?? 'transparent'}`
            : undefined,
        borderRadius: s.radius !== undefined ? `${s.radius}px` : undefined,
        padding: s.padding !== undefined ? `${s.padding}px` : undefined,
        opacity: s.opacity,
        textShadow: s.shadow
            ? '0 2px 4px rgb(0 0 0 / 0.9), 0 0 2px rgb(0 0 0)'
            : undefined,
        boxShadow: s.glow
            ? `0 0 ${Math.max(12, (s.borderWidth ?? 2) * 6)}px ${s.glow}, inset 0 0 12px ${s.glow}`
            : undefined,
        justifyContent:
            s.align === 'left'
                ? 'flex-start'
                : s.align === 'right'
                  ? 'flex-end'
                  : s.align === 'center'
                    ? 'center'
                    : undefined,
        textAlign: s.align,
    };
});

const stat = computed(() => {
    if (!props.character || !props.element.props.stat) return null;
    return (
        deriveStats(props.character).find(
            (entry) => entry.key === props.element.props.stat,
        ) ?? null
    );
});
const count = computed(() =>
    props.character
        ? countItems(props.character, {
              item: props.element.props.item,
              category: props.element.props.category,
          })
        : 0,
);
const countIcon = computed(
    () =>
        props.character?.items.find((item) =>
            props.element.props.item
                ? item.key === props.element.props.item
                : item.category === props.element.props.category,
        )?.icon ?? null,
);
const blocks = computed<Block[]>(() =>
    (props.element.props.blocks ?? []).map((block, index) => ({
        ...block,
        id: block.id ?? `b${index}`,
        title: block.title ?? null,
        collapsed: false,
    })),
);
const initials = computed(
    () =>
        props.character?.name
            .split(/\s+/)
            .map((part) => part[0])
            .join('')
            .slice(0, 2)
            .toUpperCase() ?? '',
);

// Re-trigger change animations when the bound value changes.
const signature = computed(() => {
    const c = props.character;
    if (!c) return 'empty';
    switch (props.element.type) {
        case 'stat':
            return `${stat.value?.effective}/${stat.value?.effective_max}`;
        case 'item-count':
            return String(count.value);
        case 'hero-card':
            return `${c.revision}`;
        default:
            return `${c.id}`;
    }
});
const animating = ref(false);
watch(signature, (next, previous) => {
    if (
        previous === undefined ||
        props.element.animate === 'none' ||
        props.element.animate === 'pulse'
    )
        return;
    animating.value = false;
    requestAnimationFrame(() => (animating.value = true));
});
const animationClass = computed(() => {
    if (props.element.animate === 'pulse') return 'overlay-anim-pulse';
    return animating.value && props.element.animate !== 'none'
        ? `overlay-anim-${props.element.animate}`
        : '';
});
</script>

<template>
    <div
        class="flex h-full w-full items-center overflow-hidden leading-none"
        :class="animationClass"
        :style="style"
        @animationend="animating = false"
    >
        <template v-if="element.type === 'text'">{{
            element.props.text
        }}</template>
        <template v-else-if="element.type === 'name'">{{
            character?.name
        }}</template>
        <template v-else-if="element.type === 'class'">{{
            character?.class_name
        }}</template>
        <template v-else-if="element.type === 'player'">{{
            character?.assigned_user_name
        }}</template>
        <template v-else-if="element.type === 'portrait'">
            <img
                v-if="character?.portrait"
                :src="character.portrait"
                alt=""
                class="h-full w-full object-cover"
                :style="{ borderRadius: style.borderRadius }"
            />
            <span
                v-else-if="character"
                class="flex h-full w-full items-center justify-center bg-stone-800 font-black text-amber-300"
                :style="{
                    borderRadius: style.borderRadius,
                    fontSize: `${element.h * 0.4}px`,
                }"
                >{{ initials }}</span
            >
        </template>
        <template v-else-if="element.type === 'stat' && stat">
            <span
                v-if="element.props.label"
                class="mr-[0.3em] text-[0.45em] opacity-80"
                >{{ element.props.label }}</span
            >
            <template v-if="element.props.display === 'bar'">
                <span
                    class="relative h-full w-full overflow-hidden"
                    :style="{
                        borderRadius: style.borderRadius,
                        background: 'rgb(0 0 0 / 0.5)',
                    }"
                >
                    <span
                        class="absolute inset-y-0 left-0 transition-[width] duration-500"
                        :style="{
                            width: `${stat.effective_max ? Math.max(0, Math.min(100, (stat.effective / stat.effective_max) * 100)) : 100}%`,
                            background: element.style.color ?? '#d9a441',
                        }"
                    />
                </span>
            </template>
            <template v-else-if="element.props.display === 'pips'">
                <span
                    class="flex h-full w-full flex-wrap items-center gap-[4%]"
                >
                    <span
                        v-for="pip in stat.effective_max ?? stat.effective"
                        :key="pip"
                        class="aspect-square h-[70%] rounded-full"
                        :style="{
                            background:
                                pip <= stat.effective
                                    ? (element.style.color ?? '#d9a441')
                                    : 'rgb(0 0 0 / 0.45)',
                            border: '2px solid rgb(0 0 0 / 0.6)',
                        }"
                    />
                </span>
            </template>
            <template v-else-if="element.props.display === 'value-max'"
                >{{ stat.effective
                }}<span class="text-[0.55em] opacity-80"
                    >/{{ stat.effective_max ?? '—' }}</span
                ></template
            >
            <template v-else>{{ stat.effective }}</template>
        </template>
        <template v-else-if="element.type === 'item-count' && character">
            <img
                v-if="element.props.display === 'icon-value'"
                :src="countIcon ?? '/icons/potion-ball.svg'"
                alt=""
                class="mr-[0.15em] h-[1em] w-[1em] object-contain"
            />
            <span
                v-if="element.props.label"
                class="mr-[0.3em] text-[0.5em] opacity-80"
                >{{ element.props.label }}</span
            >
            {{ count }}
        </template>
        <template v-else-if="element.type === 'equipped' && character">
            <span class="flex h-full items-center gap-[6%]">
                <template
                    v-for="item in character.items.filter(
                        (entry) => entry.slots.length,
                    )"
                    :key="item.id"
                >
                    <img
                        v-if="item.icon"
                        :src="item.icon"
                        :alt="item.name"
                        class="h-[80%] object-contain"
                    />
                    <span v-else class="text-[0.5em]">{{ item.name }}</span>
                </template>
            </span>
        </template>
        <template v-else-if="element.type === 'image' && element.props.src">
            <img
                :src="element.props.src"
                alt=""
                class="h-full w-full object-contain"
            />
        </template>
        <template v-else-if="element.type === 'hero-card' && character">
            <!-- Sheet blocks are rem-sized; zoom scales the whole card with the element's font size. -->
            <div
                class="h-full w-full self-start overflow-hidden text-left text-base text-stone-100"
                :style="{ zoom: (element.style.fontSize ?? 16) / 16 }"
            >
                <p class="mb-1 truncate text-xl font-black">
                    {{ character.name }}
                </p>
                <SheetRenderer
                    :character="character"
                    :system="{
                        ...system,
                        rules_mode: 'tracker',
                        catalog: null,
                    }"
                    :blocks="blocks"
                    mode="overlay"
                    layout-name="overlay"
                    :actions="null"
                />
            </div>
        </template>
    </div>
</template>
