<script setup lang="ts">
import AppHeader from '@/components/AppHeader.vue';
import { echo } from '@/echo';
import { sendRoomViewerHeartbeat } from '@/lib/roomViewers';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, watch } from 'vue';

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
    quantity: number;
    slots: string[];
};
type Ability = {
    id: number;
    name: string;
    description: string | null;
    is_used: boolean;
};
type Character = {
    session_character_id: number;
    id: number;
    name: string;
    class_name: string | null;
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
type RoomRealtimePayload = {
    room_name: string;
    visibility: string;
};
type RoomViewerCountPayload = {
    viewer_count: number;
};

const props = defineProps<{ game: Game }>();
const connectionStatus = ref<'connecting' | 'connected' | 'error'>(
    'connecting',
);
const changedStats = ref<Record<string, boolean>>({});
let stopConnectionListener: (() => void) | null = null;
let subscribedChannelName: string | null = null;
let viewerHeartbeatInterval: number | null = null;
const openCharacters = ref<Record<number, boolean>>({});
const openSections = ref<Record<string, boolean>>({});
const showRoomTools = ref(false);
const viewerCount = ref(props.game.viewer_count);
const sectionKey = (characterId: number, trayKey: string) =>
    `${characterId}:${trayKey}`;
const toggleCharacter = (character: Character) => {
    openCharacters.value[character.id] = !openCharacters.value[character.id];
    if (openCharacters.value[character.id]) {
        for (const tray of character.trays) {
            const key = sectionKey(character.id, tray.key);
            if (!(key in openSections.value))
                openSections.value[key] = tray.default_open;
        }
    }
};
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
const updateRoomSettings = () =>
    roomSettingsForm.patch(`/games/${props.game.id}`, {
        preserveScroll: true,
    });
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

onMounted(() => {
    if (!echo) {
        connectionStatus.value = 'error';
        return;
    }

    const channel = props.game.is_spectator
        ? echo.channel(`games.public.${props.game.id}`)
        : echo.private(`games.${props.game.id}`);
    subscribedChannelName = props.game.is_spectator
        ? `games.public.${props.game.id}`
        : `games.${props.game.id}`;
    const connection = echo.connector.pusher.connection;
    const updateConnectionStatus = ({ current }: { current: string }) => {
        if (current === 'connected') connectionStatus.value = 'connected';
        else if (current === 'failed' || current === 'unavailable')
            connectionStatus.value = 'error';
        else connectionStatus.value = 'connecting';
    };

    connection.bind('state_change', updateConnectionStatus);
    stopConnectionListener = () =>
        connection.unbind('state_change', updateConnectionStatus);

    channel
        .listen('.room.realtime.ping', (payload: RoomRealtimePayload) => {
            if (
                props.game.is_spectator &&
                payload.visibility !== 'public_view'
            ) {
                router.visit(
                    `/rooms?room_closed_name=${encodeURIComponent(payload.room_name)}`,
                );
                return;
            }

            router.reload({
                only: ['game'],
            });
        })
        .listen(
            '.room.viewer_count.changed',
            (payload: RoomViewerCountPayload) => {
                viewerCount.value = payload.viewer_count;
            },
        )
        .subscribed(() => (connectionStatus.value = 'connected'))
        .error(() => (connectionStatus.value = 'error'));

    void sendViewerHeartbeat();
    viewerHeartbeatInterval = window.setInterval(sendViewerHeartbeat, 15000);
});

onUnmounted(() => {
    stopConnectionListener?.();
    if (viewerHeartbeatInterval) window.clearInterval(viewerHeartbeatInterval);
    if (subscribedChannelName) echo?.leave(subscribedChannelName);
});
</script>

<template>
    <Head :title="game.name" />
    <main class="min-h-screen bg-stone-950 text-stone-100">
        <AppHeader :current="game.is_spectator ? 'rooms' : 'dashboard'" />
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
                        <button
                            v-if="
                                game.can_manage_members ||
                                game.can_manage_settings
                            "
                            type="button"
                            class="size-10 border border-amber-700/50 bg-stone-900 text-xl text-amber-400 transition hover:border-amber-500 hover:bg-amber-700 hover:text-stone-950 active:scale-95"
                            :class="{
                                'border-amber-500 bg-amber-700 text-stone-950':
                                    showRoomTools,
                            }"
                            aria-label="Room tools"
                            title="Room tools"
                            @click="showRoomTools = !showRoomTools"
                        >
                            ⚙
                        </button>
                    </div>
                    <p class="mt-2 text-stone-400">
                        {{ game.visibility }}
                        <span class="mx-2 text-stone-600">|</span>
                        {{ viewerCount }}
                        {{ viewerCount === 1 ? 'watcher' : 'watchers' }}
                    </p>
                </div>
                <div
                    v-if="game.can_manage_members"
                    class="border border-amber-700/40 bg-stone-900 px-6 py-4 text-center"
                >
                    <p class="text-xs tracking-widest text-stone-400 uppercase">
                        Join code
                    </p>
                    <p
                        class="mt-1 font-mono text-2xl font-bold tracking-[0.2em] text-amber-400"
                    >
                        {{ game.join_code }}
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

            <section
                v-if="
                    showRoomTools &&
                    (game.can_manage_members || game.can_manage_settings)
                "
                class="mt-6 border border-amber-800/40 bg-stone-900 p-5 shadow-2xl"
            >
                <div class="grid gap-5 lg:grid-cols-2">
                    <form
                        v-if="game.can_manage_settings"
                        class="space-y-4"
                        @submit.prevent="updateRoomSettings"
                    >
                        <h2 class="hq-rule-heading text-xl font-bold">
                            Room settings
                        </h2>
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
                            <span class="text-sm text-stone-400"
                                >Visibility</span
                            >
                            <select
                                v-model="roomSettingsForm.visibility"
                                class="mt-1 w-full border border-stone-700 bg-stone-950 px-4 py-3"
                            >
                                <option value="private">Private</option>
                                <option value="invite_only">
                                    Join requests
                                </option>
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

                    <form
                        v-if="game.can_manage_members"
                        class="space-y-4"
                        @submit.prevent="createCharacter"
                    >
                        <h2 class="hq-rule-heading text-xl font-bold">
                            Add character
                        </h2>
                        <input
                            v-model="characterForm.name"
                            required
                            placeholder="Character name"
                            class="w-full border border-stone-700 bg-stone-950 px-4 py-3"
                        />
                        <select
                            v-model="
                                characterForm.character_class_definition_id
                            "
                            class="w-full border border-stone-700 bg-stone-950 px-4 py-3"
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
                </div>
            </section>

            <section class="mt-8">
                <div class="flex items-center justify-between">
                    <h2 class="hq-rule-heading text-2xl font-bold">
                        Characters
                    </h2>
                </div>
                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    <article
                        v-for="character in game.characters"
                        :key="character.id"
                        class="border border-stone-800 bg-stone-900 p-5"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-2xl font-bold">
                                    {{ character.name }}
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
                        </div>
                        <button
                            type="button"
                            class="mt-5 flex w-full items-center justify-between border-t border-stone-800 pt-4 text-left font-semibold text-amber-400"
                            @click="toggleCharacter(character)"
                        >
                            <span>Character sheet</span>
                            <span>{{
                                openCharacters[character.id] ? '-' : '+'
                            }}</span>
                        </button>
                        <div
                            v-if="openCharacters[character.id]"
                            class="mt-3 space-y-2"
                        >
                            <section
                                v-for="tray in character.trays"
                                :key="tray.key"
                                class="overflow-hidden border border-stone-800 bg-stone-950"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between p-3 text-left font-semibold"
                                    @click="
                                        openSections[
                                            sectionKey(character.id, tray.key)
                                        ] =
                                            !openSections[
                                                sectionKey(
                                                    character.id,
                                                    tray.key,
                                                )
                                            ]
                                    "
                                >
                                    <span>{{ tray.name }}</span>
                                    <span>{{
                                        openSections[
                                            sectionKey(character.id, tray.key)
                                        ]
                                            ? '-'
                                            : '+'
                                    }}</span>
                                </button>
                                <div
                                    v-if="
                                        openSections[
                                            sectionKey(character.id, tray.key)
                                        ]
                                    "
                                    class="space-y-2 border-t border-stone-800 p-3 text-sm"
                                >
                                    <template
                                        v-if="
                                            tray.key === 'inventory' ||
                                            tray.key === 'equipment'
                                        "
                                    >
                                        <article
                                            v-for="item in character.items"
                                            :key="item.id"
                                            class="bg-stone-900 p-3"
                                        >
                                            <strong
                                                >{{ item.name }} x{{
                                                    item.quantity
                                                }}</strong
                                            >
                                            <p
                                                v-if="item.description"
                                                class="text-stone-400"
                                            >
                                                {{ item.description }}
                                            </p>
                                            <p
                                                v-if="item.slots.length"
                                                class="text-amber-500"
                                            >
                                                Equipped:
                                                {{ item.slots.join(', ') }}
                                            </p>
                                        </article>
                                        <p
                                            v-if="!character.items.length"
                                            class="text-stone-500"
                                        >
                                            No items.
                                        </p>
                                    </template>
                                    <template v-else-if="tray.key === 'spells'">
                                        <article
                                            v-for="ability in character.spells"
                                            :key="ability.id"
                                            class="bg-stone-900 p-3"
                                            :class="{
                                                'opacity-50': ability.is_used,
                                            }"
                                        >
                                            <strong>{{ ability.name }}</strong>
                                            <p class="text-stone-400">
                                                {{ ability.description }}
                                            </p>
                                        </article>
                                        <p
                                            v-if="!character.spells.length"
                                            class="text-stone-500"
                                        >
                                            No spells.
                                        </p>
                                    </template>
                                    <template v-else-if="tray.key === 'skills'">
                                        <article
                                            v-for="ability in character.skills"
                                            :key="ability.id"
                                            class="bg-stone-900 p-3"
                                            :class="{
                                                'opacity-50': ability.is_used,
                                            }"
                                        >
                                            <strong>{{ ability.name }}</strong>
                                            <p class="text-stone-400">
                                                {{ ability.description }}
                                            </p>
                                        </article>
                                        <p
                                            v-if="!character.skills.length"
                                            class="text-stone-500"
                                        >
                                            No skills.
                                        </p>
                                    </template>
                                    <p
                                        v-else-if="tray.key === 'notes'"
                                        class="whitespace-pre-wrap text-stone-300"
                                    >
                                        {{
                                            character.note || 'No public notes.'
                                        }}
                                    </p>
                                    <p v-else class="text-stone-500">
                                        No entries yet.
                                    </p>
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
