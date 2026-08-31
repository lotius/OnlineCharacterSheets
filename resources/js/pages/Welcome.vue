<script setup lang="ts">
import type { User } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage<{ auth: { user: User | null } }>();
defineProps<{
    publicRooms: Array<{ id: number; name: string; system: string }>;
}>();
</script>

<template>
    <Head title="Welcome" />
    <main
        class="flex min-h-screen items-center bg-stone-950 px-6 py-16 text-stone-100"
    >
        <div class="mx-auto w-full max-w-6xl">
            <div class="grid gap-12 lg:grid-cols-[1.2fr_0.8fr] lg:items-center">
                <section>
                    <p
                        class="text-sm font-semibold tracking-[0.3em] text-amber-500 uppercase"
                    >
                        Gather your party
                    </p>
                    <h1
                        class="mt-5 max-w-3xl text-5xl leading-tight font-black sm:text-7xl"
                    >
                        Live character sheets for the whole table.
                    </h1>
                    <p class="mt-6 max-w-2xl text-lg leading-8 text-stone-400">
                        Create a room, assign heroes, and keep every player
                        synchronized across desktop and mobile.
                    </p>
                    <div class="mt-9 flex flex-wrap gap-4">
                        <Link
                            v-if="page.props.auth.user"
                            href="/dashboard"
                            class="rounded-lg bg-amber-600 px-6 py-3 font-bold text-stone-950 hover:bg-amber-500"
                            >Open dashboard</Link
                        >
                        <template v-else>
                            <Link
                                href="/register"
                                class="rounded-lg bg-amber-600 px-6 py-3 font-bold text-stone-950 hover:bg-amber-500"
                                >Create account</Link
                            >
                            <Link
                                href="/login"
                                class="rounded-lg border border-stone-700 px-6 py-3 font-bold hover:border-amber-500 hover:text-amber-400"
                                >Log in</Link
                            >
                        </template>
                    </div>
                </section>
                <aside
                    class="rounded-3xl border border-amber-700/30 bg-gradient-to-br from-stone-900 to-amber-950/30 p-8 shadow-2xl"
                >
                    <p class="text-5xl">⚔</p>
                    <h2 class="mt-6 text-2xl font-bold">Generic at its core</h2>
                    <p class="mt-3 leading-7 text-stone-400">
                        HeroQuest-inspired presentation backed by reusable game
                        systems, stats, classes, and permissions.
                    </p>
                </aside>
            </div>

            <section class="mt-16 border-t border-stone-800 pt-10">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p
                            class="text-sm tracking-widest text-amber-500 uppercase"
                        >
                            Spectator access
                        </p>
                        <h2 class="mt-2 text-3xl font-black">Public rooms</h2>
                    </div>
                    <p class="text-sm text-stone-400">
                        No account required. Viewing is always read-only.
                    </p>
                </div>
                <div
                    v-if="publicRooms.length"
                    class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <Link
                        v-for="room in publicRooms"
                        :key="room.id"
                        :href="`/games/${room.id}`"
                        class="rounded-xl border border-stone-800 bg-stone-900 p-5 transition hover:border-amber-600"
                    >
                        <p
                            class="text-xs tracking-wider text-amber-500 uppercase"
                        >
                            {{ room.system }}
                        </p>
                        <h3 class="mt-2 text-xl font-bold">{{ room.name }}</h3>
                        <p class="mt-4 text-sm text-stone-400">
                            Enter as spectator →
                        </p>
                    </Link>
                </div>
                <p
                    v-else
                    class="mt-6 rounded-xl border border-dashed border-stone-800 p-6 text-stone-500"
                >
                    There are no public rooms open right now.
                </p>
            </section>
        </div>
    </main>
</template>
