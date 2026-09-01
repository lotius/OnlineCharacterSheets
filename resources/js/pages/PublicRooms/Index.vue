<script setup lang="ts">
import AppHeader from '@/components/AppHeader.vue';
import { echo } from '@/echo';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, watch } from 'vue';

type Room = {
    id: number;
    name: string;
    system: string;
};
type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};
type PaginatedRooms = {
    data: Room[];
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
};

const props = defineProps<{
    rooms: PaginatedRooms;
    filters: { search: string };
    notice: string | null;
}>();
const search = ref(props.filters.search);
const toast = ref(props.notice);
const connectionStatus = ref<'connecting' | 'connected' | 'error'>(
    'connecting',
);
let stopConnectionListener: (() => void) | null = null;

const submitSearch = () => {
    router.get(
        '/rooms',
        { search: search.value || undefined },
        { preserveState: true, replace: true },
    );
};

watch(
    () => props.filters.search,
    (value) => (search.value = value),
);
watch(
    () => props.notice,
    (value) => (toast.value = value),
);

onMounted(() => {
    if (!echo) {
        connectionStatus.value = 'error';
        return;
    }

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

    echo.channel('rooms.public')
        .listen('.public.rooms.changed', () => {
            router.reload({
                only: ['rooms'],
            });
        })
        .subscribed(() => (connectionStatus.value = 'connected'))
        .error(() => (connectionStatus.value = 'error'));
});

onUnmounted(() => {
    stopConnectionListener?.();
    echo?.leave('rooms.public');
});
</script>

<template>
    <Head title="Public rooms" />
    <main class="min-h-screen bg-stone-950 text-stone-100">
        <AppHeader current="rooms" />
        <div class="mx-auto max-w-6xl px-6 py-12">
            <header
                class="mt-6 flex flex-wrap items-start justify-between gap-5 border-b border-stone-800 pb-8"
            >
                <div>
                    <p
                        class="text-sm tracking-[0.25em] text-amber-500 uppercase"
                    >
                        Spectator access
                    </p>
                    <h1
                        class="hq-rule-heading mt-3 text-4xl font-black sm:text-5xl"
                    >
                        Public rooms
                    </h1>
                    <p class="mt-3 text-stone-400">
                        No account is required. Public rooms are always
                        read-only for spectators.
                    </p>
                </div>
                <div
                    class="flex items-center"
                    :title="`Realtime ${connectionStatus}`"
                    role="status"
                    :aria-label="`Realtime ${connectionStatus}`"
                >
                    <span
                        class="size-3 rounded-full ring-4 ring-stone-950"
                        :class="{
                            'bg-amber-500': connectionStatus === 'connecting',
                            'bg-emerald-500': connectionStatus === 'connected',
                            'bg-red-500': connectionStatus === 'error',
                        }"
                    />
                </div>
            </header>

            <form
                class="mt-8 flex flex-col gap-3 sm:flex-row"
                @submit.prevent="submitSearch"
            >
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search rooms or systems"
                    class="min-w-0 flex-1 border border-stone-700 bg-stone-950 px-4 py-3"
                />
                <button class="bg-amber-600 px-5 py-3 font-bold text-stone-950">
                    Search
                </button>
            </form>

            <div
                v-if="rooms.data.length"
                class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <Link
                    v-for="room in rooms.data"
                    :key="room.id"
                    :href="`/games/${room.id}`"
                    class="border border-stone-800 bg-stone-900 p-6 transition hover:border-amber-600"
                >
                    <p class="text-xs tracking-wider text-amber-500 uppercase">
                        {{ room.system }}
                    </p>
                    <h2 class="mt-3 text-2xl font-bold">{{ room.name }}</h2>
                    <p class="mt-5 text-sm text-stone-400">
                        Enter as spectator →
                    </p>
                </Link>
            </div>
            <p
                v-else
                class="mt-8 rounded-xl border border-dashed border-stone-800 p-10 text-center text-stone-500"
            >
                There are no public rooms open right now.
            </p>
            <nav
                v-if="rooms.total > 50"
                class="mt-8 flex flex-wrap items-center justify-between gap-4"
            >
                <p class="text-sm text-stone-400">
                    {{ rooms.from }}-{{ rooms.to }} of {{ rooms.total }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="link in rooms.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        preserve-scroll
                        preserve-state
                        class="rounded border border-stone-700 px-3 py-2 text-sm"
                        :class="{
                            'border-amber-600 text-amber-400': link.active,
                            'pointer-events-none opacity-40': !link.url,
                        }"
                        v-html="link.label"
                    />
                </div>
            </nav>
        </div>

        <div
            v-if="toast"
            class="fixed right-4 bottom-4 z-50 max-w-sm rounded-lg border border-amber-700/60 bg-stone-900 px-4 py-3 text-sm text-amber-100 shadow-2xl"
            role="status"
        >
            <div class="flex items-start gap-3">
                <p class="min-w-0 flex-1">{{ toast }}</p>
                <button
                    class="rounded border border-stone-700 px-2 py-1 text-xs text-stone-300 transition hover:border-amber-500 hover:text-amber-300"
                    @click="toast = null"
                >
                    Close
                </button>
            </div>
        </div>
    </main>
</template>
