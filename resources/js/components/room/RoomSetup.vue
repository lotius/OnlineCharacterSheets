<script setup lang="ts">
import type { Character, Layouts, SystemContext } from '@/sheet/types';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import LayoutEditor from './LayoutEditor.vue';

type Member = { id: number; name: string; role: string; status: string };
type Option = { id: number; name: string };

const props = defineProps<{
    game: {
        id: number;
        name: string;
        join_code: string | null;
        visibility: string;
        can_manage_settings: boolean;
        members: Member[];
        classes: Option[];
        players: Option[];
        overlays: Array<{ id: number; name: string; url: string }>;
        sheet_layouts: {
            room: Partial<Layouts> | null;
            system: Layouts | null;
        };
    };
    characters: Character[];
    system: SystemContext;
}>();

const presets = {
    'hero-column': 'Hero column (left edge)',
    'hero-row': 'Hero cards (bottom row)',
    'current-hero': 'Current hero card',
    blank: 'Blank canvas',
};
const characterForm = useForm({ name: '', character_class_definition_id: '' });
const settingsForm = useForm({
    name: props.game.name,
    visibility: props.game.visibility,
});
const overlayForm = useForm({ name: 'Stream overlay', preset: 'hero-column' });
const copied = ref<string | null>(null);
const confirmRemove = ref<number | null>(null);
const preserve = { preserveScroll: true } as const;

const copy = async (text: string, key: string) => {
    await navigator.clipboard.writeText(text);
    copied.value = key;
    window.setTimeout(() => (copied.value = null), 2000);
};
const assign = (character: Character, event: Event) => {
    const userId = Number((event.target as HTMLSelectElement).value);
    const url = `/games/${props.game.id}/characters/${character.session_character_id}/assignment`;
    if (userId) router.patch(url, { assigned_user_id: userId }, preserve);
    else router.delete(url, preserve);
};
const remove = (character: Character) => {
    if (confirmRemove.value !== character.id) {
        confirmRemove.value = character.id;
        return;
    }
    confirmRemove.value = null;
    router.delete(
        `/games/${props.game.id}/characters/${character.session_character_id}`,
        preserve,
    );
};
const memberUrl = (member: Member, action = '') =>
    `/games/${props.game.id}/members/${member.id}${action}`;
</script>

