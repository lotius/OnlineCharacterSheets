<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '', remember: false });
const submit = () =>
    form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Log in" />
    <main
        class="flex min-h-screen items-center justify-center bg-stone-950 px-6 py-12 text-stone-100"
    >
        <section
            class="w-full max-w-md rounded-2xl border border-amber-700/40 bg-stone-900 p-8 shadow-2xl"
        >
            <p
                class="text-sm font-semibold tracking-[0.25em] text-amber-500 uppercase"
            >
                Online Character Sheets
            </p>
            <h1 class="mt-3 text-3xl font-bold">Return to the table</h1>
            <p class="mt-2 text-sm text-stone-400">
                Log in to manage your rooms and characters.
            </p>
            <form class="mt-8 space-y-5" @submit.prevent="submit">
                <label class="block"
                    ><span class="text-sm font-medium">Email</span
                    ><input
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        required
                        autofocus
                        class="mt-2 w-full rounded-lg border border-stone-700 bg-stone-950 px-4 py-3 outline-none focus:border-amber-500"
                    /><span
                        v-if="form.errors.email"
                        class="mt-1 block text-sm text-red-400"
                        >{{ form.errors.email }}</span
                    ></label
                >
                <label class="block"
                    ><span class="text-sm font-medium">Password</span
                    ><input
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="mt-2 w-full rounded-lg border border-stone-700 bg-stone-950 px-4 py-3 outline-none focus:border-amber-500"
                    /><span
                        v-if="form.errors.password"
                        class="mt-1 block text-sm text-red-400"
                        >{{ form.errors.password }}</span
                    ></label
                >
                <label class="flex items-center gap-2 text-sm text-stone-300"
                    ><input
                        v-model="form.remember"
                        type="checkbox"
                        class="size-4 accent-amber-600"
                    />Remember me</label
                >
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-lg bg-amber-600 px-4 py-3 font-semibold text-stone-950 hover:bg-amber-500 disabled:opacity-50"
                >
                    Log in
                </button>
            </form>
            <p class="mt-6 text-center text-sm text-stone-400">
                New adventurer?
                <Link
                    href="/register"
                    class="font-semibold text-amber-500 hover:text-amber-400"
                    >Create an account</Link
                >
            </p>
        </section>
    </main>
</template>
