<script setup lang="ts">
import AppHeader from '@/components/AppHeader.vue';
import GameHistory, { type HistoryEvent } from '@/components/GameHistory.vue';
import CharacterCard from '@/components/room/CharacterCard.vue';
import RoomSetup from '@/components/room/RoomSetup.vue';
import TurnBar from '@/components/room/TurnBar.vue';
import ToastHost from '@/components/sheet/ToastHost.vue';
import { useDragSort } from '@/lib/dragSort';
import { sendRoomViewerHeartbeat } from '@/lib/roomViewers';
import { useLiveSync } from '@/sheet/liveSync';
import { createSheetStore, type SheetActions } from '@/sheet/store';
import { toast } from '@/sheet/toasts';
import type { Character, Layouts, SystemContext } from '@/sheet/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

type Game = {
    id: number;
    name: string;
    join_code: string | null;
    status: string;
    visibility: string;
    viewer_count: number;
    system: string;
    current_character_id: number | null;
    current_revision: number;
    can_manage_members: boolean;
    can_manage_settings: boolean;
    is_spectator: boolean;
    my_user_id: number | null;
    card_columns: number | null;
    members: Array<{ id: number; name: string; role: string; status: string }>;
    classes: Array<{ id: number; name: string }>;
    players: Array<{ id: number; name: string }>;
    overlays: Array<{ id: number; name: string; url: string }>;
    game_events: HistoryEvent[];
    characters: Character[];
    sheet_layouts: { room: Partial<Layouts> | null; system: Layouts | null };
};

const props = defineProps<{ game: Game; system: SystemContext }>();

const store = createSheetStore(() => props.game.id);
store.load(props.game.characters);
watch(
    () => props.game.characters,
    (characters) => store.load(characters),
);

// Turn order: dragging reorders locally right away; the server order replaces it once saved.
const savedOrder = ref<number[] | null>(null);
watch(
    () => props.game.characters,
    () => (savedOrder.value = null),
);
const sort = useDragSort({
    ids: () => characters.value.map((character) => character.id),
    onDrop: (ids) => {
        savedOrder.value = ids;
        router.put(
            `/games/${props.game.id}/turn/order`,
            { character_ids: ids },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['game'],
                onError: (errors) => {
                    savedOrder.value = null;
                    toast(
                        Object.values(errors)[0] ??
                            'The turn order could not be saved.',
                        'error',
                    );
                },
            },
        );
    },
});
const characters = computed(() => {
    const live = props.game.characters.map(
        (character) => store.characters[character.id] ?? character,
    );
    const order = sort.order.value ?? savedOrder.value;
    if (!order) return live;
    return order
        .map((id) => live.find((character) => character.id === id))
        .filter((character): character is Character => !!character);
});
const actionCache = new Map<number, SheetActions>();
const actionsFor = (id: number) => {
    if (!actionCache.has(id)) actionCache.set(id, store.actionsFor(id));
    return actionCache.get(id)!;
};
const tab = ref<'play' | 'setup' | 'history'>('play');

// Cards per row (room setting; phones always show one). Applied instantly, then saved for everyone.
const columnClasses: Record<string, string> = {
    auto: 'md:grid-cols-2 xl:grid-cols-3',
    1: 'md:grid-cols-1 md:max-w-3xl md:mx-auto',
    2: 'md:grid-cols-2',
    3: 'md:grid-cols-2 lg:grid-cols-3',
    4: 'md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4',
};
const columnsOverride = ref<number | null | undefined>(undefined);
watch(
    () => props.game.card_columns,
    () => (columnsOverride.value = undefined),
);
const columns = computed(() =>
    columnsOverride.value === undefined
        ? props.game.card_columns
        : columnsOverride.value,
);
const setColumns = (value: number | null) => {
    columnsOverride.value = value;
    router.patch(
        `/games/${props.game.id}/display`,
        { card_columns: value },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['game'],
            onError: () => {
                columnsOverride.value = undefined;
                toast('The layout could not be saved.', 'error');
            },
        },
    );
};
const viewerCount = ref(props.game.viewer_count);
const pendingRequests = computed(
    () =>
        props.game.members.filter((member) => member.status === 'requested')
            .length,
);
watch(
    () => props.game.viewer_count,
    (count) => (viewerCount.value = count),
);

const { status, syncError, resync } = useLiveSync({
    gameId: () => props.game.id,
    isSpectator: () => props.game.is_spectator,
    revision: () => props.game.current_revision,
    store,
    only: ['game', 'system'],
    onViewerCount: (count) => (viewerCount.value = count),
});

let heartbeat: number | undefined;
const beat = async () => {
    const result = await sendRoomViewerHeartbeat(props.game.id);
    if (result) viewerCount.value = result.viewer_count;
};
onMounted(() => {
    void beat();
    heartbeat = window.setInterval(beat, 15000);
});
onUnmounted(() => window.clearInterval(heartbeat));
</script>

