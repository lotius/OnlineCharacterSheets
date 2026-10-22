<script setup lang="ts">
import AppHeader from '@/components/AppHeader.vue';
import OverlayCanvas from '@/components/overlay/OverlayCanvas.vue';
import ToastHost from '@/components/sheet/ToastHost.vue';
import { echo } from '@/echo';
import { sampleState } from '@/overlay/resolve';
import type {
    ElementType,
    OverlayConfig,
    OverlayElement,
    OverlayState,
} from '@/overlay/types';
import type { Block } from '@/sheet/types';
import { toast } from '@/sheet/toasts';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    overlay: {
        id: number;
        name: string;
        url: string;
        configuration: OverlayConfig;
    };
    presets: Record<string, string>;
    state: OverlayState;
    game: { id: number; name: string };
}>();

const clone = <T>(value: T): T => JSON.parse(JSON.stringify(value)) as T;
const config = ref<OverlayConfig>(clone(props.overlay.configuration));
const name = ref(props.overlay.name);
const saved = ref(
    JSON.stringify({
        name: props.overlay.name,
        config: props.overlay.configuration,
    }),
);
const dirty = computed(
    () =>
        JSON.stringify({ name: name.value, config: config.value }) !==
        saved.value,
);
const selectedId = ref<string | null>(null);
const selected = computed(
    () =>
        config.value.elements.find(
            (element) => element.id === selectedId.value,
        ) ?? null,
);
const dataMode = ref<'live' | 'sample'>(
    props.state.characters.length ? 'live' : 'sample',
);
const live = ref<OverlayState>(props.state);
const preview = computed(() =>
    dataMode.value === 'sample' ? sampleState(live.value) : live.value,
);
const snap = ref(true);
const copied = ref(false);
const confirmDelete = ref(false);
const stage = ref<HTMLElement | null>(null);
const scale = ref(0.5);

// When the server returns a new configuration (save, preset reset), adopt it.
watch(
    () => props.overlay.configuration,
    (next) => {
        config.value = clone(next);
        saved.value = JSON.stringify({
            name: props.overlay.name,
            config: next,
        });
        history.value = [JSON.stringify(next)];
        cursor.value = 0;
    },
);

// ---- Local undo/redo of editor changes (not game events). ----
const history = ref<string[]>([JSON.stringify(config.value)]);
const cursor = ref(0);
const record = () => {
    const snapshot = JSON.stringify(config.value);
    if (snapshot === history.value[cursor.value]) return;
    history.value = [
        ...history.value.slice(0, cursor.value + 1),
        snapshot,
    ].slice(-100);
    cursor.value = history.value.length - 1;
};
const undo = () => {
    if (cursor.value === 0) return;
    cursor.value -= 1;
    config.value = JSON.parse(history.value[cursor.value]) as OverlayConfig;
};
const redo = () => {
    if (cursor.value >= history.value.length - 1) return;
    cursor.value += 1;
    config.value = JSON.parse(history.value[cursor.value]) as OverlayConfig;
};

