<script setup lang="ts">
import AppHeader from '@/components/AppHeader.vue';
import BottomSheet from '@/components/sheet/BottomSheet.vue';
import SheetRenderer from '@/components/sheet/SheetRenderer.vue';
import ToastHost from '@/components/sheet/ToastHost.vue';
import { sendRoomViewerHeartbeat } from '@/lib/roomViewers';
import { useLiveSync } from '@/sheet/liveSync';
import { prefs, resetPrefs } from '@/sheet/prefs';
import { createSheetStore } from '@/sheet/store';
import type { Character, SystemContext } from '@/sheet/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    game: {
        id: number;
        name: string;
        is_spectator: boolean;
        current_character_id: number | null;
        current_revision: number;
    };
    character: Character;
    system: SystemContext;
}>();

const store = createSheetStore(() => props.game.id);
store.load([props.character]);
watch(
    () => props.character,
    (character) => store.load([character]),
);

const sheet = computed(
    () => store.characters[props.character.id] ?? props.character,
);
const actions = store.actionsFor(props.character.id);
const isTurn = computed(
    () => props.game.current_character_id === props.character.id,
);
const menuOpen = ref(false);
const portraitInput = ref<HTMLInputElement | null>(null);
const blockLabel = (id: string) =>
    props.system.layouts.sheet.find((block) => block.id === id);

const { status, syncError, resync } = useLiveSync({
    gameId: () => props.game.id,
    isSpectator: () => props.game.is_spectator,
    revision: () => props.game.current_revision,
    store,
    only: ['game', 'character'],
});

const uploadPortrait = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) void actions.portraitUpload(file);
    (event.target as HTMLInputElement).value = '';
};

let heartbeat: number | undefined;
onMounted(() => {
    void sendRoomViewerHeartbeat(props.game.id);
    heartbeat = window.setInterval(
        () => void sendRoomViewerHeartbeat(props.game.id),
        15000,
    );
});
onUnmounted(() => window.clearInterval(heartbeat));
</script>

<template>
    <Head :title="sheet.name" />
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

        <header
            class="sticky top-0 z-20 border-b border-amber-800/40 bg-stone-950/95 backdrop-blur"
            :class="{ 'turn-glow': isTurn }"
        >
            <div class="mx-auto flex max-w-2xl items-center gap-3 px-4 py-2">
                <img
                    v-if="sheet.portrait"
                    :src="sheet.portrait"
                    alt=""
                    class="size-11 shrink-0 rounded-full border-2 border-amber-700 object-cover"
                />
                <div class="min-w-0 flex-1">
                    <Link
                        :href="`/games/${game.id}`"
                        class="block truncate text-xs text-amber-500"
                        >← {{ game.name }}</Link
                    >
                    <h1 class="truncate text-xl leading-tight font-black">
                        {{ sheet.name }}
                    </h1>
                    <p class="truncate text-xs text-stone-400">
                        {{ sheet.class_name || 'Unclassed' }}
                        <span v-if="sheet.assigned_user_name">
                            · {{ sheet.assigned_user_name }}</span
                        >
                    </p>
                </div>
                <span
                    v-if="isTurn"
                    class="shrink-0 rounded-full bg-amber-500 px-3 py-1 text-xs font-black text-stone-950 uppercase"
                >
                    {{ sheet.can_update ? 'Your turn' : 'Their turn' }}
                </span>
                <span
                    class="size-3 shrink-0 rounded-full"
                    :class="{
                        'bg-amber-500': status === 'connecting',
                        'bg-emerald-500': status === 'connected',
                        'bg-red-500': status === 'error',
                    }"
                    role="status"
                    :aria-label="`Realtime ${status}`"
                    :title="`Realtime ${status}`"
                />
                <button
                    type="button"
                    class="flex size-11 shrink-0 items-center justify-center rounded-md text-2xl text-stone-300 hover:bg-stone-800"
                    aria-label="View options"
                    @click="menuOpen = true"
                >
                    ⋯
                </button>
            </div>
        </header>

        <div class="mx-auto max-w-2xl px-3 pt-3">
            <SheetRenderer
                :character="sheet"
                :system="system"
                :blocks="system.layouts.sheet"
                mode="sheet"
                layout-name="sheet"
                :actions="game.is_spectator ? null : actions"
                :busy="store.busy"
            />
        </div>

        <BottomSheet
            :open="menuOpen"
            title="View options"
            @close="menuOpen = false"
        >
            <p class="text-sm text-stone-400">
                These only change how the sheet looks on this device.
            </p>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <button
                    type="button"
                    class="min-h-11 rounded-md border"
                    :class="
                        prefs.density === 'comfortable'
                            ? 'border-amber-500 text-amber-400'
                            : 'border-stone-700'
                    "
                    @click="prefs.density = 'comfortable'"
                >
                    Comfortable
                </button>
                <button
                    type="button"
                    class="min-h-11 rounded-md border"
                    :class="
                        prefs.density === 'compact'
                            ? 'border-amber-500 text-amber-400'
                            : 'border-stone-700'
                    "
                    @click="prefs.density = 'compact'"
                >
                    Compact
                </button>
            </div>
            <p class="mt-4 text-sm font-semibold text-stone-300">Sections</p>
            <label
                v-for="block in system.layouts.sheet"
                :key="block.id"
                class="flex min-h-11 items-center gap-3 border-b border-stone-800"
            >
                <input
                    type="checkbox"
                    class="size-5"
                    :checked="!prefs.hidden[`sheet:${block.id}`]"
                    @change="
                        prefs.hidden[`sheet:${block.id}`] = !(
                            $event.target as HTMLInputElement
                        ).checked
                    "
                />
                <span class="capitalize">{{
                    blockLabel(block.id)?.title ?? block.type
                }}</span>
            </label>
            <template v-if="sheet.can_update && !game.is_spectator">
                <p class="mt-4 text-sm font-semibold text-stone-300">
                    Portrait
                </p>
                <input
                    ref="portraitInput"
                    type="file"
                    accept="image/png,image/jpeg,image/webp,image/gif"
                    class="hidden"
                    @change="uploadPortrait"
                />
                <div class="mt-2 flex gap-2">
                    <button
                        type="button"
                        class="min-h-11 flex-1 rounded-md bg-stone-700 font-semibold"
                        :disabled="store.busy.has('portrait')"
                        @click="portraitInput?.click()"
                    >
                        {{
                            store.busy.has('portrait')
                                ? 'Uploading…'
                                : sheet.portrait
                                  ? 'Change portrait'
                                  : 'Upload portrait'
                        }}
                    </button>
                    <button
                        v-if="sheet.portrait"
                        type="button"
                        class="min-h-11 rounded-md border border-stone-700 px-4"
                        @click="actions.portraitRemove()"
                    >
                        Remove
                    </button>
                </div>
            </template>
            <button
                type="button"
                class="mt-4 mb-1 min-h-11 w-full rounded-md border border-stone-700 text-sm text-stone-300"
                @click="resetPrefs"
            >
                Reset view options
            </button>
        </BottomSheet>
        <ToastHost />
    </main>
</template>
