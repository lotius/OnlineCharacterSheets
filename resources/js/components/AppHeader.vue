<script setup lang="ts">
import type { User } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    current?: 'home' | 'dashboard' | 'rooms' | 'library' | 'admin';
}>();

const page = usePage<{ auth: { user: User | null } }>();
const open = ref(false);
</script>

<template>
    <header
        class="border-b border-stone-800 bg-stone-950/95 px-4 py-3 text-stone-100"
    >
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
            <nav class="flex min-w-0 flex-wrap items-center gap-2">
                <Link
                    href="/"
                    class="px-3 py-2 text-sm font-semibold text-amber-500"
                    :class="{ 'bg-stone-900': current === 'home' }"
                    >Online Character Sheets</Link
                >
                <Link
                    v-if="page.props.auth.user"
                    href="/dashboard"
                    class="px-3 py-2 text-sm text-stone-300 transition hover:text-amber-400"
                    :class="{
                        'bg-stone-900 text-amber-400': current === 'dashboard',
                    }"
                    >Your rooms</Link
                >
                <Link
                    href="/rooms"
                    class="px-3 py-2 text-sm text-stone-300 transition hover:text-amber-400"
                    :class="{
                        'bg-stone-900 text-amber-400': current === 'rooms',
                    }"
                    >Public rooms</Link
                >
                <Link
                    v-if="page.props.auth.user"
                    href="/games/create"
                    class="px-3 py-2 text-sm text-stone-300 transition hover:text-amber-400"
                    >Create room</Link
                >
            </nav>

            <div class="relative shrink-0">
                <template v-if="page.props.auth.user">
                    <button
                        class="border border-stone-700 bg-stone-900 px-3 py-2 text-sm font-semibold text-stone-100 transition hover:border-amber-600 hover:text-amber-400"
                        @click="open = !open"
                    >
                        {{ page.props.auth.user.name }}
                    </button>
                    <div
                        v-if="open"
                        class="absolute right-0 z-50 mt-2 w-64 border border-stone-800 bg-stone-900 p-2 shadow-2xl"
                    >
                        <div class="border-b border-stone-800 px-3 py-2">
                            <p class="font-semibold">
                                {{ page.props.auth.user.name }}
                            </p>
                            <p class="truncate text-xs text-stone-400">
                                {{ page.props.auth.user.email }}
                            </p>
                        </div>
                        <Link
                            href="/library"
                            class="block px-3 py-2 text-sm text-stone-300 hover:bg-stone-950 hover:text-amber-400"
                            @click="open = false"
                            >Homebrew library</Link
                        >
                        <Link
                            v-if="page.props.auth.user.is_admin"
                            href="/admin/systems"
                            class="block px-3 py-2 text-sm text-stone-300 hover:bg-stone-950 hover:text-amber-400"
                            @click="open = false"
                            >Admin</Link
                        >
                        <Link
                            v-if="page.props.auth.user.is_admin"
                            href="/admin/catalog"
                            class="block px-3 py-2 text-sm text-stone-300 hover:bg-stone-950 hover:text-amber-400"
                            @click="open = false"
                            >System catalog</Link
                        >
                        <button
                            class="block w-full px-3 py-2 text-left text-sm text-stone-300 hover:bg-stone-950 hover:text-amber-400"
                            @click="router.post('/logout')"
                        >
                            Log out
                        </button>
                    </div>
                </template>
                <div v-else class="flex gap-2">
                    <Link
                        href="/login"
                        class="border border-stone-700 px-3 py-2 text-sm text-stone-300 hover:border-amber-600 hover:text-amber-400"
                        >Log in</Link
                    >
                    <Link
                        href="/register"
                        class="bg-amber-600 px-3 py-2 text-sm font-bold text-stone-950"
                        >Register</Link
                    >
                </div>
            </div>
        </div>
    </header>
</template>