// ---- Elements ----
const palette: Array<{ type: ElementType; label: string }> = [
    { type: 'hero-card', label: 'Hero card' },
    { type: 'stat', label: 'Stat value' },
    { type: 'item-count', label: 'Item count' },
    { type: 'portrait', label: 'Portrait' },
    { type: 'name', label: 'Hero name' },
    { type: 'class', label: 'Class' },
    { type: 'player', label: 'Player name' },
    { type: 'equipped', label: 'Equipped icons' },
    { type: 'turn-marker', label: 'Turn marker' },
    { type: 'text', label: 'Text' },
    { type: 'box', label: 'Box / panel' },
    { type: 'image', label: 'Image' },
];
const id = () => Math.random().toString(36).slice(2, 10);
const firstResource = computed(
    () =>
        live.value.system.stats.find((stat) => stat.kind === 'resource')?.key ??
        live.value.system.stats[0]?.key ??
        'body',
);
const text = { color: '#f5ead2', shadow: true };
function defaults(type: ElementType): Omit<OverlayElement, 'id' | 'x' | 'y'> {
    const base = {
        z: 0,
        hero: 1 as OverlayElement['hero'],
        show: 'occupied' as const,
        animate: 'none' as const,
        props: {},
        style: {},
    };
    switch (type) {
        case 'text':
            return {
                ...base,
                type,
                w: 400,
                h: 60,
                hero: null,
                show: 'always',
                props: { text: 'Text' },
                style: { ...text, fontSize: 36 },
            };
        case 'name':
            return {
                ...base,
                type,
                w: 300,
                h: 50,
                style: { ...text, fontSize: 32, fontWeight: 700 },
            };
        case 'class':
        case 'player':
            return {
                ...base,
                type,
                w: 260,
                h: 40,
                style: { ...text, fontSize: 24 },
            };
        case 'portrait':
            return {
                ...base,
                type,
                w: 120,
                h: 120,
                style: { radius: 999, borderColor: '#d9a441', borderWidth: 3 },
            };
        case 'stat':
            return {
                ...base,
                type,
                w: 120,
                h: 100,
                animate: 'bump',
                props: { stat: firstResource.value, display: 'value' },
                style: {
                    ...text,
                    color: '#facc15',
                    fontSize: 72,
                    fontWeight: 800,
                    align: 'center',
                    fontFamily: 'display',
                },
            };
        case 'item-count':
            return {
                ...base,
                type,
                w: 140,
                h: 70,
                animate: 'bump',
                props: {
                    category:
                        live.value.system.item_categories.find(
                            (c) => c.key === 'potion',
                        )?.key ??
                        live.value.system.item_categories[0]?.key ??
                        'potion',
                    display: 'icon-value',
                },
                style: {
                    ...text,
                    color: '#4ade80',
                    fontSize: 44,
                    fontWeight: 800,
                },
            };
        case 'equipped':
            return { ...base, type, w: 300, h: 60 };
        case 'turn-marker':
            return {
                ...base,
                type,
                w: 340,
                h: 140,
                show: 'current',
                animate: 'pulse',
                style: {
                    borderColor: '#facc15',
                    borderWidth: 4,
                    radius: 16,
                    glow: 'rgba(250,204,21,0.5)',
                },
            };
        case 'box':
            return {
                ...base,
                type,
                w: 300,
                h: 150,
                hero: null,
                show: 'always',
                style: { background: 'rgba(12,9,7,0.6)', radius: 12 },
            };
        case 'image':
            return {
                ...base,
                type,
                w: 200,
                h: 200,
                hero: null,
                show: 'always',
                props: { src: '' },
            };
        case 'hero-card':
            return {
                ...base,
                type,
                w: 360,
                h: 280,
                animate: 'flash',
                props: {
                    blocks: [
                        {
                            id: 'stats',
                            type: 'stats',
                            title: null,
                            collapsed: false,
                        },
                        {
                            id: 'equipment',
                            type: 'equipment',
                            title: null,
                            collapsed: false,
                        },
                    ],
                },
                style: {
                    background: 'rgba(12,9,7,0.78)',
                    radius: 10,
                    padding: 12,
                    fontSize: 16,
                },
            };
    }
}
const add = (type: ElementType) => {
    const element = {
        id: id(),
        x: 80 + (config.value.elements.length % 10) * 24,
        y: 80 + (config.value.elements.length % 10) * 24,
        ...defaults(type),
    } as OverlayElement;
    element.z =
        Math.max(0, ...config.value.elements.map((entry) => entry.z)) + 1;
    config.value.elements.push(element);
    selectedId.value = element.id;
    record();
};
const remove = () => {
    if (!selected.value) return;
    config.value.elements = config.value.elements.filter(
        (element) => element.id !== selectedId.value,
    );
    selectedId.value = null;
    record();
};
const duplicate = () => {
    if (!selected.value) return;
    const copy = {
        ...clone(selected.value),
        id: id(),
        x: selected.value.x + 24,
        y: selected.value.y + 24,
    };
    config.value.elements.push(copy);
    selectedId.value = copy.id;
    record();
};
const layer = (direction: 1 | -1) => {
    if (!selected.value) return;
    selected.value.z += direction;
    record();
};

// ---- Dragging / resizing in logical canvas pixels ----
let drag: {
    element: OverlayElement;
    mode: 'move' | 'resize';
    startX: number;
    startY: number;
    x: number;
    y: number;
    w: number;
    h: number;
} | null = null;
const round = (value: number) =>
    snap.value ? Math.round(value / 8) * 8 : Math.round(value);
const grab = (
    element: OverlayElement,
    event: PointerEvent,
    mode: 'move' | 'resize',
) => {
    if (event.button !== 0) return;
    event.preventDefault();
    selectedId.value = element.id;
    drag = {
        element,
        mode,
        startX: event.clientX,
        startY: event.clientY,
        x: element.x,
        y: element.y,
        w: element.w,
        h: element.h,
    };
};
const onMove = (event: PointerEvent) => {
    if (!drag) return;
    const dx = (event.clientX - drag.startX) / scale.value;
    const dy = (event.clientY - drag.startY) / scale.value;
    if (drag.mode === 'move') {
        drag.element.x = round(drag.x + dx);
        drag.element.y = round(drag.y + dy);
    } else {
        drag.element.w = Math.max(8, round(drag.w + dx));
        drag.element.h = Math.max(8, round(drag.h + dy));
    }
};
const onUp = () => {
    if (drag) record();
    drag = null;
};
const onKey = (event: KeyboardEvent) => {
    const typing =
        event.target instanceof HTMLInputElement ||
        event.target instanceof HTMLTextAreaElement ||
        event.target instanceof HTMLSelectElement;
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
        event.preventDefault();
        save();
        return;
    }
    if (typing) return;
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') {
        event.preventDefault();
        if (event.shiftKey) redo();
        else undo();
    } else if (
        (event.ctrlKey || event.metaKey) &&
        event.key.toLowerCase() === 'y'
    ) {
        event.preventDefault();
        redo();
    } else if (
        (event.ctrlKey || event.metaKey) &&
        event.key.toLowerCase() === 'd'
    ) {
        event.preventDefault();
        duplicate();
    } else if (
        selected.value &&
        (event.key === 'Delete' || event.key === 'Backspace')
    ) {
        event.preventDefault();
        remove();
    } else if (selected.value && event.key.startsWith('Arrow')) {
        event.preventDefault();
        const step = event.shiftKey ? 10 : 1;
        if (event.key === 'ArrowLeft') selected.value.x -= step;
        if (event.key === 'ArrowRight') selected.value.x += step;
        if (event.key === 'ArrowUp') selected.value.y -= step;
        if (event.key === 'ArrowDown') selected.value.y += step;
        record();
    } else if (event.key === 'Escape') {
        selectedId.value = null;
    }
};

