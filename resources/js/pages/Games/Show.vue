<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
type Member = { id: number; name: string; role: string; status: string };
type Game = {
    id: number;
    name: string;
    join_code: string;
    status: string;
    visibility: string;
    system: string;
    can_manage_members: boolean;
    members: Member[];
};
const props = defineProps<{ game: Game }>();
const memberUrl = (member: Member, action = '') =>
    `/games/${props.game.id}/members/${member.id}${action}`;
</script>
<template>
    <Head :title="game.name" />
    <main class="min-h-screen bg-stone-950 px-6 py-10 text-stone-100">
        <div class="mx-auto max-w-5xl">
            <Link href="/dashboard" class="text-sm text-amber-500"
                >← Dashboard</Link
            >
            <header
                class="mt-5 flex flex-wrap items-start justify-between gap-5 border-b border-stone-800 pb-7"
            >
                <div>
                    <p class="text-sm tracking-widest text-amber-500 uppercase">
                        {{ game.system }}
                    </p>
                    <h1 class="mt-2 text-4xl font-black">{{ game.name }}</h1>
                    <p class="mt-2 text-stone-400">
                        {{ game.status }} · {{ game.visibility }}
                    </p>
                </div>
                <div
                    v-if="game.can_manage_members"
                    class="rounded-xl border border-amber-700/40 bg-stone-900 px-6 py-4 text-center"
                >
                    <p class="text-xs tracking-widest text-stone-400 uppercase">
                        Join code
                    </p>
                    <p
                        class="mt-1 font-mono text-2xl font-bold tracking-[0.2em] text-amber-400"
                    >
                        {{ game.join_code }}
                    </p>
                </div>
            </header>
            <section class="mt-8">
                <h2 class="text-2xl font-bold">Members</h2>
                <div class="mt-4 space-y-3">
                    <article
                        v-for="member in game.members"
                        :key="member.id"
                        class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-stone-800 bg-stone-900 p-4"
                    >
                        <div>
                            <p class="font-semibold">{{ member.name }}</p>
                            <p class="text-sm text-stone-400">
                                {{ member.role }} · {{ member.status }}
                            </p>
                        </div>
                        <div
                            v-if="
                                game.can_manage_members && member.role !== 'dm'
                            "
                            class="flex gap-2"
                        >
                            <button
                                v-if="member.status === 'requested'"
                                class="rounded bg-emerald-700 px-3 py-2 text-sm font-semibold"
                                @click="
                                    router.patch(memberUrl(member, '/approve'))
                                "
                            >
                                Approve</button
                            ><button
                                v-if="member.status === 'requested'"
                                class="rounded bg-stone-700 px-3 py-2 text-sm"
                                @click="
                                    router.patch(memberUrl(member, '/reject'))
                                "
                            >
                                Reject</button
                            ><button
                                v-if="member.status === 'active'"
                                class="rounded bg-red-900 px-3 py-2 text-sm"
                                @click="router.delete(memberUrl(member))"
                            >
                                Remove
                            </button>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </main>
</template>
