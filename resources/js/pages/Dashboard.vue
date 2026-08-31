<script setup lang="ts">
import type { User } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
type Room = {
    id: number;
    name: string;
    system: string;
    role: string;
    membership_status: string;
    room_status: string;
};
defineProps<{ rooms: Room[] }>();
const page = usePage<{ auth: { user: User } }>();
const join = useForm({ join_code: '' });
</script>
<template>
    <Head title="Dashboard" />
    <main class="min-h-screen bg-stone-950 px-6 py-10 text-stone-100">
        <div class="mx-auto max-w-6xl">
            <header
                class="flex flex-wrap items-center justify-between gap-5 border-b border-stone-800 pb-6"
            >
                <div>
                    <p
                        class="text-sm font-semibold tracking-[0.25em] text-amber-500 uppercase"
                    >
                        Online Character Sheets
                    </p>
                    <h1 class="mt-2 text-3xl font-bold">
                        Welcome, {{ page.props.auth.user.name }}
                    </h1>
                </div>
                <div class="flex gap-3">
                    <Link
                        href="/games/create"
                        class="rounded-lg bg-amber-600 px-4 py-2 font-bold text-stone-950"
                        >Create room</Link
                    ><button
                        class="rounded-lg border border-stone-700 px-4 py-2 text-sm"
                        @click="router.post('/logout')"
                    >
                        Log out
                    </button>
                </div>
            </header>
            <section
                class="mt-8 rounded-xl border border-stone-800 bg-stone-900 p-5"
            >
                <h2 class="font-bold">Join with a code</h2>
                <form
                    class="mt-3 flex gap-3"
                    @submit.prevent="join.post('/games/join')"
                >
                    <div class="flex-1">
                        <input
                            v-model="join.join_code"
                            maxlength="8"
                            placeholder="ABCDEFGH"
                            class="w-full rounded-lg border border-stone-700 bg-stone-950 px-4 py-3 font-mono tracking-widest uppercase"
                        /><span
                            v-if="join.errors.join_code"
                            class="text-sm text-red-400"
                            >{{ join.errors.join_code }}</span
                        >
                    </div>
                    <button
                        class="rounded-lg border border-amber-600 px-5 font-semibold text-amber-400"
                    >
                        Join
                    </button>
                </form>
            </section>
            <section class="mt-10">
                <h2 class="text-2xl font-bold">Your rooms</h2>
                <div v-if="rooms.length" class="mt-4 grid gap-4 md:grid-cols-2">
                    <article
                        v-for="room in rooms"
                        :key="room.id"
                        class="rounded-xl border border-stone-800 bg-stone-900 p-5"
                    >
                        <div class="flex justify-between gap-4">
                            <div>
                                <p class="text-sm text-amber-500">
                                    {{ room.system }}
                                </p>
                                <h3 class="mt-1 text-xl font-bold">
                                    {{ room.name }}
                                </h3>
                                <p class="mt-2 text-sm text-stone-400">
                                    {{ room.role }} ·
                                    {{ room.membership_status }}
                                </p>
                            </div>
                            <Link
                                v-if="room.membership_status === 'active'"
                                :href="`/games/${room.id}`"
                                class="self-start rounded border border-stone-700 px-3 py-2 text-sm"
                                >Open</Link
                            >
                        </div>
                    </article>
                </div>
                <p
                    v-else
                    class="mt-4 rounded-xl border border-dashed border-stone-700 p-10 text-center text-stone-400"
                >
                    No rooms yet. Create one or enter a join code.
                </p>
            </section>
        </div>
    </main>
</template>