// ---- Hero card blocks (a small, safe subset of sheet blocks) ----
const blockChoices = computed<
    Array<{
        key: string;
        label: string;
        block: Omit<Block, 'id' | 'collapsed'>;
    }>
>(() => [
    { key: 'stats', label: 'Stats', block: { type: 'stats', title: null } },
    {
        key: 'equipment',
        label: 'Equipment',
        block: { type: 'equipment', title: null },
    },
    ...live.value.system.item_categories.map((category) => ({
        key: `items:${category.key}`,
        label: category.name,
        block: {
            type: 'items' as const,
            title: category.name,
            category: category.key,
        },
    })),
    {
        key: 'spells',
        label: 'Spells',
        block: { type: 'abilities', title: 'Spells', kind: 'spells' },
    },
    {
        key: 'skills',
        label: 'Skills',
        block: { type: 'abilities', title: 'Skills', kind: 'skills' },
    },
]);
const blockKey = (block: Block) =>
    block.type === 'items'
        ? `items:${block.category}`
        : block.type === 'abilities'
          ? (block.kind ?? 'spells')
          : block.type;
const toggleBlock = (key: string) => {
    if (!selected.value) return;
    const blocks = selected.value.props.blocks ?? [];
    const exists = blocks.some((block) => blockKey(block) === key);
    const choice = blockChoices.value.find((entry) => entry.key === key)!;
    selected.value.props.blocks = exists
        ? blocks.filter((block) => blockKey(block) !== key)
        : [
              ...blocks,
              {
                  ...choice.block,
                  id: key.replace(':', '-'),
                  collapsed: false,
              } as Block,
          ];
    record();
};

// ---- Hero slots ----
const heroCount = computed(() =>
    Math.max(
        5,
        ...config.value.elements.map((element) =>
            typeof element.hero === 'number' ? element.hero : 0,
        ),
    ),
);
const setHeroSlot = (index: number, value: string) => {
    const slots = [...(config.value.hero_slots ?? [])];
    while (slots.length < heroCount.value) slots.push(null);
    slots[index] = value === '' ? null : Number(value);
    config.value.hero_slots = slots.every((slot) => slot === null)
        ? null
        : slots;
    record();
};

// ---- Canvas size, scale and live data ----
const sizes = [
    [1920, 1080],
    [2560, 1440],
    [3840, 2160],
    [1280, 720],
];
const setSize = (value: string) => {
    const [width, height] = value.split('x').map(Number);
    config.value.width = width;
    config.value.height = height;
    record();
    fit();
};
const fit = () => {
    if (stage.value)
        scale.value = Math.min(
            1,
            (stage.value.clientWidth - 2) / config.value.width,
        );
};
const token = computed(() => props.overlay.url.split('/').pop() ?? '');
const refreshLive = async () => {
    const response = await fetch(`/overlay/${token.value}/state`, {
        headers: { Accept: 'application/json' },
        cache: 'no-store',
    }).catch(() => null);
    if (response?.ok) live.value = (await response.json()) as OverlayState;
};

