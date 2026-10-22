<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

type TurnEvent = {
    id: number;
    revision: number;
    event_type: string;
    before_character_id: number | null;
    after_character_id: number | null;
    undone: boolean;
};
const props = defineProps<{
    gameId: number;
    currentCharacterId: number | null;
    canManage: boolean;
    characters: Array<{ id: number; name: string }>;
    events: TurnEvent[];
}>();
const form = useForm({
    direction: 'next',
    character_id: null as number | null,
});
const orderForm = useForm({ character_ids: [] as number[] });
function move(index: number, offset: number) {
    const ids = props.characters.map((character) => character.id);
    const destination = index + offset;
    if (destination < 0 || destination >= ids.length) return;
    [ids[index], ids[destination]] = [ids[destination], ids[index]];
    orderForm.character_ids = ids;
    orderForm.put(`/games/${props.gameId}/turn/order`, {
        preserveScroll: true,
    });
}
const name = (id: number | null) =>
    id === null
        ? 'Not started'
        : (props.characters.find((character) => character.id === id)?.name ??
          'Removed character');
function change(direction: string, characterId: number | null = null) {
    form.direction = direction;
    form.character_id = characterId;
    form.patch(`/games/${props.gameId}/turn`, { preserveScroll: true });
}
function select(event: Event) {
    change('select', Number((event.target as HTMLSelectElement).value));
}
</script>

<template>
    <section
        class="my-4 rounded-xl border border-amber-800 bg-stone-900 p-4"
        aria-label="Turn tracker"
    >
        <p class="font-bold text-amber-200" aria-live="polite">
            Current turn: {{ name(currentCharacterId) }}
        </p>
        <div v-if="canManage" class="mt-3 flex flex-wrap gap-3">
            <button
                :disabled="form.processing || !characters.length"
                class="rounded border border-stone-600 px-3 py-2 disabled:opacity-40"
                @click="change('previous')"
            >
                Previous
            </button>
            <button
                :disabled="form.processing || !characters.length"
                class="rounded border border-stone-600 px-3 py-2 disabled:opacity-40"
                @click="change('next')"
            >
                Next
            </button>
            <button
                :disabled="form.processing || !characters.length"
                class="rounded border border-stone-600 px-3 py-2 disabled:opacity-40"
                @click="change('first')"
            >
                Reset to first
            </button>
            <select
                aria-label="Select current character"
                :value="currentCharacterId ?? ''"
                :disabled="form.processing || !characters.length"
                class="rounded bg-stone-800 p-2"
                @change="select"
            >
                <option disabled value="">Choose character</option>
                <option
                    v-for="character in characters"
                    :key="character.id"
                    :value="character.id"
                >
                    {{ character.name }}
                </option>
            </select>
        </div>
        <p
            v-for="(error, key) in form.errors"
            :key="key"
            class="mt-2 text-red-300"
        >
            {{ error }}
        </p>
        <details v-if="canManage && characters.length" class="mt-4">
            <summary>Edit turn order</summary>
            <p
                v-for="(error, key) in orderForm.errors"
                :key="key"
                class="mt-2 text-red-300"
            >
                {{ error }}
            </p>
            <ol class="mt-3 space-y-2">
                <li
                    v-for="(character, index) in characters"
                    :key="character.id"
                    class="flex items-center gap-3"
                >
                    <span class="flex-1"
                        >{{ index + 1 }}. {{ character.name }}</span
                    >
                    <button
                        :aria-label="`Move ${character.name} earlier`"
                        :disabled="orderForm.processing || index === 0"
                        class="rounded border border-stone-600 px-3 py-2 disabled:opacity-40"
                        @click="move(index, -1)"
                    >
                        Up
                    </button>
                    <button
                        :aria-label="`Move ${character.name} later`"
                        :disabled="
                            orderForm.processing ||
                            index === characters.length - 1
                        "
                        class="rounded border border-stone-600 px-3 py-2 disabled:opacity-40"
                        @click="move(index, 1)"
                    >
                        Down
                    </button>
                </li>
            </ol>
        </details>
    </section>
</template>
