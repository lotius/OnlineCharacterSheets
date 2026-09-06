<script setup lang="ts">
import AppHeader from '@/components/AppHeader.vue';
import { echo } from '@/echo';
import { sendRoomViewerHeartbeat } from '@/lib/roomViewers';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, reactive, ref, watch } from 'vue';

type Option = { id: number; name: string };
type Stat = {
    id: number;
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
    slots: Option[];
};
type Ability = {
    id: number;
    name: string;
    description: string | null;
    icon: string | null;
    is_used: boolean;
    charges: number | null;
};
type Character = {
    id: number;
    name: string;
    class_name: string | null;
    gold: number;
    can_update: boolean;
    stats: Stat[];
    trays: Array<{ key: string; name: string; default_open: boolean }>;
    slots: Option[];
    item_definitions: Option[];
    items: Item[];
    spell_definitions: Option[];
    spells: Ability[];
    skill_definitions: Option[];
    skills: Ability[];
    note: { content: string | null; is_private: boolean } | null;
};
type RoomRealtimePayload = {
    room_name: string;
    visibility: string;
};

const props = defineProps<{
    game: { id: number; name: string; is_spectator: boolean };
    character: Character;
}>();
const connectionStatus = ref<'connecting' | 'connected' | 'error'>(
    'connecting',
);
const changedStats = ref<Record<number, boolean>>({});
const goldAdjustment = ref<'add' | 'subtract' | null>(null);
const goldAdjustmentAmount = ref(1);
let stopConnectionListener: (() => void) | null = null;
let subscribedChannelName: string | null = null;
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
const open = reactive(
    Object.fromEntries(
        sheetSections.map((section) => [section.key, false]),
    ) as Record<string, boolean>,
);
const itemForm = useForm({
    item_definition_id: '',
    custom_name: '',
    custom_description: '',
    quantity: 1,
});
const spellForm = useForm({ spell_definition_id: '' });
const skillForm = useForm({ skill_definition_id: '' });
const preserve = { preserveScroll: true } as const;
const stat = (entry: Stat, change: number) =>
    router.patch(
        `/characters/${props.character.id}/stats/${entry.id}`,
        { value: Number(entry.value) + change },
        preserve,
    );
const updateGold = (gold: number) =>
    router.patch(
        `/characters/${props.character.id}/gold`,
        { gold: Math.max(0, gold) },
        preserve,
    );
const openGoldAdjustment = (operation: 'add' | 'subtract') => {
    goldAdjustment.value = operation;
    goldAdjustmentAmount.value = 1;
};
const applyGoldAdjustment = () => {
    const change = Math.max(1, Math.floor(goldAdjustmentAmount.value));
    updateGold(
        goldAdjustment.value === 'subtract'
            ? Math.max(0, props.character.gold - change)
            : props.character.gold + change,
    );
    goldAdjustment.value = null;
};
const quantity = (item: Item, change: number) =>
    router.patch(
        `/characters/${props.character.id}/items/${item.id}`,
        { quantity: item.quantity + change },
        preserve,
    );
const equip = (item: Item, event: Event) => {
    const slot = Number((event.target as HTMLSelectElement).value);
    if (slot)
        router.put(
            `/characters/${props.character.id}/items/${item.id}/slots/${slot}`,
            {},
            preserve,
        );
};
const unequip = (item: Item, slot: Option) =>
    router.delete(
        `/characters/${props.character.id}/items/${item.id}/slots/${slot.id}`,
        preserve,
    );
const toggleActiveItem = (item: Item) =>
    router.patch(
        `/characters/${props.character.id}/items/${item.id}/active`,
        { is_active: !item.is_active },
        preserve,
    );
const toggleAbility = (kind: 'spells' | 'skills', ability: Ability) =>
    router.patch(
        `/characters/${props.character.id}/${kind}/${ability.id}`,
        { is_used: !ability.is_used, charges: ability.charges },
        preserve,
    );
const removeAbility = (kind: 'spells' | 'skills', ability: Ability) =>
    router.delete(
        `/characters/${props.character.id}/${kind}/${ability.id}`,
        preserve,
    );
const currentStatValues = () =>
    Object.fromEntries(
        props.character.stats.map((entry) => [
            entry.id,
            JSON.stringify(entry.value),
        ]),
    );
