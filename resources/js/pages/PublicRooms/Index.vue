<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    rooms: Array<{
        id: number;
        name: string;
        system: string;
        status: string;
    }>;
}>();
</script>

<template>
    <Head title="Public rooms" />
    <main class="min-h-screen bg-stone-950 px-6 py-12 text-stone-100">
        <div class="mx-auto max-w-6xl">
            <Link href="/" class="text-sm text-amber-500">← Home</Link>
            <header class="mt-6 border-b border-stone-800 pb-8">
                <p class="text-sm tracking-[0.25em] text-amber-500 uppercase">
                    Spectator access
                </p>
                <h1 class="mt-3 text-4xl font-black sm:text-5xl">
                    Public rooms
                </h1>
                <p class="mt-3 text-stone-400">
                    No account is required. Public rooms are always read-only
                    for spectators.
                </p>
            </header>

            <div
                v-if="rooms.length"
                class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <Link
                    v-for="room in rooms"
                    :key="room.id"
                    :href="`/games/${room.id}`"
                    class="rounded-xl border border-stone-800 bg-stone-900 p-6 transition hover:border-amber-600"
                >
                    <div class="flex items-start justify-between gap-3">
                        <p
                            class="text-xs tracking-wider text-amber-500 uppercase"
                        >
                            {{ room.system }}
                        </p>
                        <span
                            class="rounded-full bg-stone-800 px-2 py-1 text-xs text-stone-400"
                            >{{ room.status }}</span
                        >
                    </div>
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
        </div>
    </main>
</template>
