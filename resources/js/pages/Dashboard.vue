<script setup lang="ts">
import AppHeader from '@/components/AppHeader.vue';
import type { User } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
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
const showJoinModal = ref(false);
const submitJoinCode = () =>
    join.post('/games/join', {
        onSuccess: () => {
            showJoinModal.value = false;
            join.reset();
        },
    });
</script>
<template>
    <Head title="Dashboard" />
    <main class="min-h-screen bg-stone-950 text-stone-100">
        <AppHeader current="dashboard" />
        <div class="mx-auto max-w-6xl px-6 py-10">
            <header
                class="flex flex-wrap items-center justify-between gap-5 border-b border-stone-800 pb-6"
            >
                <div>
                    <p
                        class="text-sm font-semibold tracking-[0.25em] text-amber-500 uppercase"
                    >
                        Online Character Sheets
                    </p>
                    <h1 class="hq-rule-heading mt-2 text-3xl font-bold">
                        Welcome, {{ page.props.auth.user.name }}
                    </h1>
                </div>
                <div class="flex gap-3">
                    <button
                        class="rounded-lg border border-amber-600 px-4 py-2 text-sm font-semibold text-amber-400 transition hover:bg-amber-600 hover:text-stone-950"
                        @click="showJoinModal = true"
                    >
                        Join with Code
                    </button>
                    <Link
                        href="/games/create"
                        class="rounded-lg bg-amber-600 px-4 py-2 font-bold text-stone-950"
                        >Create room</Link
                    >
                </div>
            </header>
            <section class="mt-10">
                <h2 class="hq-rule-heading text-2xl font-bold">Your rooms</h2>
                <div v-if="rooms.length" class="mt-4 grid gap-4 md:grid-cols-2">
                    <article
                        v-for="room in rooms"
                        :key="room.id"
                        class="border border-stone-800 bg-stone-900 p-5"
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
                    No rooms yet. Create one or join with a code.
                </p>
            </section>
        </div>

        <div
            v-if="showJoinModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-stone-950/80 px-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="join-room-title"
            @click.self="showJoinModal = false"
        >
            <section
                class="w-full max-w-md border border-stone-800 bg-stone-900 p-6 shadow-2xl"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="join-room-title" class="text-2xl font-bold">
                            Join with Code
                        </h2>
                    </div>
                    <button
                        class="rounded border border-stone-700 px-3 py-1 text-sm text-stone-300 transition hover:border-amber-600 hover:text-amber-400"
                        @click="showJoinModal = false"
                    >
                        Close
                    </button>
                </div>
                <form class="mt-5 space-y-4" @submit.prevent="submitJoinCode">
                    <label class="block">
                        <span class="text-sm text-stone-400">Room code</span>
                        <input
                            v-model="join.join_code"
                            maxlength="8"
                            autofocus
                            placeholder="ABCDEFGH"
                            class="mt-2 w-full border border-stone-700 bg-stone-950 px-4 py-3 font-mono tracking-widest uppercase"
                        />
                        <span
                            v-if="join.errors.join_code"
                            class="mt-2 block text-sm text-red-400"
                            >{{ join.errors.join_code }}</span
                        >
                    </label>
                    <button
                        :disabled="join.processing"
                        class="w-full bg-amber-600 px-5 py-3 font-bold text-stone-950 transition hover:bg-amber-500 disabled:opacity-50"
                    >
                        Join
                    </button>
                </form>
            </section>
        </div>
    </main>
</template>
