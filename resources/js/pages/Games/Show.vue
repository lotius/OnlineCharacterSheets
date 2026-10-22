<script setup lang="ts">
import AppHeader from '@/components/AppHeader.vue';
import GameHistory, { type HistoryEvent } from '@/components/GameHistory.vue';
import TurnControls from '@/components/TurnControls.vue';
import { useRoomSync } from '@/lib/roomSync';
import { sendRoomViewerHeartbeat } from '@/lib/roomViewers';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

type Member = { id: number; name: string; role: string; status: string };
type Option = { id: number; name: string };
type Stat = {
    id: number;
    key: string;
    name: string;
    value: number;
    max_value: number | null;
};
type Item = {
    id: number;
    name: string;
    description: string | null;
    icon: string | null;
    quantity: number;
    is_active: boolean;
    slots: string[];
};
type Ability = {
    id: number;
    name: string;
    description: string | null;
    icon: string | null;
    is_used: boolean;
};
type DetailEntry = {
    name: string;
    description: string | null;
    icon: string | null;
};
type Character = {
    session_character_id: number;
    id: number;
    name: string;
    class_name: string | null;
    gold: number;
    assigned_user_id: number | null;
    assigned_user_name: string | null;
    can_update: boolean;
    stats: Stat[];
    trays: Array<{ key: string; name: string; default_open: boolean }>;
    items: Item[];
    spells: Ability[];
    skills: Ability[];
    note: string | null;
};
type Game = {
    game_events: HistoryEvent[];
    current_character_id: number | null;
    current_revision: number;
    turn_events: Array<{
        id: number;
        revision: number;
        event_type: string;
        before_character_id: number | null;
        after_character_id: number | null;
        undone: boolean;
    }>;
    id: number;
    name: string;
    join_code: string;
    status: string;
    visibility: string;
    viewer_count: number;
    system: string;
    can_manage_members: boolean;
    can_manage_settings: boolean;
    is_spectator: boolean;
    members: Member[];
    classes: Option[];
    players: Option[];
    characters: Character[];
};

const props = defineProps<{ game: Game }>();
const changedStats = ref<Record<string, boolean>>({});
let viewerHeartbeatInterval: number | null = null;
const sheetSections = [
    {
        key: 'inventory',
        name: 'Inventory',
    },
    {
        key: 'abilities',
        name: 'Spells / Skills',
    },
] as const;
const openSections = ref<Record<string, boolean>>(
    Object.fromEntries(
        props.game.characters.flatMap((character) =>
            sheetSections.map((section) => [
                `${character.id}:${section.key}`,
                false,
            ]),
        ),
    ),
);
const showRoomSettings = ref(false);
const showInvitePlayers = ref(false);
const joinCodeCopied = ref(false);
const goldAdjustmentCharacter = ref<Character | null>(null);
const goldAdjustmentOperation = ref<'add' | 'subtract'>('add');
const goldAdjustmentAmount = ref(1);
const detailEntry = ref<DetailEntry | null>(null);
const viewerCount = ref(props.game.viewer_count);
const partyGold = computed(() =>
    props.game.characters.reduce(
        (total, character) => total + character.gold,
        0,
    ),
);
const sectionKey = (characterId: number, trayKey: string) =>
    `${characterId}:${trayKey}`;
const characterForm = useForm({ name: '', character_class_definition_id: '' });
const roomSettingsForm = useForm({
    name: props.game.name,
    visibility: props.game.visibility,
});
const createCharacter = () =>
    characterForm.post(`/games/${props.game.id}/characters`, {
        preserveScroll: true,
        onSuccess: () => characterForm.reset(),
    });
const memberUrl = (member: Member, action = '') =>
    `/games/${props.game.id}/members/${member.id}${action}`;
const assignmentUrl = (character: Character) =>
    `/games/${props.game.id}/characters/${character.session_character_id}/assignment`;
