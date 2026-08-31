<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    systems: Array<{
        id: number;
        name: string;
        slug: string;
        versions: Array<{
            id: number;
            version: string;
            name: string | null;
            is_current: boolean;
        }>;
    }>;
}>();
const systemForm = useForm({
    name: '',
    slug: '',
    description: '',
    default_theme: '',
    version: '1.0',
    version_name: 'Core',
});
const versionForms = new Map<number, ReturnType<typeof useForm>>();
const versionForm = (id: number) => {
    if (!versionForms.has(id))
        versionForms.set(
            id,
            useForm({ version: '', name: '', is_current: false }),
        );
    return versionForms.get(id)!;
};
</script>
<template>
    <Head title="Game systems" />
    <main class="min-h-screen bg-stone-950 px-6 py-10 text-stone-100">
        <div class="mx-auto max-w-6xl">
            <Link href="/dashboard" class="text-sm text-amber-500"
                >← Dashboard</Link
            >
            <header class="mt-5">
                <p class="text-sm tracking-widest text-amber-500 uppercase">
                    Administrator
                </p>
                <h1 class="mt-2 text-4xl font-black">Game systems</h1>
            </header>
            <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_380px]">
                <section class="space-y-4">
                    <article
                        v-for="system in systems"
                        :key="system.id"
                        class="rounded-xl border border-stone-800 bg-stone-900 p-5"
                    >
                        <div class="flex justify-between">
                            <div>
                                <h2 class="text-2xl font-bold">
                                    {{ system.name }}
                                </h2>
                                <p class="font-mono text-xs text-amber-500">
                                    {{ system.slug }}
                                </p>
                            </div>
                            <Link
                                :href="`/admin/catalog?version=${system.versions[0]?.id}`"
                                class="text-amber-400"
                                >Open catalog →</Link
                            >
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span
                                v-for="version in system.versions"
                                :key="version.id"
                                class="rounded bg-stone-950 px-3 py-2 text-sm"
                                >{{ version.name || version.version }}
                                <small
                                    v-if="version.is_current"
                                    class="text-emerald-400"
                                    >current</small
                                ></span
                            >
                        </div>
                        <form
                            class="mt-4 grid gap-2 sm:grid-cols-3"
                            @submit.prevent="
                                versionForm(system.id).post(
                                    `/admin/systems/${system.id}/versions`,
                                )
                            "
                        >
                            <input
                                v-model="versionForm(system.id).version"
                                required
                                placeholder="Version"
                                class="rounded bg-stone-950 p-2"
                            /><input
                                v-model="versionForm(system.id).name"
                                placeholder="Display name"
                                class="rounded bg-stone-950 p-2"
                            /><button
                                class="rounded border border-amber-600 text-amber-400"
                            >
                                Add version
                            </button>
                        </form>
                    </article>
                </section>
                <form
                    class="h-fit space-y-4 rounded-xl border border-stone-800 bg-stone-900 p-5"
                    @submit.prevent="systemForm.post('/admin/systems')"
                >
                    <h2 class="text-xl font-bold">New game system</h2>
                    <input
                        v-model="systemForm.name"
                        required
                        placeholder="Name"
                        class="w-full rounded bg-stone-950 p-3"
                    /><input
                        v-model="systemForm.slug"
                        placeholder="slug (optional)"
                        class="w-full rounded bg-stone-950 p-3"
                    /><textarea
                        v-model="systemForm.description"
                        placeholder="Description"
                        class="w-full rounded bg-stone-950 p-3"
                    /><input
                        v-model="systemForm.default_theme"
                        placeholder="Default theme"
                        class="w-full rounded bg-stone-950 p-3"
                    />
                    <div class="grid grid-cols-2 gap-2">
                        <input
                            v-model="systemForm.version"
                            required
                            placeholder="1.0"
                            class="rounded bg-stone-950 p-3"
                        /><input
                            v-model="systemForm.version_name"
                            placeholder="Core"
                            class="rounded bg-stone-950 p-3"
                        />
                    </div>
                    <p
                        v-for="error in systemForm.errors"
                        :key="error"
                        class="text-sm text-red-400"
                    >
                        {{ error }}
                    </p>
                    <button
                        class="w-full rounded bg-amber-600 p-3 font-bold text-stone-950"
                    >
                        Create system
                    </button>
                </form>
            </div>
        </div>
    </main>
</template>
