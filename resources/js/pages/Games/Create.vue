<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
defineProps<{ versions: Array<{ id: number; name: string }> }>();
const form = useForm({
    name: '',
    game_system_version_id: '',
    visibility: 'invite_only',
});
</script>
<template>
    <Head title="Create room" />
    <main class="min-h-screen bg-stone-950 px-6 py-12 text-stone-100">
        <section
            class="mx-auto max-w-xl rounded-2xl border border-amber-700/40 bg-stone-900 p-8"
        >
            <Link href="/dashboard" class="text-sm text-amber-500"
                >← Dashboard</Link
            >
            <h1 class="mt-5 text-3xl font-bold">Create a game room</h1>
            <form class="mt-8 space-y-5" @submit.prevent="form.post('/games')">
                <label class="block"
                    ><span>Room name</span
                    ><input
                        v-model="form.name"
                        required
                        class="mt-2 w-full rounded-lg border border-stone-700 bg-stone-950 px-4 py-3"
                    /><span
                        v-if="form.errors.name"
                        class="text-sm text-red-400"
                        >{{ form.errors.name }}</span
                    ></label
                ><label class="block"
                    ><span>Game system</span
                    ><select
                        v-model="form.game_system_version_id"
                        required
                        class="mt-2 w-full rounded-lg border border-stone-700 bg-stone-950 px-4 py-3"
                    >
                        <option disabled value="">Choose a system</option>
                        <option
                            v-for="version in versions"
                            :key="version.id"
                            :value="version.id"
                        >
                            {{ version.name }}
                        </option></select
                    ><span
                        v-if="form.errors.game_system_version_id"
                        class="text-sm text-red-400"
                        >{{ form.errors.game_system_version_id }}</span
                    ></label
                ><label class="block"
                    ><span>Visibility</span
                    ><select
                        v-model="form.visibility"
                        class="mt-2 w-full rounded-lg border border-stone-700 bg-stone-950 px-4 py-3"
                    >
                        <option value="private">Private</option>
                        <option value="invite_only">Join requests</option>
                        <option value="public_view">
                            Public spectator view later
                        </option>
                    </select></label
                ><button
                    :disabled="form.processing"
                    class="w-full rounded-lg bg-amber-600 px-5 py-3 font-bold text-stone-950 disabled:opacity-50"
                >
                    Create room
                </button>
            </form>
        </section>
    </main>
</template>