const assign = (character: Character, event: Event) => {
    const userId = Number((event.target as HTMLSelectElement).value);
    if (userId)
        router.patch(
            assignmentUrl(character),
            { assigned_user_id: userId },
            { preserveScroll: true },
        );
    else router.delete(assignmentUrl(character), { preserveScroll: true });
};
const removeCharacter = (character: Character) =>
    router.delete(
        `/games/${props.game.id}/characters/${character.session_character_id}`,
        { preserveScroll: true },
    );
const statKey = (character: Character, stat: Stat) =>
    `${character.id}:${stat.id}`;
const currentStatValues = () =>
    Object.fromEntries(
        props.game.characters.flatMap((character) =>
            character.stats.map((stat) => [
                statKey(character, stat),
                JSON.stringify(stat.value),
            ]),
        ),
    );
const flashStat = (key: string) => {
    changedStats.value[key] = false;
    requestAnimationFrame(() => {
        changedStats.value[key] = true;
        window.setTimeout(() => {
            changedStats.value[key] = false;
        }, 700);
    });
};
const changeStat = (character: Character, stat: Stat, change: number) =>
    router.patch(
        `/characters/${character.id}/stats/${stat.id}`,
        { value: Number(stat.value) + change },
        { preserveScroll: true },
    );
const updateGold = (character: Character, gold: number) =>
    router.patch(
        `/characters/${character.id}/gold`,
        { gold: Math.max(0, gold) },
        { preserveScroll: true },
    );
const openGoldAdjustment = (
    character: Character,
    operation: 'add' | 'subtract',
) => {
    goldAdjustmentCharacter.value = character;
    goldAdjustmentOperation.value = operation;
    goldAdjustmentAmount.value = 1;
};
const applyGoldAdjustment = () => {
    if (!goldAdjustmentCharacter.value) return;

    const character = goldAdjustmentCharacter.value;
    const change = Math.max(1, Math.floor(goldAdjustmentAmount.value));
    updateGold(
        character,
        goldAdjustmentOperation.value === 'subtract'
            ? Math.max(0, character.gold - change)
            : character.gold + change,
    );
    goldAdjustmentCharacter.value = null;
};
const toggleActiveItem = (character: Character, item: Item) =>
    router.patch(
        `/characters/${character.id}/items/${item.id}/active`,
        { is_active: !item.is_active },
        { preserveScroll: true },
    );
const showDetails = (entry: DetailEntry) => (detailEntry.value = entry);
const updateRoomSettings = () =>
    roomSettingsForm.patch(`/games/${props.game.id}`, {
        preserveScroll: true,
        onSuccess: () => (showRoomSettings.value = false),
    });
const copyJoinCode = async () => {
    await navigator.clipboard.writeText(props.game.join_code);
    joinCodeCopied.value = true;
    window.setTimeout(() => (joinCodeCopied.value = false), 2000);
};
const sendViewerHeartbeat = async () => {
    const result = await sendRoomViewerHeartbeat(props.game.id);
    if (result) viewerCount.value = result.viewer_count;
};

watch(
    () => props.game,
    (game) => {
        viewerCount.value = game.viewer_count;
        roomSettingsForm.defaults({
            name: game.name,
            visibility: game.visibility,
        });
        roomSettingsForm.name = game.name;
        roomSettingsForm.visibility = game.visibility;
    },
);

watch(currentStatValues, (current, previous) => {
    for (const [key, value] of Object.entries(current)) {
        if (previous[key] !== undefined && previous[key] !== value) {
            flashStat(key);
        }
    }
});

const { status: connectionStatus, syncError, resync } = useRoomSync(() => props.game, {
    only: ['game'],
    onViewerCount: count => { viewerCount.value = count; },
});

onMounted(() => {
    void sendViewerHeartbeat();
    viewerHeartbeatInterval = window.setInterval(sendViewerHeartbeat, 15000);
});

onUnmounted(() => {
    if (viewerHeartbeatInterval) window.clearInterval(viewerHeartbeatInterval);
});
</script>

