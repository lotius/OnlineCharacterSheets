<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Definition = Record<string, unknown> & {
    id: number;
    key: string;
    name: string;
    description?: string | null;
    configuration_json?: string;
};
type Group = { label: string; definitions: Definition[] };
const props = defineProps<{
    mode: 'admin' | 'personal';
    versions: Array<{ id: number; name: string }>;
    selectedVersionId: number | null;
    groups: Record<string, Group>;
}>();
const activeType = ref(Object.keys(props.groups)[0] || 'items');
const editing = ref<Definition | null>(null);
const form = useForm({
    name: '',
    key: '',
    description: '',
    icon: '',
    configuration_json: '',
    display_order: 0,
    data_type: 'integer',
    default_value: null as number | null,
    default_max_value: null as number | null,
    minimum_value: null as number | null,
    maximum_value: null as number | null,
    is_public: true,
    capacity: 1,
    is_collapsible: true,
    default_open: true,
});
const base = computed(() =>
    props.mode === 'admin' ? '/admin/catalog' : '/library',
);
const selectVersion = (event: Event) =>
    router.get(base.value, {
        version: (event.target as HTMLSelectElement).value,
    });
const reset = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
};
const edit = (definition: Definition) => {
    editing.value = definition;
    for (const key of Object.keys(form.data())) {
        if (key in definition)
            (form as unknown as Record<string, unknown>)[key] = definition[key];
    }
};
const save = () => {
    const url = `${base.value}/${props.selectedVersionId}/${activeType.value}`;
    if (editing.value)
        form.patch(`${url}/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: reset,
        });
    else form.post(url, { preserveScroll: true, onSuccess: reset });
};
const remove = (definition: Definition) => {
    if (confirm(`Delete ${definition.name}?`))
        router.delete(`${base.value}/${activeType.value}/${definition.id}`, {
            preserveScroll: true,
        });
};
</script>

<template>
    <Head :title="mode === 'admin' ? 'System catalog' : 'Homebrew library'" />
    <main class="min-h-screen bg-stone-950 px-6 py-10 text-stone-100">
        <div class="mx-auto max-w-7xl">
            <Link href="/dashboard" class="text-sm text-amber-500"
                >← Dashboard</Link
            >
            <header class="mt-5 border-b border-stone-800 pb-6">
                <p class="text-sm tracking-widest text-amber-500 uppercase">
                    {{ mode === 'admin' ? 'Administrator' : 'DM library' }}
                </p>
                <h1 class="mt-2 text-4xl font-black">
                    {{
                        mode === 'admin'
                            ? 'System-wide catalog'
                            : 'Reusable homebrew'
                    }}
                </h1>
                <p class="mt-2 max-w-3xl text-stone-400">
                    {{
                        mode === 'admin'
                            ? 'These defaults are available to every room using this system version.'
                            : 'These definitions belong to your account and can be reused in any of your compatible rooms.'
                    }}
                </p>
            </header>
            <label class="mt-6 block max-w-xl"
                >Game system version
                <select
                    :value="selectedVersionId || ''"
                    class="mt-2 w-full rounded bg-stone-900 p-3"
                    @change="selectVersion"
                >
                    <option
                        v-for="version in versions"
                        :key="version.id"
                        :value="version.id"
                    >
                        {{ version.name }}
                    </option>
                </select>
            </label>
            <div
                v-if="selectedVersionId"
                class="mt-8 grid gap-6 lg:grid-cols-[220px_1fr_360px]"
            >
                <nav class="space-y-2">
                    <button
                        v-for="(group, key) in groups"
                        :key="key"
                        class="w-full rounded-lg px-4 py-3 text-left"
                        :class="
                            activeType === key
                                ? 'bg-amber-600 font-bold text-stone-950'
                                : 'bg-stone-900'
                        "
                        @click="
                            activeType = String(key);
                            reset();
                        "
                    >
                        {{ group.label }}
                        <span class="float-right opacity-60">{{
                            group.definitions.length
                        }}</span>
                    </button>
                </nav>
                <section
                    class="rounded-xl border border-stone-800 bg-stone-900 p-5"
                >
                    <h2 class="text-2xl font-bold">
                        {{ groups[activeType]?.label }}
                    </h2>
                    <div class="mt-4 space-y-3">
                        <article
                            v-for="definition in groups[activeType]
                                ?.definitions"
                            :key="definition.id"
                            class="flex items-start justify-between gap-4 rounded-lg bg-stone-950 p-4"
                        >
                            <div>
                                <strong>{{ definition.name }}</strong>
                                <p class="font-mono text-xs text-amber-500">
                                    {{ definition.key }}
                                </p>
                                <p class="mt-1 text-sm text-stone-400">
                                    {{ definition.description }}
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    class="text-sm text-amber-400"
                                    @click="edit(definition)"
                                >
                                    Edit</button
                                ><button
                                    class="text-sm text-red-400"
                                    @click="remove(definition)"
                                >
                                    Delete
                                </button>
                            </div>
                        </article>
                        <p
                            v-if="!groups[activeType]?.definitions.length"
                            class="rounded border border-dashed border-stone-700 p-8 text-center text-stone-500"
                        >
                            No definitions in this scope yet.
                        </p>
                    </div>
                </section>
                <form
                    class="h-fit space-y-4 rounded-xl border border-stone-800 bg-stone-900 p-5"
                    @submit.prevent="save"
                >
                    <h2 class="text-xl font-bold">
                        {{ editing ? 'Edit' : 'Add' }}
                        {{ groups[activeType]?.label }}
                    </h2>
                    <label class="block text-sm"
                        >Name<input
                            v-model="form.name"
                            required
                            class="mt-1 w-full rounded bg-stone-950 p-3"
                    /></label>
                    <label class="block text-sm"
                        >Key<input
                            v-model="form.key"
                            placeholder="generated_from_name"
                            class="mt-1 w-full rounded bg-stone-950 p-3 font-mono"
                    /></label>
                    <label class="block text-sm"
                        >Description<textarea
                            v-model="form.description"
                            rows="3"
                            class="mt-1 w-full rounded bg-stone-950 p-3"
                        />
                    </label>
                    <label v-if="activeType === 'stats'" class="block text-sm"
                        >Data type<select
                            v-model="form.data_type"
                            class="mt-1 w-full rounded bg-stone-950 p-3"
                        >
                            <option>integer</option>
                            <option>decimal</option>
                            <option>text</option>
                            <option>boolean</option>
                        </select></label
                    >
                    <label v-if="activeType === 'slots'" class="block text-sm"
                        >Capacity<input
                            v-model.number="form.capacity"
                            type="number"
                            min="1"
                            class="mt-1 w-full rounded bg-stone-950 p-3"
                    /></label>
                    <label class="block text-sm"
                        >Advanced configuration (JSON)<textarea
                            v-model="form.configuration_json"
                            rows="5"
                            placeholder="{}"
                            class="mt-1 w-full rounded bg-stone-950 p-3 font-mono text-xs"
                        />
                    </label>
                    <div
                        v-if="Object.keys(form.errors).length"
                        class="rounded bg-red-950 p-3 text-sm text-red-300"
                    >
                        <p v-for="error in form.errors" :key="error">
                            {{ error }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button
                            :disabled="form.processing"
                            class="flex-1 rounded bg-amber-600 p-3 font-bold text-stone-950"
                        >
                            Save</button
                        ><button
                            v-if="editing"
                            type="button"
                            class="rounded border border-stone-700 px-4"
                            @click="reset"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
            <p v-else class="mt-8 text-stone-400">
                An administrator must create a game system version first.
            </p>
        </div>
    </main>
</template>