// ---- Persistence ----
const save = () => {
    router.put(
        `/games/${props.game.id}/overlays/${props.overlay.id}`,
        { name: name.value, configuration: config.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => toast('Overlay saved. OBS updates automatically.'),
            onError: (errors) =>
                toast(
                    Object.values(errors)[0] ?? 'Could not save the overlay.',
                    'error',
                    8000,
                ),
        },
    );
};
const applyPreset = (preset: string) => {
    if (
        !preset ||
        !window.confirm(
            'Replace the whole layout with this preset? This is saved immediately.',
        )
    )
        return;
    router.put(
        `/games/${props.game.id}/overlays/${props.overlay.id}`,
        { name: name.value, configuration: config.value, preset },
        { preserveScroll: true, preserveState: true },
    );
};
const copyUrl = async () => {
    await navigator.clipboard.writeText(props.overlay.url);
    copied.value = true;
    window.setTimeout(() => (copied.value = false), 2000);
};
const rotate = () => {
    if (
        window.confirm(
            'Issue a new URL? The current OBS source will stop updating until you paste the new URL.',
        )
    ) {
        router.post(
            `/games/${props.game.id}/overlays/${props.overlay.id}/token`,
            {},
            { preserveState: true, preserveScroll: true },
        );
    }
};
const destroy = () => {
    if (!confirmDelete.value) {
        confirmDelete.value = true;
        return;
    }
    router.delete(`/games/${props.game.id}/overlays/${props.overlay.id}`);
};

let observer: ResizeObserver | null = null;
let liveChannel: string | null = null;
let livePoll: number | undefined;
const beforeUnload = (event: BeforeUnloadEvent) => {
    if (dirty.value) event.preventDefault();
};
onMounted(() => {
    window.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', onUp);
    window.addEventListener('keydown', onKey);
    window.addEventListener('beforeunload', beforeUnload);
    observer = new ResizeObserver(fit);
    if (stage.value) observer.observe(stage.value);
    fit();
    liveChannel = props.state.overlay.channel;
    echo?.channel(liveChannel).listen(
        '.room.realtime.ping',
        () => void refreshLive(),
    );
    livePoll = window.setInterval(() => void refreshLive(), 30000);
});
onUnmounted(() => {
    window.removeEventListener('pointermove', onMove);
    window.removeEventListener('pointerup', onUp);
    window.removeEventListener('keydown', onKey);
    window.removeEventListener('beforeunload', beforeUnload);
    observer?.disconnect();
    if (liveChannel) echo?.leave(liveChannel);
    window.clearInterval(livePoll);
});

const numberField = (key: 'x' | 'y' | 'w' | 'h' | 'z', value: string) => {
    if (selected.value && value !== '') selected.value[key] = Number(value);
};
const styleField = <K extends keyof OverlayElement['style']>(
    key: K,
    value: OverlayElement['style'][K] | '',
) => {
    if (!selected.value) return;
    if (value === '' || value === undefined) delete selected.value.style[key];
    else selected.value.style[key] = value as OverlayElement['style'][K];
};
const hexOf = (color?: string) =>
    color && /^#[0-9a-f]{6}$/i.test(color) ? color : '#ffffff';
</script>