const flashStat = (id: number) => {
    changedStats.value[id] = false;
    requestAnimationFrame(() => {
        changedStats.value[id] = true;
        window.setTimeout(() => {
            changedStats.value[id] = false;
        }, 700);
    });
};

watch(currentStatValues, (current, previous) => {
    for (const [id, value] of Object.entries(current)) {
        if (previous[id] !== undefined && previous[id] !== value) {
            flashStat(Number(id));
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
                only: ['game', 'character'],
            });
        })
        .subscribed(() => (connectionStatus.value = 'connected'))
        .error(() => (connectionStatus.value = 'error'));

    void sendRoomViewerHeartbeat(props.game.id);
    viewerHeartbeatInterval = window.setInterval(
        () => void sendRoomViewerHeartbeat(props.game.id),
        15000,
    );
});

onUnmounted(() => {
    stopConnectionListener?.();
    if (viewerHeartbeatInterval) window.clearInterval(viewerHeartbeatInterval);
    if (subscribedChannelName) echo?.leave(subscribedChannelName);
});
</script>

<template>
    <Head :title="character.name" />
    <main class="min-h-screen bg-stone-950 text-stone-100">
        <AppHeader :current="game.is_spectator ? 'rooms' : 'dashboard'" />
        <header
            class="sticky top-0 z-10 border-b border-amber-800/40 bg-stone-950/95 px-4 py-4 backdrop-blur"
        >
            <div
                class="mx-auto flex max-w-3xl items-center justify-between gap-4"
            >
                <div>
                    <Link
                        :href="`/games/${game.id}`"
                        class="text-xs text-amber-500"
                        >← {{ game.name }}</Link
                    >
                    <h1 class="hq-rule-heading text-2xl font-black">
                        {{ character.name }}
                    </h1>
                    <p class="text-sm text-stone-400">
                        {{ character.class_name || 'Unclassed' }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div
                        class="flex items-center"
                        :title="`Realtime ${connectionStatus}`"
                        role="status"
                        :aria-label="`Realtime ${connectionStatus}`"
                    >
                        <span
                            class="size-3 rounded-full ring-4 ring-stone-950"
                            :class="{
                                'bg-amber-500':
                                    connectionStatus === 'connecting',
                                'bg-emerald-500':
                                    connectionStatus === 'connected',
                                'bg-red-500': connectionStatus === 'error',
                            }"
                        />
                    </div>
                    <div
                        v-for="entry in character.stats"
                        :key="entry.id"
                        class="min-w-20 border border-stone-800 bg-stone-900 p-2 text-center"
                    >
                        <p
                            class="text-[10px] tracking-wide text-stone-400 uppercase"
                        >
                            {{ entry.name }}
                        </p>
                        <div class="flex items-center justify-center gap-2">
                            <button
                                v-if="character.can_update"
                                :disabled="entry.value <= 0"
                                class="size-7 rounded bg-stone-800 font-bold transition duration-150 enabled:hover:bg-amber-700 enabled:hover:text-stone-950 enabled:active:scale-90 enabled:active:bg-amber-500 disabled:opacity-30"
                                @click="stat(entry, -1)"
                            >
                                −</button
                            ><strong
                                class="rounded-md px-2 py-1 text-amber-400 transition"
                                :class="{
                                    'stat-value-flash': changedStats[entry.id],
                                }"
                                >{{ entry.value
                                }}<small v-if="entry.max_value !== null"
                                    >/{{ entry.max_value }}</small
                                ></strong
                            ><button
                                v-if="character.can_update"
                                :disabled="
                                    entry.max_value !== null &&
                                    entry.value >= entry.max_value
                                "
                                class="size-7 rounded bg-stone-800 font-bold transition duration-150 enabled:hover:bg-amber-700 enabled:hover:text-stone-950 enabled:active:scale-90 enabled:active:bg-amber-500 disabled:opacity-30"
                                @click="stat(entry, 1)"
                            >
                                +
                            </button>
                        </div>
                    </div>
                    <div
                        class="min-w-28 border border-stone-800 bg-stone-900 p-2 text-center"
                    >
                        <p
                            class="text-[10px] tracking-wide text-stone-400 uppercase"
                        >
                            Gold
                        </p>
                        <div class="flex items-center justify-center gap-2">
                            <button
                                v-if="character.can_update"
                                :disabled="character.gold <= 0"
                                class="size-7 rounded bg-stone-800 font-bold transition duration-150 enabled:hover:bg-amber-700 enabled:hover:text-stone-950 enabled:active:scale-90 enabled:active:bg-amber-500 disabled:opacity-30"
                                @click="openGoldAdjustment('subtract')"
                            >
                                −
                            </button>
                            <input
                                v-if="character.can_update"
                                :value="character.gold"
                                type="number"
                                min="0"
                                class="w-16 bg-stone-950 px-1 py-1 text-center font-bold text-amber-400"
                                aria-label="Gold amount"
                                @change="
                                    updateGold(
                                        Number(
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        ),
                                    )
                                "
                            />
                            <strong v-else class="px-2 py-1 text-amber-400">
                                {{ character.gold }}
                            </strong>
                            <button
                                v-if="character.can_update"
                                class="size-7 rounded bg-stone-800 font-bold transition duration-150 hover:bg-amber-700 hover:text-stone-950 active:scale-90 active:bg-amber-500"
                                @click="openGoldAdjustment('add')"
                            >
                                +
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <div
            v-if="goldAdjustment"
            class="fixed inset-0 z-50 flex items-center justify-center bg-stone-950/80 p-4"
            @click.self="goldAdjustment = null"
        >
            <form
                role="dialog"
                aria-modal="true"
                aria-labelledby="gold-adjustment-title"
                class="w-full max-w-sm border border-amber-800/50 bg-stone-900 p-6 shadow-2xl"
                @submit.prevent="applyGoldAdjustment"
            >
                <div class="flex items-center justify-between gap-4">
                    <h2
                        id="gold-adjustment-title"
                        class="hq-rule-heading text-xl font-bold"
                    >
                        {{
                            goldAdjustment === 'add'
                                ? 'Add Gold'
                                : 'Subtract Gold'
                        }}
                    </h2>
                    <button
                        type="button"
                        class="text-2xl text-stone-400 hover:text-stone-100"
                        aria-label="Close gold adjustment"
                        @click="goldAdjustment = null"
                    >
                        ×
                    </button>
                </div>
                <p class="mt-3 text-sm text-stone-400">
                    Current gold: {{ character.gold }}
                </p>
                <label class="mt-4 block text-sm text-stone-300">
                    Amount
                    <input
                        v-model.number="goldAdjustmentAmount"
                        type="number"
                        min="1"
                        :max="
                            goldAdjustment === 'subtract'
                                ? character.gold
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
                        goldAdjustment === 'add' ? 'Add Gold' : 'Subtract Gold'
                    }}
                </button>
            </form>
        </div>
        <div class="mx-auto max-w-3xl space-y-4 px-4 py-6">
            <section>
                <h2 class="hq-rule-heading text-lg font-bold text-amber-400">
                    Active Items
                </h2>
                <div
                    v-if="character.items.some((item) => item.is_active)"
                    class="mt-3 space-y-2"
                >
                    <article
                        v-for="item in character.items.filter(
                            (entry) => entry.is_active,
                        )"
                        :key="item.id"
                        class="flex items-start justify-between gap-3 bg-stone-900 p-3"
                    >
                        <div class="flex min-w-0 gap-3">
                            <img
                                v-if="item.icon"
                                :src="item.icon"
                                alt=""
                                class="size-10 shrink-0 object-contain"
                            />
                            <div>
                                <strong
                                    >{{ item.name }} ×{{
                                        item.quantity
                                    }}</strong
                                >
                                <p
                                    v-if="item.description"
                                    class="text-sm text-stone-400"
                                >
                                    {{ item.description }}
                                </p>
                            </div>
                        </div>
                        <button
                            v-if="character.can_update"
                            type="button"
                            class="shrink-0 text-sm text-amber-400 hover:text-amber-300"
                            @click="toggleActiveItem(item)"
                        >
                            Stop using
                        </button>
                    </article>
                </div>
                <p v-else class="mt-2 text-sm text-stone-500">
                    No active items.
                </p>
            </section>
            <section
                v-for="section in sheetSections"
                :key="section.key"
                class="overflow-hidden border border-stone-800 bg-stone-900"
            >
                <button
                    class="flex w-full items-center gap-3 p-4 text-left text-lg font-bold"
                    :aria-expanded="open[section.key]"
                    @click="open[section.key] = !open[section.key]"
                >
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 24"
                        class="size-4 shrink-0 transition-transform"
                        :class="{ 'rotate-90': open[section.key] }"
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
                    v-if="open[section.key]"
                    class="border-t border-stone-800 p-4"
                >
                    <template v-if="section.key === 'inventory'">
                        <form
                            v-if="character.can_update"
                            class="mb-4 grid gap-2 sm:grid-cols-2"
                            @submit.prevent="
                                itemForm.post(
                                    `/characters/${character.id}/items`,
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => itemForm.reset(),
                                    },
                                )
                            "
                        >
                            <select
                                v-model="itemForm.item_definition_id"
                                class="rounded bg-stone-950 p-3"
                            >
                                <option value="">Custom item</option>
                                <option
                                    v-for="option in character.item_definitions"
                                    :key="option.id"
                                    :value="option.id"
                                >
                                    {{ option.name }}
                                </option></select
                            ><input
                                v-model="itemForm.custom_name"
                                placeholder="Custom name"
                                class="rounded bg-stone-950 p-3"
                            /><input
                                v-model="itemForm.custom_description"
                                placeholder="Description"
                                class="rounded bg-stone-950 p-3 sm:col-span-2"
                            /><button
                                class="rounded bg-amber-600 p-3 font-bold text-stone-950 sm:col-span-2"
                            >
                                Add item
                            </button>
                        </form>
                        <div class="space-y-2">
                            <article
                                v-for="item in character.items"
                                :key="item.id"
                                class="rounded-lg bg-stone-950 p-3"
                            >
                                <div class="flex justify-between gap-3">
                                    <div class="flex min-w-0 gap-3">
                                        <img
                                            v-if="item.icon"
                                            :src="item.icon"
                                            alt=""
                                            class="size-10 shrink-0 object-contain"
                                        />
                                        <div>
                                            <strong>{{ item.name }}</strong>
                                            <p class="text-sm text-stone-400">
                                                {{ item.description }}
                                            </p>
                                            <p
                                                v-if="item.slots.length"
                                                class="mt-1 text-xs text-amber-500"
                                            >
                                                Equipped:
                                                <template
                                                    v-if="character.can_update"
                                                >
                                                    <button
                                                        v-for="slot in item.slots"
                                                        :key="slot.id"
                                                        class="mr-2 underline"
                                                        @click="
                                                            unequip(item, slot)
                                                        "
                                                    >
                                                        {{ slot.name }} ×
                                                    </button>
                                                </template>
                                                <template v-else>
                                                    <span
                                                        v-for="slot in item.slots"
                                                        :key="slot.id"
                                                        class="mr-2"
                                                    >
                                                        {{ slot.name }}
                                                    </span>
                                                </template>
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        v-if="character.can_update"
                                        class="flex items-start gap-2"
                                    >
                                        <button
                                            :disabled="item.quantity <= 1"
                                            @click="quantity(item, -1)"
                                        >
                                            −</button
                                        ><span>{{ item.quantity }}</span
                                        ><button @click="quantity(item, 1)">
                                            +</button
                                        ><button
                                            class="text-red-400"
                                            @click="
                                                router.delete(
                                                    `/characters/${character.id}/items/${item.id}`,
                                                    preserve,
                                                )
                                            "
                                        >
                                            ×
                                        </button>
                                    </div>
                                </div>
                                <select
                                    v-if="character.can_update"
                                    class="mt-2 rounded bg-stone-900 p-2 text-sm"
                                    @change="equip(item, $event)"
                                >
                                    <option value="">Equip to…</option>
                                    <option
                                        v-for="slot in character.slots"
                                        :key="slot.id"
                                        :value="slot.id"
                                    >
                                        {{ slot.name }}
                                    </option>
                                </select>
                                <button
                                    v-if="character.can_update"
                                    type="button"
                                    class="mt-2 border border-stone-700 px-3 py-2 text-sm font-semibold text-amber-400 hover:border-amber-600"
                                    @click="toggleActiveItem(item)"
                                >
                                    {{
                                        item.is_active
                                            ? 'Stop using'
                                            : 'Use item'
                                    }}
                                </button>
                            </article>
                        </div>
                    </template>
                    <template v-else-if="section.key === 'abilities'"
                        ><form
                            v-if="character.can_update"
                            class="mb-3 flex gap-2"
                            @submit.prevent="
                                spellForm.post(
                                    `/characters/${character.id}/spells`,
                                    preserve,
                                )
                            "
                        >
                            <select
                                v-model="spellForm.spell_definition_id"
                                class="flex-1 rounded bg-stone-950 p-3"
                            >
                                <option value="">Choose spell</option>
                                <option
                                    v-for="option in character.spell_definitions"
                                    :key="option.id"
                                    :value="option.id"
                                >
                                    {{ option.name }}
                                </option></select
                            ><button
                                class="rounded bg-amber-600 px-4 text-stone-950"
                            >
                                Add
                            </button>
                        </form>
                        <article
                            v-for="ability in character.spells"
                            :key="ability.id"
                            class="mb-2 rounded bg-stone-950 p-3"
                            :class="{ 'opacity-50': ability.is_used }"
                        >
                            <button
                                v-if="character.can_update"
                                class="w-full text-left"
                                @click="toggleAbility('spells', ability)"
                            >
                                <span class="flex items-center gap-2">
                                    <img
                                        v-if="ability.icon"
                                        :src="ability.icon"
                                        alt=""
                                        class="size-8 object-contain"
                                    />
                                    <strong>{{ ability.name }}</strong>
                                </span>
                                <p class="text-sm text-stone-400">
                                    {{ ability.description }}
                                </p>
                            </button>
                            <div v-else>
                                <span class="flex items-center gap-2">
                                    <img
                                        v-if="ability.icon"
                                        :src="ability.icon"
                                        alt=""
                                        class="size-8 object-contain"
                                    />
                                    <strong>{{ ability.name }}</strong>
                                </span>
                                <p class="text-sm text-stone-400">
                                    {{ ability.description }}
                                </p>
                            </div>
                            <button
                                v-if="character.can_update"
                                class="mt-2 text-xs text-red-400"
                                @click="removeAbility('spells', ability)"
                            >
                                Remove
                            </button>
                        </article></template
                    >
                    <template v-if="section.key === 'abilities'"
                        ><form
                            v-if="character.can_update"
                            class="mb-3 flex gap-2"
                            @submit.prevent="
                                skillForm.post(
                                    `/characters/${character.id}/skills`,
                                    preserve,
                                )
                            "
                        >
                            <select
                                v-model="skillForm.skill_definition_id"
                                class="flex-1 rounded bg-stone-950 p-3"
                            >
                                <option value="">Choose skill</option>
                                <option
                                    v-for="option in character.skill_definitions"
                                    :key="option.id"
                                    :value="option.id"
                                >
                                    {{ option.name }}
                                </option></select
                            ><button
                                class="rounded bg-amber-600 px-4 text-stone-950"
                            >
                                Add
                            </button>
                        </form>
                        <article
                            v-for="ability in character.skills"
                            :key="ability.id"
                            class="mb-2 rounded bg-stone-950 p-3"
                            :class="{ 'opacity-50': ability.is_used }"
                        >
                            <button
                                v-if="character.can_update"
                                class="w-full text-left"
                                @click="toggleAbility('skills', ability)"
                            >
                                <span class="flex items-center gap-2">
                                    <img
                                        v-if="ability.icon"
                                        :src="ability.icon"
                                        alt=""
                                        class="size-8 object-contain"
                                    />
                                    <strong>{{ ability.name }}</strong>
                                </span>
                                <p class="text-sm text-stone-400">
                                    {{ ability.description }}
                                </p>
                            </button>
                            <div v-else>
                                <span class="flex items-center gap-2">
                                    <img
                                        v-if="ability.icon"
                                        :src="ability.icon"
                                        alt=""
                                        class="size-8 object-contain"
                                    />
                                    <strong>{{ ability.name }}</strong>
                                </span>
                                <p class="text-sm text-stone-400">
                                    {{ ability.description }}
                                </p>
                            </div>
                            <button
                                v-if="character.can_update"
                                class="mt-2 text-xs text-red-400"
                                @click="removeAbility('skills', ability)"
                            >
                                Remove
                            </button>
                        </article></template
                    >
                </div>
            </section>
        </div>
    </main>
</template>