<template>
    <Head :title="game.name" />
    <main class="min-h-screen bg-stone-950 text-stone-100">
        <AppHeader :current="game.is_spectator ? 'rooms' : 'dashboard'" />
        <div v-if="syncError" role="status" class="bg-amber-950 px-6 py-3 text-sm text-amber-200">
            Updates may be delayed. Reconnecting automatically.
            <button class="ml-3 underline" @click="resync">Retry now</button>
        </div>
        <div class="mx-auto max-w-6xl px-6 py-10">
            <header
                class="flex flex-wrap items-start justify-between gap-5 border-b border-stone-800 pb-7"
            >
                <div class="min-w-0 flex-1">
                    <p class="text-sm tracking-widest text-amber-500 uppercase">
                        {{ game.system }}
                    </p>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <h1 class="hq-rule-heading text-4xl font-black">
                            {{ game.name }}
                        </h1>
                        <div class="flex gap-2">
                            <button
                                v-if="game.can_manage_settings"
                                type="button"
                                class="flex size-10 items-center justify-center border border-amber-700/50 bg-stone-900 text-xl text-amber-400 transition hover:border-amber-500 hover:bg-amber-700 hover:text-stone-950 active:scale-95"
                                aria-label="Room settings"
                                title="Room settings"
                                @click="showRoomSettings = true"
                            >
                                ⚙
                            </button>
                            <button
                                v-if="game.can_manage_members"
                                type="button"
                                class="flex size-10 items-center justify-center border border-amber-700/50 bg-stone-900 text-amber-400 transition hover:border-amber-500 hover:bg-amber-700 hover:text-stone-950 active:scale-95"
                                aria-label="Invite players"
                                title="Invite players"
                                @click="showInvitePlayers = true"
                            >
                                <svg
                                    aria-hidden="true"
                                    viewBox="0 0 24 24"
                                    class="size-5"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="M15 19a6 6 0 0 0-12 0" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M19 8v6M16 11h6" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p class="mt-2 text-stone-400">
                        {{ game.visibility }}
                        <span class="mx-2 text-stone-600">|</span>
                        {{ viewerCount }}
                        {{ viewerCount === 1 ? 'watcher' : 'watchers' }}
                    </p>
                </div>
                <div
                    class="flex items-center"
                    :title="`Realtime ${connectionStatus}`"
                    role="status"
                    :aria-label="`Realtime ${connectionStatus}`"
                >
                    <span
                        class="size-3 rounded-full ring-4 ring-stone-900"
                        :class="{
                            'bg-amber-500': connectionStatus === 'connecting',
                            'bg-emerald-500': connectionStatus === 'connected',
                            'bg-red-500': connectionStatus === 'error',
                        }"
                    />
                </div>
            </header>

            <div
                v-if="goldAdjustmentCharacter"
                class="fixed inset-0 z-50 flex items-center justify-center bg-stone-950/80 p-4"
                @click.self="goldAdjustmentCharacter = null"
            >
                <form
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="room-gold-adjustment-title"
                    class="w-full max-w-sm border border-amber-800/50 bg-stone-900 p-6 shadow-2xl"
                    @submit.prevent="applyGoldAdjustment"
                >
                    <div class="flex items-center justify-between gap-4">
                        <h2
                            id="room-gold-adjustment-title"
                            class="hq-rule-heading text-xl font-bold"
                        >
                            {{
                                goldAdjustmentOperation === 'add'
                                    ? 'Add Gold'
                                    : 'Subtract Gold'
                            }}
                        </h2>
                        <button
                            type="button"
                            class="text-2xl text-stone-400 hover:text-stone-100"
                            aria-label="Close gold adjustment"
                            @click="goldAdjustmentCharacter = null"
                        >
                            ×
                        </button>
                    </div>
                    <p class="mt-3 text-sm text-stone-400">
                        {{ goldAdjustmentCharacter.name }} currently has
                        {{ goldAdjustmentCharacter.gold }} gold.
                    </p>
                    <label class="mt-4 block text-sm text-stone-300">
                        Amount
                        <input
                            v-model.number="goldAdjustmentAmount"
                            type="number"
                            min="1"
                            :max="
                                goldAdjustmentOperation === 'subtract'
                                    ? goldAdjustmentCharacter.gold
                                    : undefined
                            "
                            required
                            autofocus
                            class="mt-1 w-full border border-stone-700 bg-stone-950 p-3 text-xl text-amber-400"
                        />
                    </label>
                    <button
                        class="mt-5 w-full bg-amber-600 p-3 font-bold text-stone-950 hover:bg-amber-500"
                    >
                        {{
                            goldAdjustmentOperation === 'add'
                                ? 'Add Gold'
                                : 'Subtract Gold'
                        }}
                    </button>
                </form>
            </div>
            <div
                v-if="detailEntry"
                class="fixed inset-0 z-50 flex items-center justify-center bg-stone-950/80 p-4"
                @click.self="detailEntry = null"
            >
                <section
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="room-entry-details-title"
                    class="w-full max-w-md border border-amber-800/50 bg-stone-900 p-6 shadow-2xl"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <img
                                v-if="detailEntry.icon"
                                :src="detailEntry.icon"
                                alt=""
                                class="size-12 object-contain"
                            />
                            <h2
                                id="room-entry-details-title"
                                class="hq-rule-heading text-xl font-bold"
                            >
                                {{ detailEntry.name }}
                            </h2>
                        </div>
                        <button
                            type="button"
                            class="text-2xl text-stone-400 hover:text-stone-100"
                            aria-label="Close details"
                            @click="detailEntry = null"
                        >
                            ×
                        </button>
                    </div>
                    <p class="mt-4 whitespace-pre-wrap text-stone-300">
                        {{
                            detailEntry.description || 'No additional details.'
                        }}
                    </p>
                </section>
            </div>

            <div
                v-if="showRoomSettings && game.can_manage_settings"
                class="fixed inset-0 z-50 flex items-center justify-center bg-stone-950/80 p-4"
                @click.self="showRoomSettings = false"
            >
                <form
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="room-settings-title"
                    class="w-full max-w-lg space-y-4 border border-amber-800/50 bg-stone-900 p-6 text-stone-100 shadow-2xl"
                    @submit.prevent="updateRoomSettings"
                >
                    <div class="flex items-center justify-between gap-4">
                        <h2
                            id="room-settings-title"
                            class="hq-rule-heading text-xl font-bold"
                        >
                            Room settings
                        </h2>
                        <button
                            type="button"
                            class="text-2xl text-stone-400 hover:text-stone-100"
                            aria-label="Close room settings"
                            @click="showRoomSettings = false"
                        >
                            ×
                        </button>
                    </div>
                    <label class="block">
                        <span class="text-sm text-stone-400">Name</span>
                        <input
                            v-model="roomSettingsForm.name"
                            required
                            class="mt-1 w-full border border-stone-700 bg-stone-950 px-4 py-3"
                        />
                        <span
                            v-if="roomSettingsForm.errors.name"
                            class="text-sm text-red-400"
                            >{{ roomSettingsForm.errors.name }}</span
                        >
                    </label>
                    <label class="block">
                        <span class="text-sm text-stone-400">Visibility</span>
                        <select
                            v-model="roomSettingsForm.visibility"
                            class="mt-1 w-full border border-stone-700 bg-stone-950 px-4 py-3"
                        >
                            <option value="private">Private</option>
                            <option value="invite_only">Join requests</option>
                            <option value="public_view">
                                Public spectator
                            </option>
                        </select>
                    </label>
                    <button
                        :disabled="roomSettingsForm.processing"
                        class="bg-amber-600 px-4 py-2 font-bold text-stone-950 transition hover:bg-amber-500 active:scale-95 disabled:opacity-50"
                    >
                        Save settings
                    </button>
                </form>
            </div>
            <div
                v-if="showInvitePlayers && game.can_manage_members"
                class="fixed inset-0 z-50 flex items-center justify-center bg-stone-950/80 p-4"
                @click.self="showInvitePlayers = false"
            >
                <section
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="invite-players-title"
                    class="w-full max-w-lg border border-amber-800/50 bg-stone-900 p-6 text-stone-100 shadow-2xl"
                >
                    <div class="flex items-center justify-between gap-4">
                        <h2
                            id="invite-players-title"
                            class="hq-rule-heading text-xl font-bold"
                        >
                            Invite players
                        </h2>
                        <button
                            type="button"
                            class="text-2xl text-stone-400 hover:text-stone-100"
                            aria-label="Close player invitations"
                            @click="showInvitePlayers = false"
                        >
                            ×
                        </button>
                    </div>
                    <p class="mt-4 text-stone-400">
                        Share this join code with the players you want to
                        invite.
                    </p>
                    <div
                        class="mt-5 flex flex-wrap items-center justify-between gap-4 border border-amber-700/40 bg-stone-950 p-4"
                    >
                        <span
                            class="font-mono text-2xl font-bold tracking-[0.2em] text-amber-400"
                            >{{ game.join_code }}</span
                        >
                        <button
                            type="button"
                            class="bg-amber-600 px-4 py-2 font-bold text-stone-950 transition hover:bg-amber-500 active:scale-95"
                            @click="copyJoinCode"
                        >
                            {{ joinCodeCopied ? 'Copied!' : 'Copy code' }}
                        </button>
                    </div>
                </section>
            </div>

            <section class="mt-8">
                <div class="flex items-center justify-between">
                    <h2 class="hq-rule-heading text-2xl font-bold">
                        Characters
                    </h2>
                </div>
                <form
                    v-if="game.can_manage_members"
                    class="mt-5 grid gap-3 border border-stone-800 bg-stone-900 p-4 sm:grid-cols-[1fr_1fr_auto]"
                    @submit.prevent="createCharacter"
                >
                    <input
                        v-model="characterForm.name"
                        required
                        placeholder="Character name"
                        class="border border-stone-700 bg-stone-950 px-4 py-3"
                    />
                    <select
                        v-model="characterForm.character_class_definition_id"
                        class="border border-stone-700 bg-stone-950 px-4 py-3"
                    >
                        <option value="">No class</option>
                        <option
                            v-for="option in game.classes"
                            :key="option.id"
                            :value="option.id"
                        >
                            {{ option.name }}
                        </option>
                    </select>
                    <button
                        class="bg-amber-600 px-5 py-2 font-bold text-stone-950 transition hover:bg-amber-500 active:scale-95"
                    >
                        Create character
                    </button>
                </form>
                <div
                    class="mt-5 border border-amber-700/40 bg-stone-900 px-5 py-4"
                >
                    <p
                        class="text-xs font-semibold tracking-widest text-stone-400 uppercase"
                    >
                        Party Gold
                    </p>
                    <p class="mt-1 text-3xl font-black text-amber-400">
                        {{ partyGold }}
                    </p>
                </div>
                <TurnControls
                    :game-id="game.id"
                    :current-character-id="game.current_character_id"
                    :can-manage="game.can_manage_members"
                    :characters="game.characters"
                    :events="game.turn_events"
                />
                <GameHistory
                    v-if="game.can_manage_members"
                    :game-id="game.id"
                    :events="game.game_events"
                />
                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    <article
                        v-for="character in game.characters"
                        :key="character.id"
                        class="border border-stone-800 bg-stone-900 p-5"
                        :class="{
                            'ring-2 ring-amber-400':
                                game.current_character_id === character.id,
                        }"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-2xl font-bold">
                                    {{ character.name }}
                                    <span
                                        v-if="
                                            game.current_character_id ===
                                            character.id
                                        "
                                        class="block text-sm text-amber-300"
                                        >Current turn</span
                                    >
                                </h3>
                                <p class="text-sm text-stone-400">
                                    {{ character.class_name || 'Unclassed' }}
                                </p>
                            </div>
                            <select
                                v-if="game.can_manage_members"
                                :value="character.assigned_user_id || ''"
                                class="border border-stone-700 bg-stone-950 px-3 py-2 text-sm"
                                @change="assign(character, $event)"
                            >
                                <option value="">Unassigned</option>
                                <option
                                    v-for="player in game.players"
                                    :key="player.id"
                                    :value="player.id"
                                >
                                    {{ player.name }}
                                </option></select
                            ><span v-else class="text-sm text-stone-400">{{
                                character.assigned_user_name || 'Unassigned'
                            }}</span>
                        </div>
                        <Link
                            :href="`/games/${game.id}/characters/${character.id}`"
                            class="mt-3 inline-block text-sm font-semibold text-amber-500"
                            >Open character sheet</Link
                        >
                        <button
                            v-if="game.can_manage_members"
                            class="mt-3 ml-3 text-sm text-red-400 hover:text-red-300"
                            @click="removeCharacter(character)"
                        >
                            Remove
                        </button>
                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <div
                                v-for="stat in character.stats"
                                :key="stat.id"
                                class="bg-stone-950 p-4 text-center"
                            >
                                <p
                                    class="text-xs tracking-wider text-stone-400 uppercase"
                                >
                                    {{ stat.name }}
                                </p>
                                <div
                                    class="mt-2 flex items-center justify-center gap-3"
                                >
                                    <button
                                        v-if="character.can_update"
                                        :disabled="stat.value <= 0"
                                        class="size-8 bg-stone-800 font-bold transition duration-150 enabled:hover:bg-amber-700 enabled:hover:text-stone-950 enabled:active:scale-90 enabled:active:bg-amber-500 disabled:opacity-30"
                                        @click="changeStat(character, stat, -1)"
                                    >
                                        -</button
                                    ><strong
                                        class="px-2 py-1 text-2xl text-amber-400 transition"
                                        :class="{
                                            'stat-value-flash':
                                                changedStats[
                                                    statKey(character, stat)
                                                ],
                                        }"
                                        >{{ stat.value
                                        }}<span
                                            v-if="stat.max_value !== null"
                                            class="text-sm text-stone-500"
                                            >/{{ stat.max_value }}</span
                                        ></strong
                                    ><button
                                        v-if="character.can_update"
                                        :disabled="
                                            stat.max_value !== null &&
                                            stat.value >= stat.max_value
                                        "
                                        class="size-8 bg-stone-800 font-bold transition duration-150 enabled:hover:bg-amber-700 enabled:hover:text-stone-950 enabled:active:scale-90 enabled:active:bg-amber-500 disabled:opacity-30"
                                        @click="changeStat(character, stat, 1)"
                                    >
                                        +
                                    </button>
                                </div>
                            </div>
                            <div class="bg-stone-950 p-4 text-center">
                                <p
                                    class="text-xs tracking-wider text-stone-400 uppercase"
                                >
                                    Gold
                                </p>
                                <div
                                    class="mt-2 flex items-center justify-center gap-3"
                                >
                                    <button
                                        v-if="character.can_update"
                                        :disabled="character.gold <= 0"
                                        class="size-8 bg-stone-800 font-bold transition duration-150 enabled:hover:bg-amber-700 enabled:hover:text-stone-950 enabled:active:scale-90 enabled:active:bg-amber-500 disabled:opacity-30"
                                        @click="
                                            openGoldAdjustment(
                                                character,
                                                'subtract',
                                            )
                                        "
                                    >
                                        −
                                    </button>
                                    <input
                                        v-if="character.can_update"
                                        :value="character.gold"
                                        type="number"
                                        min="0"
                                        class="w-20 bg-stone-900 px-2 py-1 text-center text-2xl font-bold text-amber-400"
                                        aria-label="Gold amount"
                                        @change="
                                            updateGold(
                                                character,
                                                Number(
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                ),
                                            )
                                        "
                                    />
                                    <strong
                                        v-else
                                        class="px-2 py-1 text-2xl text-amber-400"
                                    >
                                        {{ character.gold }}
                                    </strong>
                                    <button
                                        v-if="character.can_update"
                                        class="size-8 bg-stone-800 font-bold transition duration-150 hover:bg-amber-700 hover:text-stone-950 active:scale-90 active:bg-amber-500"
                                        @click="
                                            openGoldAdjustment(character, 'add')
                                        "
                                    >
                                        +
                                    </button>
                                </div>
                            </div>
                        </div>
                        <section class="mt-3">
                            <h4 class="font-bold text-amber-400">
                                Active Items
                            </h4>
                            <div
                                v-if="
                                    character.items.some(
                                        (item) => item.is_active,
                                    )
                                "
                                class="mt-2 space-y-2"
                            >
                                <article
                                    v-for="item in character.items.filter(
                                        (entry) => entry.is_active,
                                    )"
                                    :key="item.id"
                                    class="flex cursor-pointer items-start justify-between gap-3 bg-stone-950 p-3"
                                    :title="item.description || item.name"
                                    @click="showDetails(item)"
                                >
                                    <div class="flex min-w-0 gap-3">
                                        <img
                                            v-if="item.icon"
                                            :src="item.icon"
                                            alt=""
                                            class="size-9 shrink-0 object-contain"
                                        />
                                        <div>
                                            <strong>{{ item.name }}</strong>
                                        </div>
                                    </div>
                                    <button
                                        v-if="character.can_update"
                                        type="button"
                                        class="shrink-0 text-sm text-amber-400 hover:text-amber-300"
                                        @click.stop="
                                            toggleActiveItem(character, item)
                                        "
                                    >
                                        Stop using
                                    </button>
                                </article>
                            </div>
                            <p v-else class="mt-1 text-sm text-stone-500">
                                No active items.
                            </p>
                        </section>
                        <div class="mt-2 space-y-2">
                            <section
                                v-for="section in sheetSections"
                                :key="section.key"
                                class="overflow-hidden border border-stone-800 bg-stone-950"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-3 p-3 text-left font-semibold"
                                    :aria-expanded="
                                        openSections[
                                            sectionKey(
                                                character.id,
                                                section.key,
                                            )
                                        ]
                                    "
                                    @click="
                                        openSections[
                                            sectionKey(
                                                character.id,
                                                section.key,
                                            )
                                        ] =
                                            !openSections[
                                                sectionKey(
                                                    character.id,
                                                    section.key,
                                                )
                                            ]
                                    "
                                >
                                    <svg
                                        aria-hidden="true"
                                        viewBox="0 0 24 24"
                                        class="size-4 shrink-0 transition-transform"
                                        :class="{
                                            'rotate-90':
                                                openSections[
                                                    sectionKey(
                                                        character.id,
                                                        section.key,
                                                    )
                                                ],
                                        }"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path d="m9 18 6-6-6-6" />
                                    </svg>
                                    <span>{{ section.name }}</span>
                                </button>
                                <div
                                    v-if="
                                        openSections[
                                            sectionKey(
                                                character.id,
                                                section.key,
                                            )
                                        ]
                                    "
                                    class="space-y-2 border-t border-stone-800 p-3 text-sm"
                                >
                                    <template
                                        v-if="section.key === 'inventory'"
                                    >
                                        <article
                                            v-for="item in character.items.filter(
                                                (entry) =>
                                                    entry.quantity -
                                                        Number(
                                                            entry.is_active,
                                                        ) >
                                                    0,
                                            )"
                                            :key="item.id"
                                            class="bg-stone-900 p-3"
                                            :title="
                                                item.description || item.name
                                            "
                                        >
                                            <div
                                                class="flex items-center justify-between gap-3"
                                            >
                                                <button
                                                    type="button"
                                                    class="flex min-w-0 items-center gap-2 text-left"
                                                    @click="showDetails(item)"
                                                >
                                                    <img
                                                        v-if="item.icon"
                                                        :src="item.icon"
                                                        alt=""
                                                        class="size-8 shrink-0 object-contain"
                                                    />
                                                    <strong
                                                        >{{ item.name }} x{{
                                                            item.quantity -
                                                            Number(
                                                                item.is_active,
                                                            )
                                                        }}</strong
                                                    >
                                                </button>
                                                <button
                                                    v-if="character.can_update"
                                                    type="button"
                                                    class="shrink-0 border border-stone-700 px-2 py-1 text-xs font-semibold text-amber-400 hover:border-amber-600"
                                                    @click="
                                                        toggleActiveItem(
                                                            character,
                                                            item,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        item.is_active
                                                            ? 'Stop using'
                                                            : 'Use item'
                                                    }}
                                                </button>
                                            </div>
                                            <p
                                                v-if="item.slots.length"
                                                class="text-amber-500"
                                            >
                                                Equipped:
                                                {{ item.slots.join(', ') }}
                                            </p>
                                        </article>
                                        <p
                                            v-if="
                                                !character.items.some(
                                                    (item) =>
                                                        item.quantity -
                                                            Number(
                                                                item.is_active,
                                                            ) >
                                                        0,
                                                )
                                            "
                                            class="text-stone-500"
                                        >
                                            No items.
                                        </p>
                                    </template>
                                    <template
                                        v-else-if="section.key === 'abilities'"
                                    >
                                        <article
                                            v-for="ability in character.spells"
                                            :key="ability.id"
                                            class="bg-stone-900 p-3"
                                            :title="
                                                ability.description ||
                                                ability.name
                                            "
                                            :class="{
                                                'opacity-50': ability.is_used,
                                            }"
                                        >
                                            <button
                                                type="button"
                                                class="flex items-center gap-2 text-left"
                                                @click="showDetails(ability)"
                                            >
                                                <img
                                                    v-if="ability.icon"
                                                    :src="ability.icon"
                                                    alt=""
                                                    class="size-8 object-contain"
                                                />
                                                <strong>{{
                                                    ability.name
                                                }}</strong>
                                            </button>
                                        </article>
                                        <p
                                            v-if="!character.spells.length"
                                            class="text-stone-500"
                                        >
                                            No spells.
                                        </p>
                                    </template>
                                    <template
                                        v-if="section.key === 'abilities'"
                                    >
                                        <article
                                            v-for="ability in character.skills"
                                            :key="ability.id"
                                            class="bg-stone-900 p-3"
                                            :title="
                                                ability.description ||
                                                ability.name
                                            "
                                            :class="{
                                                'opacity-50': ability.is_used,
                                            }"
                                        >
                                            <button
                                                type="button"
                                                class="flex items-center gap-2 text-left"
                                                @click="showDetails(ability)"
                                            >
                                                <img
                                                    v-if="ability.icon"
                                                    :src="ability.icon"
                                                    alt=""
                                                    class="size-8 object-contain"
                                                />
                                                <strong>{{
                                                    ability.name
                                                }}</strong>
                                            </button>
                                        </article>
                                        <p
                                            v-if="!character.skills.length"
                                            class="text-stone-500"
                                        >
                                            No skills.
                                        </p>
                                    </template>
                                </div>
                            </section>
                        </div>
                    </article>
                </div>
                <p
                    v-if="!game.characters.length"
                    class="mt-5 border border-dashed border-stone-700 p-8 text-center text-stone-400"
                >
                    No characters have been created.
                </p>
            </section>

            <section v-if="!game.is_spectator" class="mt-10">
                <h2 class="hq-rule-heading text-2xl font-bold">Members</h2>
                <div class="mt-4 space-y-3">
                    <article
                        v-for="member in game.members"
                        :key="member.id"
                        class="flex flex-wrap items-center justify-between gap-4 border border-stone-800 bg-stone-900 p-4"
                    >
                        <div>
                            <p class="font-semibold">{{ member.name }}</p>
                            <p class="text-sm text-stone-400">
                                {{ member.role }} - {{ member.status }}
                            </p>
                        </div>
                        <div
                            v-if="
                                game.can_manage_members && member.role !== 'dm'
                            "
                            class="flex gap-2"
                        >
                            <button
                                v-if="member.status === 'requested'"
                                class="bg-emerald-700 px-3 py-2 text-sm font-semibold"
                                @click="
                                    router.patch(memberUrl(member, '/approve'))
                                "
                            >
                                Approve</button
                            ><button
                                v-if="member.status === 'requested'"
                                class="bg-stone-700 px-3 py-2 text-sm"
                                @click="
                                    router.patch(memberUrl(member, '/reject'))
                                "
                            >
                                Reject</button
                            ><button
                                v-if="member.status === 'active'"
                                class="bg-red-900 px-3 py-2 text-sm"
                                @click="router.delete(memberUrl(member))"
                            >
                                Remove
                            </button>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </main>
</template>