<template>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <section class="setup-card">
            <h2 class="hq-rule-heading text-lg font-bold">Invite players</h2>
            <div
                class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-md border border-amber-700/40 bg-stone-950 p-3"
            >
                <span
                    class="font-mono text-2xl font-bold tracking-[0.2em] text-amber-400"
                    >{{ game.join_code }}</span
                >
                <button
                    type="button"
                    class="min-h-11 rounded-md bg-amber-600 px-4 font-bold"
                    @click="copy(game.join_code ?? '', 'code')"
                >
                    {{ copied === 'code' ? 'Copied!' : 'Copy code' }}
                </button>
            </div>
            <h3
                class="mt-4 text-sm font-bold tracking-wider text-stone-400 uppercase"
            >
                Members
            </h3>
            <ul class="mt-2 divide-y divide-stone-800">
                <li
                    v-for="member in game.members"
                    :key="member.id"
                    class="flex min-h-12 flex-wrap items-center gap-2 py-1"
                >
                    <span class="flex-1">
                        <span class="font-semibold">{{ member.name }}</span>
                        <span class="ml-2 text-xs text-stone-400"
                            >{{ member.role.replace('_', '-') }} ·
                            {{ member.status }}</span
                        >
                    </span>
                    <template v-if="member.role !== 'dm'">
                        <button
                            v-if="member.status === 'requested'"
                            type="button"
                            class="min-h-10 rounded-md bg-emerald-700 px-3 text-sm font-semibold"
                            @click="
                                router.patch(
                                    memberUrl(member, '/approve'),
                                    {},
                                    preserve,
                                )
                            "
                        >
                            Approve
                        </button>
                        <button
                            v-if="member.status === 'requested'"
                            type="button"
                            class="min-h-10 rounded-md bg-stone-700 px-3 text-sm"
                            @click="
                                router.patch(
                                    memberUrl(member, '/reject'),
                                    {},
                                    preserve,
                                )
                            "
                        >
                            Reject
                        </button>
                        <button
                            v-if="member.status === 'active'"
                            type="button"
                            class="min-h-10 rounded-md border border-red-900 px-3 text-sm text-red-300"
                            @click="router.delete(memberUrl(member), preserve)"
                        >
                            Remove
                        </button>
                    </template>
                </li>
            </ul>
        </section>

        <section class="setup-card">
            <h2 class="hq-rule-heading text-lg font-bold">Characters</h2>
            <p class="mt-1 text-sm text-stone-400">
                Listed in turn order. To change it, drag a card by its handle on
                the Play tab.
            </p>
            <form
                class="mt-3 grid gap-2 sm:grid-cols-[1fr_1fr_auto]"
                @submit.prevent="
                    characterForm.post(`/games/${game.id}/characters`, {
                        preserveScroll: true,
                        onSuccess: () => characterForm.reset(),
                    })
                "
            >
                <input
                    v-model="characterForm.name"
                    required
                    placeholder="Character name"
                    aria-label="Character name"
                    class="min-h-11 rounded-md border px-3"
                />
                <select
                    v-model="characterForm.character_class_definition_id"
                    aria-label="Class"
                    class="min-h-11 rounded-md border px-2"
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
                    class="min-h-11 rounded-md bg-amber-600 px-4 font-bold"
                    :disabled="characterForm.processing"
                >
                    Create
                </button>
            </form>
            <ol class="mt-3 space-y-2">
                <li
                    v-for="(character, index) in characters"
                    :key="character.id"
                    class="flex flex-wrap items-center gap-2 rounded-md bg-stone-950 p-2"
                >
                    <span class="w-5 text-center text-sm text-stone-400">{{
                        index + 1
                    }}</span>
                    <span class="min-w-24 flex-1 truncate font-semibold">{{
                        character.name
                    }}</span>
                    <select
                        :value="character.assigned_user_id || ''"
                        :aria-label="`Player for ${character.name}`"
                        class="min-h-10 max-w-40 rounded border px-2 text-sm"
                        @change="assign(character, $event)"
                    >
                        <option value="">Unassigned</option>
                        <option
                            v-for="player in game.players"
                            :key="player.id"
                            :value="player.id"
                        >
                            {{ player.name }}
                        </option>
                    </select>
                    <button
                        type="button"
                        class="min-h-10 rounded border px-2 text-sm"
                        :class="
                            confirmRemove === character.id
                                ? 'border-red-500 bg-red-900 text-white'
                                : 'border-red-900 text-red-300'
                        "
                        @click="remove(character)"
                    >
                        {{
                            confirmRemove === character.id
                                ? 'Confirm'
                                : 'Remove'
                        }}
                    </button>
                </li>
            </ol>
            <p v-if="!characters.length" class="mt-3 text-sm text-stone-500">
                No characters yet.
            </p>
        </section>

        <section class="setup-card lg:col-span-2">
            <h2 class="hq-rule-heading text-lg font-bold">Sheet layout</h2>
            <p class="mt-1 text-sm text-stone-400">
                Choose what each character sheet shows and in what order.
                Players can still collapse or hide sections on their own device.
            </p>
            <LayoutEditor
                v-if="game.sheet_layouts.system"
                class="mt-3"
                :game-id="game.id"
                :room="game.sheet_layouts.room"
                :defaults="game.sheet_layouts.system"
                :system="system"
            />
        </section>

        <section class="setup-card">
            <h2 class="hq-rule-heading text-lg font-bold">Stream overlays</h2>
            <p class="mt-1 text-sm text-stone-400">
                Add the URL as an OBS Browser Source (1920×1080). Anyone with
                the URL can view it, so treat it like a stream key.
            </p>
            <ul class="mt-3 space-y-2">
                <li
                    v-for="overlay in game.overlays"
                    :key="overlay.id"
                    class="flex flex-wrap items-center gap-2 rounded-md bg-stone-950 p-2"
                >
                    <span class="min-w-0 flex-1 truncate font-semibold">{{
                        overlay.name
                    }}</span>
                    <button
                        type="button"
                        class="min-h-10 rounded-md border border-stone-700 px-3 text-sm"
                        @click="copy(overlay.url, `overlay-${overlay.id}`)"
                    >
                        {{
                            copied === `overlay-${overlay.id}`
                                ? 'Copied!'
                                : 'Copy URL'
                        }}
                    </button>
                    <a
                        :href="`/games/${game.id}/overlays/${overlay.id}/edit`"
                        class="flex min-h-10 items-center rounded-md bg-stone-700 px-3 text-sm font-semibold"
                        >Design</a
                    >
                </li>
            </ul>
            <form
                class="mt-3 grid gap-2 sm:grid-cols-[1fr_1fr_auto]"
                @submit.prevent="overlayForm.post(`/games/${game.id}/overlays`)"
            >
                <input
                    v-model="overlayForm.name"
                    required
                    maxlength="80"
                    aria-label="Overlay name"
                    class="min-h-11 rounded-md border px-3"
                />
                <select
                    v-model="overlayForm.preset"
                    aria-label="Start from"
                    class="min-h-11 rounded-md border px-2"
                >
                    <option
                        v-for="(label, key) in presets"
                        :key="key"
                        :value="key"
                    >
                        {{ label }}
                    </option>
                </select>
                <button
                    class="min-h-11 rounded-md bg-amber-600 px-4 font-bold"
                    :disabled="overlayForm.processing"
                >
                    New overlay
                </button>
            </form>
        </section>

        <section v-if="game.can_manage_settings" class="setup-card">
            <h2 class="hq-rule-heading text-lg font-bold">Room settings</h2>
            <form
                class="mt-3 space-y-3"
                @submit.prevent="
                    settingsForm.patch(`/games/${game.id}`, preserve)
                "
            >
                <label class="block text-sm text-stone-400">
                    Name
                    <input
                        v-model="settingsForm.name"
                        required
                        class="mt-1 min-h-11 w-full rounded-md border px-3 text-stone-100"
                    />
                </label>
                <label class="block text-sm text-stone-400">
                    Visibility
                    <select
                        v-model="settingsForm.visibility"
                        class="mt-1 min-h-11 w-full rounded-md border px-2 text-stone-100"
                    >
                        <option value="private">Private</option>
                        <option value="invite_only">Join requests</option>
                        <option value="public_view">Public spectators</option>
                    </select>
                </label>
                <p v-if="settingsForm.errors.name" class="text-sm text-red-400">
                    {{ settingsForm.errors.name }}
                </p>
                <button
                    class="min-h-11 rounded-md bg-amber-600 px-5 font-bold"
                    :disabled="settingsForm.processing"
                >
                    Save settings
                </button>
            </form>
        </section>
    </div>
</template>
