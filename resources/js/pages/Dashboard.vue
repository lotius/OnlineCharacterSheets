<script setup lang="ts">
import type { User } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';

const page = usePage<{ auth: { user: User } }>();
const logout = () => router.post('/logout');
</script>

<template>
    <Head title="Dashboard" />
    <main class="min-h-screen bg-stone-950 px-6 py-10 text-stone-100">
        <div class="mx-auto max-w-6xl">
            <header
                class="flex items-center justify-between border-b border-stone-800 pb-6"
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
                <button
                    type="button"
                    class="rounded-lg border border-stone-700 px-4 py-2 text-sm font-semibold hover:border-amber-500 hover:text-amber-400"
                    @click="logout"
                >
                    Log out
                </button>
            </header>
            <section
                class="mt-10 rounded-2xl border border-dashed border-stone-700 bg-stone-900/60 p-10 text-center"
            >
                <h2 class="text-xl font-semibold">
                    Your game rooms will appear here
                </h2>
                <p class="mt-2 text-stone-400">
                    Authentication is ready. Room creation and join-by-code are
                    next.
                </p>
            </section>
        </div>
    </main>
</template>