<template>
    <Head :title="game.name" />
    <main class="min-h-screen bg-stone-950 pb-24 text-stone-100">
        <AppHeader :current="game.is_spectator ? 'rooms' : 'dashboard'" />
        <div
            v-if="syncError"
            role="status"
            class="bg-amber-950 px-4 py-2 text-sm text-amber-200"
        >
            Updates may be delayed. Reconnecting automatically.
            <button class="ml-2 underline" @click="resync">Retry now</button>
        </div>

        <div class="mx-auto max-w-7xl px-3 pt-3 sm:px-5">
            <header class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <div class="min-w-0 flex-1">
                    <p class="text-xs tracking-widest text-amber-500 uppercase">
                        {{ game.system }}
                    </p>
                    <h1 class="truncate text-2xl font-black sm:text-3xl">
                        {{ game.name }}
                    </h1>
                </div>
                <p class="flex items-center gap-2 text-sm text-stone-400">
                    <span class="capitalize">{{
                        game.visibility.replace('_', ' ')
                    }}</span>
                    <span aria-hidden="true">·</span>
                    {{ viewerCount }}
                    {{ viewerCount === 1 ? 'watcher' : 'watchers' }}
                    <span
                        class="size-3 rounded-full"
                        :class="{
                            'bg-amber-500': status === 'connecting',
                            'bg-emerald-500': status === 'connected',
                            'bg-red-500': status === 'error',
                        }"
                        role="status"
                        :aria-label="`Realtime ${status}`"
                        :title="`Realtime ${status}`"
                    />
                </p>
            </header>

            <nav
                v-if="game.can_manage_members"
                class="mt-3 flex gap-1 border-b border-stone-800"
                role="tablist"
            >
                <button
                    v-for="entry in [
                        { key: 'play', label: 'Play' },
                        {
                            key: 'setup',
                            label: pendingRequests
                                ? `Setup (${pendingRequests})`
                                : 'Setup',
                        },
                        { key: 'history', label: 'History' },
                    ] as const"
                    :key="entry.key"
                    type="button"
                    role="tab"
                    :aria-selected="tab === entry.key"
                    class="-mb-px min-h-11 border-b-2 px-4 text-sm font-bold"
                    :class="
                        tab === entry.key
                            ? 'border-amber-500 text-amber-400'
                            : 'border-transparent text-stone-400'
                    "
                    @click="tab = entry.key"
                >
                    {{ entry.label }}
                </button>
            </nav>

            <template v-if="tab === 'play'">
                <div
                    class="sticky top-0 z-20 -mx-3 bg-stone-950/90 px-3 py-2 backdrop-blur sm:-mx-5 sm:px-5"
                >
                    <TurnBar
                        :game-id="game.id"
                        :characters="characters"
                        :current-id="game.current_character_id"
                        :can-manage="game.can_manage_members"
                        :my-user-id="game.my_user_id"
                    />
                </div>
                <div
                    v-if="game.can_manage_members && characters.length > 1"
                    class="mb-2 hidden items-center justify-end gap-2 text-xs text-stone-300 md:flex"
                >
                    Cards per row
                    <div
                        class="flex overflow-hidden rounded-md border border-stone-700"
                        role="group"
                        aria-label="Cards per row"
                    >
                        <button
                            v-for="option in [null, 1, 2, 3, 4]"
                            :key="String(option)"
                            type="button"
                            class="min-h-8 min-w-9 px-2 font-semibold"
                            :class="
                                columns === option
                                    ? 'bg-amber-600 text-stone-950'
                                    : 'bg-stone-900 hover:text-amber-400'
                            "
                            :aria-pressed="columns === option"
                            @click="setColumns(option)"
                        >
                            {{ option ?? 'Auto' }}
                        </button>
                    </div>
                </div>
                <TransitionGroup
                    tag="div"
                    move-class="transition-transform duration-200"
                    class="grid grid-cols-1 gap-3"
                    :class="[
                        columnClasses[columns ?? 'auto'],
                        { 'select-none': sort.draggingId.value !== null },
                    ]"
                >
                    <CharacterCard
                        v-for="(character, index) in characters"
                        :key="character.id"
                        :game-id="game.id"
                        :character="character"
                        :system="system"
                        :is-current="game.current_character_id === character.id"
                        :is-mine="
                            game.my_user_id !== null &&
                            character.assigned_user_id === game.my_user_id
                        "
                        :actions="
                            game.is_spectator ? null : actionsFor(character.id)
                        "
                        :busy="store.busy"
                        :position="index + 1"
                        :sortable="game.can_manage_members"
                        :dragging="sort.draggingId.value === character.id"
                        @grab="sort.start(character.id, $event)"
                        @nudge="sort.nudge(character.id, $event)"
                    />
                </TransitionGroup>
                <div
                    v-if="!characters.length"
                    class="mt-4 rounded-lg border border-dashed border-stone-700 p-8 text-center text-stone-400"
                >
                    No characters yet.
                    <button
                        v-if="game.can_manage_members"
                        type="button"
                        class="ml-1 text-amber-400 underline"
                        @click="tab = 'setup'"
                    >
                        Create one in Setup.
                    </button>
                </div>
            </template>

            <div v-else-if="tab === 'setup'" class="mt-4">
                <RoomSetup
                    :game="game"
                    :characters="characters"
                    :system="system"
                />
            </div>

            <div v-else class="mt-4">
                <GameHistory :game-id="game.id" :events="game.game_events" />
            </div>
        </div>
        <ToastHost />
    </main>
</template>