<template>
    <Head :title="`Overlay · ${name}`" />
    <main class="min-h-screen bg-stone-950 text-stone-100">
        <AppHeader current="dashboard" />
        <div class="mx-auto max-w-[110rem] px-3 py-3">
            <!-- Toolbar -->
            <div class="flex flex-wrap items-center gap-2">
                <Link :href="`/games/${game.id}`" class="text-sm text-amber-500"
                    >← {{ game.name }}</Link
                >
                <input
                    v-model="name"
                    aria-label="Overlay name"
                    maxlength="80"
                    class="min-h-10 w-56 rounded-md border px-3 font-bold"
                />
                <button
                    type="button"
                    class="min-h-10 rounded-md bg-amber-600 px-4 font-bold disabled:opacity-50"
                    :disabled="!dirty"
                    title="Save (Ctrl+S)"
                    @click="save"
                >
                    {{ dirty ? 'Save' : 'Saved' }}
                </button>
                <button
                    type="button"
                    class="min-h-10 rounded-md bg-stone-800 px-3 disabled:opacity-40"
                    :disabled="cursor === 0"
                    title="Undo (Ctrl+Z)"
                    @click="undo"
                >
                    ↶ Undo
                </button>
                <button
                    type="button"
                    class="min-h-10 rounded-md bg-stone-800 px-3 disabled:opacity-40"
                    :disabled="cursor >= history.length - 1"
                    title="Redo (Ctrl+Y)"
                    @click="redo"
                >
                    ↷ Redo
                </button>
                <span class="mx-1 h-6 w-px bg-stone-700" aria-hidden="true" />
                <div
                    class="flex rounded-md border border-stone-700 text-sm"
                    role="group"
                    aria-label="Preview data"
                >
                    <button
                        type="button"
                        class="min-h-10 px-3"
                        :class="{
                            'bg-stone-700 text-amber-300': dataMode === 'live',
                        }"
                        @click="dataMode = 'live'"
                    >
                        Live game
                    </button>
                    <button
                        type="button"
                        class="min-h-10 px-3"
                        :class="{
                            'bg-stone-700 text-amber-300':
                                dataMode === 'sample',
                        }"
                        @click="dataMode = 'sample'"
                    >
                        Sample party
                    </button>
                </div>
                <label class="flex items-center gap-2 text-sm"
                    ><input v-model="snap" type="checkbox" class="size-4" />
                    Snap</label
                >
                <select
                    :value="`${config.width}x${config.height}`"
                    aria-label="Canvas size"
                    class="min-h-10 rounded-md border px-2 text-sm"
                    @change="
                        setSize(($event.target as HTMLSelectElement).value)
                    "
                >
                    <option
                        v-for="[w, h] in sizes"
                        :key="`${w}x${h}`"
                        :value="`${w}x${h}`"
                    >
                        {{ w }}×{{ h }}
                    </option>
                </select>
                <select
                    aria-label="Replace with preset"
                    class="min-h-10 rounded-md border px-2 text-sm"
                    @change="
                        (applyPreset(
                            ($event.target as HTMLSelectElement).value,
                        ),
                        (($event.target as HTMLSelectElement).value = ''))
                    "
                >
                    <option value="">Start over from preset…</option>
                    <option
                        v-for="(label, key) in presets"
                        :key="key"
                        :value="key"
                    >
                        {{ label }}
                    </option>
                </select>
                <span class="flex-1" />
                <button
                    type="button"
                    class="min-h-10 rounded-md border border-stone-700 px-3 text-sm"
                    @click="copyUrl"
                >
                    {{ copied ? 'Copied!' : 'Copy OBS URL' }}
                </button>
                <a
                    :href="overlay.url"
                    target="_blank"
                    rel="noopener"
                    class="flex min-h-10 items-center rounded-md border border-stone-700 px-3 text-sm"
                    >Open preview ↗</a
                >
                <button
                    type="button"
                    class="min-h-10 rounded-md border border-stone-700 px-3 text-sm"
                    @click="rotate"
                >
                    New URL
                </button>
                <button
                    type="button"
                    class="min-h-10 rounded-md border px-3 text-sm"
                    :class="
                        confirmDelete
                            ? 'border-red-500 bg-red-900'
                            : 'border-red-900 text-red-300'
                    "
                    @click="destroy"
                >
                    {{ confirmDelete ? 'Confirm delete' : 'Delete' }}
                </button>
            </div>

            <div
                class="mt-3 grid gap-3 lg:grid-cols-[11rem_minmax(0,1fr)_19rem]"
            >
                <!-- Palette -->
                <aside class="setup-card space-y-1 p-2">
                    <p
                        class="px-1 text-xs font-bold tracking-wider text-stone-400 uppercase"
                    >
                        Add element
                    </p>
                    <button
                        v-for="entry in palette"
                        :key="entry.type"
                        type="button"
                        class="block min-h-9 w-full rounded px-2 text-left text-sm hover:bg-stone-800"
                        @click="add(entry.type)"
                    >
                        + {{ entry.label }}
                    </button>
                    <p
                        class="px-1 pt-3 text-xs font-bold tracking-wider text-stone-400 uppercase"
                    >
                        Hero slots
                    </p>
                    <label
                        v-for="index in heroCount"
                        :key="index"
                        class="flex items-center gap-1 px-1 text-xs"
                    >
                        <span class="w-4">{{ index }}</span>
                        <select
                            :value="config.hero_slots?.[index - 1] ?? ''"
                            class="min-h-8 w-full rounded border px-1"
                            @change="
                                setHeroSlot(
                                    index - 1,
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option value="">Auto (turn order)</option>
                            <option
                                v-for="character in live.characters"
                                :key="character.id"
                                :value="character.id"
                            >
                                {{ character.name }}
                            </option>
                        </select>
                    </label>
                </aside>

                <!-- Canvas -->
                <div
                    ref="stage"
                    class="self-start overflow-hidden rounded-md border border-stone-700"
                    style="
                        background: repeating-conic-gradient(
                                #2a2420 0% 25%,
                                #1c1815 0% 50%
                            )
                            50% / 24px 24px;
                    "
                    @pointerdown.self="selectedId = null"
                >
                    <div
                        :style="{ height: `${config.height * scale}px` }"
                        @pointerdown.self="selectedId = null"
                    >
                        <OverlayCanvas
                            :config="config"
                            :state="preview"
                            :scale="scale"
                            editable
                            :selected-id="selectedId"
                            @grab="grab"
                        />
                    </div>
                    <p
                        class="border-t border-stone-800 bg-stone-950 px-3 py-1 text-xs text-stone-400"
                    >
                        Drag to move · corner handle resizes · arrows nudge
                        (Shift = 10px) · Delete removes · Ctrl+D duplicates ·
                        faded elements are hidden right now by their show
                        condition.
                    </p>
                </div>

                <!-- Inspector -->
                <aside class="setup-card space-y-3 p-3 text-sm">
                    <template v-if="selected">
                        <div class="flex items-center justify-between">
                            <p class="font-bold text-amber-400 capitalize">
                                {{ selected.type.replace('-', ' ') }}
                            </p>
                            <div class="flex gap-1">
                                <button
                                    type="button"
                                    class="size-8 rounded bg-stone-800"
                                    title="Bring forward"
                                    @click="layer(1)"
                                >
                                    ↑
                                </button>
                                <button
                                    type="button"
                                    class="size-8 rounded bg-stone-800"
                                    title="Send backward"
                                    @click="layer(-1)"
                                >
                                    ↓
                                </button>
                                <button
                                    type="button"
                                    class="size-8 rounded bg-stone-800"
                                    title="Duplicate"
                                    @click="duplicate"
                                >
                                    ⧉
                                </button>
                                <button
                                    type="button"
                                    class="size-8 rounded bg-stone-800 text-red-300"
                                    title="Delete"
                                    @click="remove"
                                >
                                    ×
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-4 gap-1">
                            <label
                                v-for="key in ['x', 'y', 'w', 'h'] as const"
                                :key="key"
                                class="text-xs text-stone-400"
                            >
                                {{ key.toUpperCase() }}
                                <input
                                    type="number"
                                    :value="Math.round(selected[key])"
                                    class="min-h-8 w-full rounded border px-1 text-stone-100"
                                    @input="
                                        numberField(
                                            key,
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        )
                                    "
                                    @change="record"
                                />
                            </label>
                        </div>
                        <label class="block text-xs text-stone-400">
                            Bound to
                            <select
                                :value="
                                    selected.hero === null
                                        ? ''
                                        : String(selected.hero)
                                "
                                class="mt-1 min-h-9 w-full rounded border px-2 text-stone-100"
                                @change="
                                    ((selected.hero =
                                        ($event.target as HTMLSelectElement)
                                            .value === ''
                                            ? null
                                            : (
                                                    $event.target as HTMLSelectElement
                                                ).value === 'current'
                                              ? 'current'
                                              : Number(
                                                    (
                                                        $event.target as HTMLSelectElement
                                                    ).value,
                                                )),
                                    record())
                                "
                            >
                                <option value="">Nothing (static)</option>
                                <option value="current">
                                    Current-turn hero
                                </option>
                                <option
                                    v-for="index in heroCount"
                                    :key="index"
                                    :value="String(index)"
                                >
                                    Hero slot {{ index }}
                                </option>
                            </select>
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="text-xs text-stone-400">
                                Show when
                                <select
                                    v-model="selected.show"
                                    class="mt-1 min-h-9 w-full rounded border px-1 text-stone-100"
                                    @change="record"
                                >
                                    <option value="always">Always</option>
                                    <option value="occupied">
                                        Hero present
                                    </option>
                                    <option value="empty">Slot empty</option>
                                    <option value="current">Hero's turn</option>
                                    <option value="not-current">
                                        Not hero's turn
                                    </option>
                                </select>
                            </label>
                            <label class="text-xs text-stone-400">
                                Animation
                                <select
                                    v-model="selected.animate"
                                    class="mt-1 min-h-9 w-full rounded border px-1 text-stone-100"
                                    @change="record"
                                >
                                    <option value="none">None</option>
                                    <option value="bump">Bump on change</option>
                                    <option value="flash">
                                        Flash on change
                                    </option>
                                    <option value="shake">
                                        Shake on change
                                    </option>
                                    <option value="pulse">
                                        Pulse (always)
                                    </option>
                                </select>
                            </label>
                        </div>

                        <!-- Type-specific content -->
                        <label
                            v-if="selected.type === 'text'"
                            class="block text-xs text-stone-400"
                        >
                            Text
                            <input
                                v-model="selected.props.text"
                                maxlength="300"
                                class="mt-1 min-h-9 w-full rounded border px-2 text-stone-100"
                                @change="record"
                            />
                        </label>
                        <template v-if="selected.type === 'stat'">
                            <div class="grid grid-cols-2 gap-2">
                                <label class="text-xs text-stone-400">
                                    Stat
                                    <select
                                        v-model="selected.props.stat"
                                        class="mt-1 min-h-9 w-full rounded border px-1 text-stone-100"
                                        @change="record"
                                    >
                                        <option
                                            v-for="stat in live.system.stats"
                                            :key="stat.key"
                                            :value="stat.key"
                                        >
                                            {{ stat.name }}
                                        </option>
                                    </select>
                                </label>
                                <label class="text-xs text-stone-400">
                                    Display
                                    <select
                                        v-model="selected.props.display"
                                        class="mt-1 min-h-9 w-full rounded border px-1 text-stone-100"
                                        @change="record"
                                    >
                                        <option value="value">Value</option>
                                        <option value="value-max">
                                            Value / max
                                        </option>
                                        <option value="bar">Bar</option>
                                        <option value="pips">Pips</option>
                                    </select>
                                </label>
                            </div>
                        </template>
                        <template v-if="selected.type === 'item-count'">
                            <label class="block text-xs text-stone-400">
                                Count items in category
                                <select
                                    v-model="selected.props.category"
                                    class="mt-1 min-h-9 w-full rounded border px-1 text-stone-100"
                                    @change="
                                        ((selected.props.item = undefined),
                                        record())
                                    "
                                >
                                    <option
                                        v-for="category in live.system
                                            .item_categories"
                                        :key="category.key"
                                        :value="category.key"
                                    >
                                        {{ category.name }}
                                    </option>
                                </select>
                            </label>
                            <label class="block text-xs text-stone-400">
                                …or one item key (e.g. healing_potion)
                                <input
                                    v-model="selected.props.item"
                                    class="mt-1 min-h-9 w-full rounded border px-2 text-stone-100"
                                    @change="record"
                                />
                            </label>
                            <label class="flex items-center gap-2 text-xs"
                                ><input
                                    type="checkbox"
                                    :checked="
                                        selected.props.display === 'icon-value'
                                    "
                                    class="size-4"
                                    @change="
                                        ((selected.props.display = (
                                            $event.target as HTMLInputElement
                                        ).checked
                                            ? 'icon-value'
                                            : 'value'),
                                        record())
                                    "
                                />
                                Show icon</label
                            >
                        </template>
                        <label
                            v-if="
                                selected.type === 'stat' ||
                                selected.type === 'item-count'
                            "
                            class="block text-xs text-stone-400"
                        >
                            Label (optional)
                            <input
                                v-model="selected.props.label"
                                maxlength="60"
                                class="mt-1 min-h-9 w-full rounded border px-2 text-stone-100"
                                @change="record"
                            />
                        </label>
                        <label
                            v-if="selected.type === 'image'"
                            class="block text-xs text-stone-400"
                        >
                            Image URL (https:// or /path)
                            <input
                                v-model="selected.props.src"
                                maxlength="500"
                                class="mt-1 min-h-9 w-full rounded border px-2 text-stone-100"
                                @change="record"
                            />
                        </label>
                        <div
                            v-if="selected.type === 'hero-card'"
                            class="text-xs text-stone-400"
                        >
                            Sections
                            <label
                                v-for="choice in blockChoices"
                                :key="choice.key"
                                class="mt-1 flex items-center gap-2 text-stone-100"
                            >
                                <input
                                    type="checkbox"
                                    class="size-4"
                                    :checked="
                                        (selected.props.blocks ?? []).some(
                                            (block) =>
                                                blockKey(block) === choice.key,
                                        )
                                    "
                                    @change="toggleBlock(choice.key)"
                                />
                                {{ choice.label }}
                            </label>
                        </div>

                        <!-- Style -->
                        <p
                            class="border-t border-stone-800 pt-2 text-xs font-bold tracking-wider text-stone-400 uppercase"
                        >
                            Style
                        </p>
                        <div class="grid grid-cols-3 gap-2">
                            <label class="text-xs text-stone-400"
                                >Size<input
                                    type="number"
                                    :value="selected.style.fontSize ?? ''"
                                    class="mt-1 min-h-8 w-full rounded border px-1 text-stone-100"
                                    @input="
                                        styleField(
                                            'fontSize',
                                            ($event.target as HTMLInputElement)
                                                .value === ''
                                                ? ''
                                                : Number(
                                                      (
                                                          $event.target as HTMLInputElement
                                                      ).value,
                                                  ),
                                        )
                                    "
                                    @change="record"
                            /></label>
                            <label class="text-xs text-stone-400"
                                >Weight
                                <select
                                    :value="selected.style.fontWeight ?? ''"
                                    class="mt-1 min-h-8 w-full rounded border px-1 text-stone-100"
                                    @change="
                                        (styleField(
                                            'fontWeight',
                                            ($event.target as HTMLSelectElement)
                                                .value === ''
                                                ? ''
                                                : Number(
                                                      (
                                                          $event.target as HTMLSelectElement
                                                      ).value,
                                                  ),
                                        ),
                                        record())
                                    "
                                >
                                    <option value="">—</option>
                                    <option value="400">Normal</option>
                                    <option value="700">Bold</option>
                                    <option value="900">Black</option>
                                </select>
                            </label>
                            <label class="text-xs text-stone-400"
                                >Font
                                <select
                                    :value="selected.style.fontFamily ?? ''"
                                    class="mt-1 min-h-8 w-full rounded border px-1 text-stone-100"
                                    @change="
                                        (styleField(
                                            'fontFamily',
                                            ($event.target as HTMLSelectElement)
                                                .value as 'sans',
                                        ),
                                        record())
                                    "
                                >
                                    <option value="">—</option>
                                    <option value="sans">Sans</option>
                                    <option value="serif">Serif</option>
                                    <option value="display">Display</option>
                                    <option value="mono">Mono</option>
                                </select>
                            </label>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <label class="text-xs text-stone-400"
                                >Align
                                <select
                                    :value="selected.style.align ?? ''"
                                    class="mt-1 min-h-8 w-full rounded border px-1 text-stone-100"
                                    @change="
                                        (styleField(
                                            'align',
                                            ($event.target as HTMLSelectElement)
                                                .value as 'left',
                                        ),
                                        record())
                                    "
                                >
                                    <option value="">—</option>
                                    <option value="left">Left</option>
                                    <option value="center">Center</option>
                                    <option value="right">Right</option>
                                </select>
                            </label>
                            <label class="text-xs text-stone-400"
                                >Radius<input
                                    type="number"
                                    :value="selected.style.radius ?? ''"
                                    class="mt-1 min-h-8 w-full rounded border px-1 text-stone-100"
                                    @input="
                                        styleField(
                                            'radius',
                                            ($event.target as HTMLInputElement)
                                                .value === ''
                                                ? ''
                                                : Number(
                                                      (
                                                          $event.target as HTMLInputElement
                                                      ).value,
                                                  ),
                                        )
                                    "
                                    @change="record"
                            /></label>
                            <label class="text-xs text-stone-400"
                                >Padding<input
                                    type="number"
                                    :value="selected.style.padding ?? ''"
                                    class="mt-1 min-h-8 w-full rounded border px-1 text-stone-100"
                                    @input="
                                        styleField(
                                            'padding',
                                            ($event.target as HTMLInputElement)
                                                .value === ''
                                                ? ''
                                                : Number(
                                                      (
                                                          $event.target as HTMLInputElement
                                                      ).value,
                                                  ),
                                        )
                                    "
                                    @change="record"
                            /></label>
                        </div>
                        <div
                            v-for="key in [
                                'color',
                                'background',
                                'borderColor',
                                'glow',
                            ] as const"
                            :key="key"
                            class="flex items-end gap-1"
                        >
                            <label class="flex-1 text-xs text-stone-400">
                                {{
                                    {
                                        color: 'Text color',
                                        background: 'Background',
                                        borderColor: 'Border color',
                                        glow: 'Glow',
                                    }[key]
                                }}
                                <input
                                    :value="selected.style[key] ?? ''"
                                    placeholder="#hex or rgba(…)"
                                    class="mt-1 min-h-8 w-full rounded border px-2 text-stone-100"
                                    @change="
                                        (styleField(
                                            key,
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        ),
                                        record())
                                    "
                                />
                            </label>
                            <input
                                type="color"
                                :value="hexOf(selected.style[key])"
                                :aria-label="`Pick ${key}`"
                                class="h-8 w-9 rounded border"
                                @change="
                                    (styleField(
                                        key,
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    ),
                                    record())
                                "
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="text-xs text-stone-400"
                                >Border width<input
                                    type="number"
                                    min="0"
                                    :value="selected.style.borderWidth ?? ''"
                                    class="mt-1 min-h-8 w-full rounded border px-1 text-stone-100"
                                    @input="
                                        styleField(
                                            'borderWidth',
                                            ($event.target as HTMLInputElement)
                                                .value === ''
                                                ? ''
                                                : Number(
                                                      (
                                                          $event.target as HTMLInputElement
                                                      ).value,
                                                  ),
                                        )
                                    "
                                    @change="record"
                            /></label>
                            <label class="text-xs text-stone-400"
                                >Opacity<input
                                    type="number"
                                    min="0"
                                    max="1"
                                    step="0.05"
                                    :value="selected.style.opacity ?? ''"
                                    class="mt-1 min-h-8 w-full rounded border px-1 text-stone-100"
                                    @input="
                                        styleField(
                                            'opacity',
                                            ($event.target as HTMLInputElement)
                                                .value === ''
                                                ? ''
                                                : Number(
                                                      (
                                                          $event.target as HTMLInputElement
                                                      ).value,
                                                  ),
                                        )
                                    "
                                    @change="record"
                            /></label>
                        </div>
                        <label class="flex items-center gap-2 text-xs"
                            ><input
                                type="checkbox"
                                :checked="!!selected.style.shadow"
                                class="size-4"
                                @change="
                                    (styleField(
                                        'shadow',
                                        ($event.target as HTMLInputElement)
                                            .checked || '',
                                    ),
                                    record())
                                "
                            />
                            Text shadow (readable on any background)</label
                        >
                    </template>
                    <template v-else>
                        <p class="font-bold text-amber-400">
                            No element selected
                        </p>
                        <p class="text-stone-400">
                            Click an element on the canvas, or add one from the
                            left. Bind elements to a hero slot so the same
                            layout works for any party; slots follow turn order
                            unless you map them.
                        </p>
                        <p class="text-stone-400">
                            In OBS add a <strong>Browser Source</strong> with
                            the copied URL at {{ config.width }}×{{
                                config.height
                            }}. The background is transparent.
                        </p>
                    </template>
                </aside>
            </div>
        </div>
        <ToastHost />
    </main>
</template>
