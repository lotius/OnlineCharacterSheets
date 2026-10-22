<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, onUnmounted, ref } from 'vue';

export type HistoryEvent = {
    id: number;
    revision: number;
    event_type: string;
    character_name: string | null;
    detail: string | null;
    can_undo: boolean;
    undone: boolean;
    created_at: string | null;
};
const props = defineProps<{ gameId: number; events: HistoryEvent[] }>();
const form = useForm({});
const older = ref<HistoryEvent[]>([]);
const loading = ref(false);
const loadError = ref(false);
const finished = ref(false);
const controller = new AbortController();
const entries = computed(() => [...new Map([...older.value, ...props.events].map(event => [event.id, event])).values()].sort((a, b) => b.revision - a.revision));
async function loadOlder() {
    if (loading.value || !entries.value.length) return;
    loading.value = true;
    loadError.value = false;
    try {
        const before = entries.value[entries.value.length - 1].revision;
        const response = await fetch(`/games/${props.gameId}/events?before_revision=${before}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
        if (!response.ok) throw new Error('History unavailable');
        const page = await response.json() as { data: HistoryEvent[]; next_before_revision: number | null };
        older.value.push(...page.data);
        finished.value = page.next_before_revision === null;
    } catch (error) {
        if (!(error instanceof DOMException && error.name === 'AbortError')) loadError.value = true;
    } finally {
        loading.value = false;
    }
}
onUnmounted(() => controller.abort());
</script>

<template>
    <details class="my-4 rounded-xl border border-stone-700 bg-stone-900 p-4">
        <summary class="font-bold">Game history</summary>
        <p v-if="!events.length" class="mt-3 text-stone-400">
            No changes recorded yet.
        </p>
        <p
            v-for="(error, key) in form.errors"
            :key="key"
            class="mt-3 text-red-300"
        >
            {{ error }}
        </p>
        <ol class="mt-3 space-y-3">
            <li
                v-for="event in entries"
                :key="event.id"
                class="flex flex-wrap items-center gap-3 text-sm"
            >
                <span class="text-stone-400">#{{ event.revision }}</span>
                <span
                    >{{ event.character_name }}
                    {{
                        event.event_type
                            .replaceAll('.', ' ')
                            .replaceAll('_', ' ')
                    }}</span
                >
                <span v-if="event.detail" class="text-amber-200">{{
                    event.detail
                }}</span>
                <span v-if="event.undone" class="text-stone-400">Undone</span>
                <button
                    v-else-if="event.can_undo"
                    :disabled="form.processing"
                    class="rounded border border-stone-600 px-3 py-2 disabled:opacity-40"
                    @click="
                        form.post(`/games/${gameId}/events/${event.id}/undo`, {
                            preserveScroll: true,
                        })
                    "
                >
                    Undo
                </button>
            </li>
        </ol>
        <p class="mt-3 text-xs text-stone-400">
            Undo is available while the affected state is unchanged.
        </p>
        <p v-if="loadError" class="mt-3 text-red-300">Could not load older history. Please retry.</p>
        <button v-if="!finished && entries.length >= 20" :disabled="loading" class="mt-3 rounded border border-stone-600 px-3 py-2" @click="loadOlder">{{ loading ? 'Loading…' : 'Load older changes' }}</button>
    </details>
</template>
