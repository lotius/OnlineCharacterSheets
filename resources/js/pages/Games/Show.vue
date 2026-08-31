<script setup lang="ts">
import { echo } from '@/echo';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

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
    system: string;
    can_manage_members: boolean;
    is_spectator: boolean;
    members: Member[];
    classes: Option[];
    players: Option[];
    characters: Character[];
};

const props = defineProps<{ game: Game }>();
const connectionStatus = ref<'connecting' | 'connected' | 'error'>(
    'connecting',
);
const lastPing = ref<string | null>(null);
const openCharacters = ref<Record<number, boolean>>({});
const openSections = ref<Record<string, boolean>>({});
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
const changeStat = (character: Character, stat: Stat, change: number) =>
    router.patch(
        `/characters/${character.id}/stats/${stat.id}`,
        { value: Number(stat.value) + change },
        { preserveScroll: true },
    );

onMounted(() => {
    if (!echo) {
        connectionStatus.value = 'error';
        return;
    }

    const channel = props.game.is_spectator
        ? echo.channel(`games.public.${props.game.id}`)
        : echo.private(`games.${props.game.id}`);

    channel
        .subscribed(() => (connectionStatus.value = 'connected'))
        .error(() => (connectionStatus.value = 'error'))
        .listen('.room.realtime.ping', (event: { sent_at: string }) => {
            lastPing.value = event.sent_at;
        });
});

onUnmounted(() => echo?.leave(`games.${props.game.id}`));
</script>

<template>
    <Head :title="game.name" />
    <main class="min-h-screen bg-stone-950 px-6 py-10 text-stone-100">
        <div class="mx-auto max-w-6xl">
            <Link
                :href="game.is_spectator ? '/' : '/dashboard'"
                class="text-sm text-amber-500"
                >← {{ game.is_spectator ? 'Home' : 'Dashboard' }}</Link
            >
            <header
                class="mt-5 flex flex-wrap items-start justify-between gap-5 border-b border-stone-800 pb-7"
            >
                <div>
                    <p class="text-sm tracking-widest text-amber-500 uppercase">
                        {{ game.system }}
                    </p>
                    <h1 class="mt-2 text-4xl font-black">{{ game.name }}</h1>
                    <p class="mt-2 text-stone-400">
                        {{ game.status }} · {{ game.visibility }}
                    </p>
                </div>
                <div
                    v-if="game.can_manage_members"
                    class="rounded-xl border border-amber-700/40 bg-stone-900 px-6 py-4 text-center"
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
                <div class="flex items-center gap-3">
                    <span
                        class="size-3 rounded-full"
                        :class="{
                            'bg-amber-500': connectionStatus === 'connecting',
                            'bg-emerald-500': connectionStatus === 'connected',
                            'bg-red-500': connectionStatus === 'error',
                        }"
                    />
                    <span class="text-sm text-stone-400"
                        >Realtime {{ connectionStatus }}</span
                    >
                    <button
                        v-if="!game.is_spectator"
                        type="button"
                        class="rounded border border-stone-700 px-3 py-2 text-sm"
                        @click="
                            router.post(
                                `/games/${game.id}/realtime/ping`,
                                {},
                                { preserveScroll: true },
                            )
                        "
                    >
                        Test
                    </button>
                </div>
            </header>

            <p v-if="lastPing" class="mt-3 text-sm text-emerald-400">
                Realtime ping received at
                {{ new Date(lastPing).toLocaleTimeString() }}.
            </p>

            <section class="mt-8">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-bold">Characters</h2>
                </div>
                <form
                    v-if="game.can_manage_members"
                    class="mt-4 grid gap-3 rounded-xl border border-stone-800 bg-stone-900 p-4 sm:grid-cols-[1fr_1fr_auto]"
                    @submit.prevent="createCharacter"
                >
                    <input
                        v-model="characterForm.name"
                        required
                        placeholder="Character name"
                        class="rounded-lg border border-stone-700 bg-stone-950 px-4 py-3"
                    />
                    <select
                        v-model="characterForm.character_class_definition_id"
                        class="rounded-lg border border-stone-700 bg-stone-950 px-4 py-3"
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
                        class="rounded-lg bg-amber-600 px-5 font-bold text-stone-950"
                    >
                        Create
                    </button>
                </form>
                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    <article
                        v-for="character in game.characters"
                        :key="character.id"
                        class="rounded-2xl border border-stone-800 bg-stone-900 p-5"
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
                                class="rounded border border-stone-700 bg-stone-950 px-3 py-2 text-sm"
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
                            >Open character sheet →</Link
                        >
                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <div
                                v-for="stat in character.stats"
                                :key="stat.id"
                                class="rounded-xl bg-stone-950 p-4 text-center"
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
                                        :disabled="!character.can_update"
                                        class="size-8 rounded bg-stone-800 disabled:opacity-30"
                                        @click="changeStat(character, stat, -1)"
                                    >
                                        −</button
                                    ><strong class="text-2xl text-amber-400"
                                        >{{ stat.value
                                        }}<span
                                            v-if="stat.max_value !== null"
                                            class="text-sm text-stone-500"
                                            >/{{ stat.max_value }}</span
                                        ></strong
                                    ><button
                                        :disabled="!character.can_update"
                                        class="size-8 rounded bg-stone-800 disabled:opacity-30"
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
                                openCharacters[character.id] ? '−' : '+'
                            }}</span>
                        </button>
                        <div
                            v-if="openCharacters[character.id]"
                            class="mt-3 space-y-2"
                        >
                            <section
                                v-for="tray in character.trays"
                                :key="tray.key"
                                class="overflow-hidden rounded-lg border border-stone-800 bg-stone-950"
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
                                            ? '−'
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
                                            class="rounded bg-stone-900 p-3"
                                        >
                                            <strong
                                                >{{ item.name }} ×{{
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
                                            class="rounded bg-stone-900 p-3"
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
                                            class="rounded bg-stone-900 p-3"
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
                    class="mt-5 rounded-xl border border-dashed border-stone-700 p-8 text-center text-stone-400"
                >
                    No characters have been created.
                </p>
            </section>

            <section v-if="!game.is_spectator" class="mt-10">
                <h2 class="text-2xl font-bold">Members</h2>
                <div class="mt-4 space-y-3">
                    <article
                        v-for="member in game.members"
                        :key="member.id"
                        class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-stone-800 bg-stone-900 p-4"
                    >
                        <div>
                            <p class="font-semibold">{{ member.name }}</p>
                            <p class="text-sm text-stone-400">
                                {{ member.role }} · {{ member.status }}
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
                                class="rounded bg-emerald-700 px-3 py-2 text-sm font-semibold"
                                @click="
                                    router.patch(memberUrl(member, '/approve'))
                                "
                            >
                                Approve</button
                            ><button
                                v-if="member.status === 'requested'"
                                class="rounded bg-stone-700 px-3 py-2 text-sm"
                                @click="
                                    router.patch(memberUrl(member, '/reject'))
                                "
                            >
                                Reject</button
                            ><button
                                v-if="member.status === 'active'"
                                class="rounded bg-red-900 px-3 py-2 text-sm"
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
