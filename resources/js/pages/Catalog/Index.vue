<script setup lang="ts">
import AppHeader from '@/components/AppHeader.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Definition = Record<string, unknown> & {
    id: number;
    key: string;
    name: string;
    description?: string | null;
    icon?: string | null;
    configuration?: Record<string, unknown> | null;
    configuration_json?: string;
};
type Group = { label: string; definitions: Definition[] };
type DefinitionType =
    | 'items'
    | 'spells'
    | 'skills'
    | 'classes'
    | 'stats'
    | 'slots'
    | 'trays';

const props = defineProps<{
    mode: 'admin' | 'personal';
    versions: Array<{ id: number; name: string }>;
    selectedVersionId: number | null;
    groups: Record<string, Group>;
}>();

const activeType = ref<DefinitionType>(
    (Object.keys(props.groups)[0] as DefinitionType | undefined) || 'items',
);
const editing = ref<Definition | null>(null);
const importInputKey = ref(0);
const form = useForm({
    name: '',
    key: '',
    description: '',
    icon: '',
    icon_upload: null as File | null,
    display_order: 0,
    data_type: 'integer',
    default_value: null as number | string | boolean | null,
    default_max_value: null as number | null,
    minimum_value: null as number | null,
    maximum_value: null as number | null,
    is_public: true,
    capacity: 1,
    is_collapsible: true,
    default_open: true,
    default_stats_json: '',
    required_slots_json: '',
    charges: null as number | null,
    category: '',
    configuration_json: '',
});
const importForm = useForm({ catalog_file: null as File | null });

const base = computed(() =>
    props.mode === 'admin' ? '/admin/catalog' : '/library',
);
const selectedVersionName = computed(
    () =>
        props.versions.find((version) => version.id === props.selectedVersionId)
            ?.name,
);
const activeGroup = computed(() => props.groups[activeType.value]);
const canDescribe = computed(() =>
    ['items', 'spells', 'skills', 'classes', 'stats'].includes(
        activeType.value,
    ),
);
const canOrder = computed(() =>
    ['classes', 'stats', 'slots', 'trays'].includes(activeType.value),
);
const canHaveIcon = computed(() =>
    ['items', 'spells', 'skills', 'stats', 'slots', 'trays'].includes(
        activeType.value,
    ),
);
const iconUrl = (icon?: string | null) =>
    icon ? (icon.startsWith('http') ? icon : `/storage/${icon}`) : null;

const safeJson = (value: string, fallback: unknown = {}) => {
    if (!value.trim()) return fallback;

    try {
        return JSON.parse(value);
    } catch {
        return fallback;
    }
};

const selectVersion = (event: Event) =>
    router.get(base.value, {
        version: (event.target as HTMLSelectElement).value,
    });

const configurationForSave = () => {
    const config = safeJson(form.configuration_json) as Record<string, unknown>;

    if (activeType.value === 'classes') {
        config.default_stats = safeJson(form.default_stats_json);
    }

    if (activeType.value === 'items') {
        config.required_slots = safeJson(form.required_slots_json, []);
        if (form.category) config.category = form.category;
    }

    if (['spells', 'skills'].includes(activeType.value)) {
        if (form.charges !== null) config.charges = form.charges;
        if (form.category) config.category = form.category;
    }

    return JSON.stringify(config, null, 2);
};

const reset = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
};

const edit = (definition: Definition) => {
    editing.value = definition;
    form.reset();
    form.clearErrors();

    for (const key of Object.keys(form.data())) {
        if (key in definition)
            (form as unknown as Record<string, unknown>)[key] = definition[key];
    }

    const config = definition.configuration || {};
    form.configuration_json = definition.configuration_json || '';
    form.default_stats_json = JSON.stringify(
        config.default_stats || {},
        null,
        2,
    );
    form.required_slots_json = JSON.stringify(
        config.required_slots || [],
        null,
        2,
    );
    form.charges = typeof config.charges === 'number' ? config.charges : null;
    form.category = typeof config.category === 'string' ? config.category : '';
};

const save = () => {
    form.configuration_json = configurationForSave();
    const url = `${base.value}/${props.selectedVersionId}/${activeType.value}`;

    form.transform((data) =>
        editing.value ? { ...data, _method: 'patch' } : data,
    );

    if (editing.value)
        form.post(`${url}/${editing.value.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: reset,
        });
    else
        form.post(url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: reset,
        });
};

const remove = (definition: Definition) => {
    if (confirm(`Delete ${definition.name}?`))
        router.delete(`${base.value}/${activeType.value}/${definition.id}`, {
            preserveScroll: true,
        });
};

const chooseType = (type: string) => {
    activeType.value = type as DefinitionType;
    reset();
};
const importCatalog = () => {
    importForm.post(
        `${base.value}/${props.selectedVersionId}/${activeType.value}/import`,
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                importForm.reset();
                importInputKey.value++;
            },
        },
    );
};
</script>

<template>
    <Head :title="mode === 'admin' ? 'System catalog' : 'Homebrew library'" />
    <main class="min-h-screen bg-stone-950 text-stone-100">
        <AppHeader :current="mode === 'admin' ? 'admin' : 'library'" />
        <div class="mx-auto max-w-7xl px-6 py-10">
            <header class="border-b border-stone-800 pb-6">
                <p class="text-sm tracking-widest text-amber-500 uppercase">
                    {{ mode === 'admin' ? 'Administrator' : 'DM library' }}
                </p>
                <h1 class="hq-rule-heading mt-2 text-4xl font-black">
                    {{
                        mode === 'admin'
                            ? 'Game element builder'
                            : 'Reusable homebrew'
                    }}
                </h1>
                <p class="mt-2 max-w-3xl text-stone-400">
                    {{
                        mode === 'admin'
                            ? 'Build system defaults one element at a time. These definitions become available to every compatible room.'
                            : 'Build reusable definitions for rooms you run.'
                    }}
                </p>
            </header>

            <div class="mt-6 flex flex-wrap items-end justify-between gap-4">
                <label class="block min-w-72 text-sm text-stone-300">
                    Game system
                    <select
                        :value="selectedVersionId || ''"
                        class="mt-2 w-full border border-stone-700 bg-stone-900 p-3"
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
                <p v-if="selectedVersionName" class="text-sm text-stone-400">
                    Editing {{ selectedVersionName }}
                </p>
            </div>

            <div
                v-if="selectedVersionId"
                class="mt-8 grid gap-6 lg:grid-cols-[240px_1fr_420px]"
            >
                <nav class="space-y-2">
                    <button
                        v-for="(group, key) in groups"
                        :key="key"
                        class="flex w-full items-center justify-between border px-4 py-3 text-left transition"
                        :class="
                            activeType === key
                                ? 'border-amber-500 bg-amber-600 font-bold text-stone-950'
                                : 'border-stone-800 bg-stone-900 text-stone-300 hover:border-amber-700 hover:text-amber-400'
                        "
                        @click="chooseType(String(key))"
                    >
                        <span>{{ group.label }}</span>
                        <span class="opacity-70">{{
                            group.definitions.length
                        }}</span>
                    </button>
                </nav>

                <section class="border border-stone-800 bg-stone-900 p-5">
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="hq-rule-heading text-2xl font-bold">
                            {{ activeGroup?.label }}
                        </h2>
                        <button
                            v-if="editing"
                            class="border border-stone-700 px-3 py-2 text-sm text-stone-300 hover:border-amber-600 hover:text-amber-400"
                            @click="reset"
                        >
                            New element
                        </button>
                    </div>
                    <div class="mt-4 space-y-3">
                        <article
                            v-for="definition in activeGroup?.definitions"
                            :key="definition.id"
                            class="flex items-start justify-between gap-4 bg-stone-950 p-4"
                        >
                            <div class="flex min-w-0 items-start gap-3">
                                <img
                                    v-if="iconUrl(definition.icon)"
                                    :src="iconUrl(definition.icon) || undefined"
                                    :alt="`${definition.name} icon`"
                                    class="size-10 shrink-0 object-contain"
                                />
                                <div class="min-w-0">
                                    <strong>{{ definition.name }}</strong>
                                    <p class="font-mono text-xs text-amber-500">
                                        {{ definition.key }}
                                    </p>
                                    <p
                                        v-if="definition.description"
                                        class="mt-1 text-sm text-stone-400"
                                    >
                                        {{ definition.description }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <button
                                    class="text-sm text-amber-400 hover:text-amber-300"
                                    @click="edit(definition)"
                                >
                                    Edit</button
                                ><button
                                    class="text-sm text-red-400 hover:text-red-300"
                                    @click="remove(definition)"
                                >
                                    Delete
                                </button>
                            </div>
                        </article>
                        <p
                            v-if="!activeGroup?.definitions.length"
                            class="border border-dashed border-stone-700 p-8 text-center text-stone-500"
                        >
                            No elements in this category yet.
                        </p>
                    </div>
                </section>

                <div class="space-y-4">
                    <form
                        class="space-y-3 border border-stone-800 bg-stone-900 p-5"
                        @submit.prevent="importCatalog"
                    >
                        <h2 class="hq-rule-heading text-lg font-bold">
                            Bulk import {{ activeGroup?.label }}
                        </h2>
                        <p class="text-sm text-stone-400">
                            Upload a JSON array. Matching keys are updated and
                            new keys are created for this game-system version.
                        </p>
                        <a
                            :href="`${base}/${selectedVersionId}/${activeType}/export`"
                            class="block border border-stone-700 px-4 py-2 text-center font-semibold text-stone-300 transition hover:border-amber-600 hover:text-amber-400"
                        >
                            Export JSON with field guide
                        </a>
                        <input
                            :key="importInputKey"
                            type="file"
                            accept="application/json,.json"
                            required
                            class="block w-full border border-stone-700 bg-stone-950 p-3 file:mr-3 file:border-0 file:bg-stone-700 file:px-3 file:py-2 file:font-semibold file:text-stone-100"
                            @change="
                                importForm.catalog_file =
                                    ($event.target as HTMLInputElement)
                                        .files?.[0] || null
                            "
                        />
                        <p
                            v-if="importForm.errors.catalog_file"
                            class="text-sm text-red-400"
                        >
                            {{ importForm.errors.catalog_file }}
                        </p>
                        <button
                            :disabled="
                                importForm.processing ||
                                !importForm.catalog_file
                            "
                            class="w-full border border-amber-700 px-4 py-2 font-bold text-amber-400 transition hover:bg-amber-700 hover:text-stone-950 disabled:opacity-40"
                        >
                            Import JSON
                        </button>
                    </form>

                    <form
                        class="space-y-4 border border-stone-800 bg-stone-900 p-5"
                        @submit.prevent="save"
                    >
                        <h2 class="hq-rule-heading text-xl font-bold">
                            {{ editing ? 'Edit' : 'Create' }}
                            {{ activeGroup?.label }}
                        </h2>

                        <label class="block text-sm text-stone-300"
                            >Name<input
                                v-model="form.name"
                                required
                                class="mt-1 w-full border border-stone-700 bg-stone-950 p-3"
                        /></label>
                        <label class="block text-sm text-stone-300"
                            >Key<input
                                v-model="form.key"
                                placeholder="generated_from_name"
                                class="mt-1 w-full border border-stone-700 bg-stone-950 p-3 font-mono"
                        /></label>
                        <label
                            v-if="canDescribe"
                            class="block text-sm text-stone-300"
                            >Description<textarea
                                v-model="form.description"
                                rows="3"
                                class="mt-1 w-full border border-stone-700 bg-stone-950 p-3"
                            />
                        </label>

                        <div v-if="canOrder">
                            <label class="block text-sm text-stone-300"
                                >Display order<input
                                    v-model.number="form.display_order"
                                    type="number"
                                    min="0"
                                    class="mt-1 w-full border border-stone-700 bg-stone-950 p-3"
                            /></label>
                        </div>

                        <label
                            v-if="canHaveIcon"
                            class="block text-sm text-stone-300"
                        >
                            Icon image
                            <input
                                type="file"
                                accept="image/png,image/jpeg,image/webp,image/gif"
                                class="mt-1 block w-full border border-stone-700 bg-stone-950 p-3 file:mr-3 file:border-0 file:bg-amber-600 file:px-3 file:py-2 file:font-bold file:text-stone-950"
                                @change="
                                    form.icon_upload =
                                        ($event.target as HTMLInputElement)
                                            .files?.[0] || null
                                "
                            />
                            <span class="mt-1 block text-xs text-stone-500">
                                PNG, JPEG, WebP, or GIF. Maximum 2 MB.
                            </span>
                        </label>

                        <section
                            v-if="activeType === 'stats'"
                            class="space-y-3"
                        >
                            <label class="block text-sm text-stone-300"
                                >Data type<select
                                    v-model="form.data_type"
                                    class="mt-1 w-full border border-stone-700 bg-stone-950 p-3"
                                >
                                    <option>integer</option>
                                    <option>decimal</option>
                                    <option>text</option>
                                    <option>boolean</option>
                                </select></label
                            >
                            <div class="grid grid-cols-2 gap-3">
                                <label class="block text-sm text-stone-300"
                                    >Default<input
                                        v-model="form.default_value"
                                        class="mt-1 w-full border border-stone-700 bg-stone-950 p-3"
                                /></label>
                                <label class="block text-sm text-stone-300"
                                    >Default max<input
                                        v-model.number="form.default_max_value"
                                        type="number"
                                        class="mt-1 w-full border border-stone-700 bg-stone-950 p-3"
                                /></label>
                                <label class="block text-sm text-stone-300"
                                    >Minimum<input
                                        v-model.number="form.minimum_value"
                                        type="number"
                                        class="mt-1 w-full border border-stone-700 bg-stone-950 p-3"
                                /></label>
                                <label class="block text-sm text-stone-300"
                                    >Maximum<input
                                        v-model.number="form.maximum_value"
                                        type="number"
                                        class="mt-1 w-full border border-stone-700 bg-stone-950 p-3"
                                /></label>
                            </div>
                            <label
                                class="flex items-center gap-2 text-sm text-stone-300"
                                ><input
                                    v-model="form.is_public"
                                    type="checkbox"
                                />
                                Visible to spectators</label
                            >
                        </section>

                        <label
                            v-if="activeType === 'slots'"
                            class="block text-sm text-stone-300"
                            >Capacity<input
                                v-model.number="form.capacity"
                                type="number"
                                min="1"
                                class="mt-1 w-full border border-stone-700 bg-stone-950 p-3"
                        /></label>

                        <section
                            v-if="activeType === 'trays'"
                            class="space-y-3"
                        >
                            <label
                                class="flex items-center gap-2 text-sm text-stone-300"
                                ><input
                                    v-model="form.is_collapsible"
                                    type="checkbox"
                                />
                                Collapsible</label
                            >
                            <label
                                class="flex items-center gap-2 text-sm text-stone-300"
                                ><input
                                    v-model="form.default_open"
                                    type="checkbox"
                                />
                                Open by default</label
                            >
                        </section>

                        <label
                            v-if="activeType === 'classes'"
                            class="block text-sm text-stone-300"
                            >Default stats JSON<textarea
                                v-model="form.default_stats_json"
                                rows="4"
                                placeholder='{"body": 8, "mind": 2}'
                                class="mt-1 w-full border border-stone-700 bg-stone-950 p-3 font-mono text-xs"
                            />
                        </label>

                        <section
                            v-if="
                                ['items', 'spells', 'skills'].includes(
                                    activeType,
                                )
                            "
                            class="space-y-3"
                        >
                            <label class="block text-sm text-stone-300"
                                >Category<input
                                    v-model="form.category"
                                    placeholder="weapon, armor, air, fire..."
                                    class="mt-1 w-full border border-stone-700 bg-stone-950 p-3"
                            /></label>
                            <label
                                v-if="activeType === 'items'"
                                class="block text-sm text-stone-300"
                                >Required slots JSON<textarea
                                    v-model="form.required_slots_json"
                                    rows="3"
                                    placeholder='["left_hand", "right_hand"]'
                                    class="mt-1 w-full border border-stone-700 bg-stone-950 p-3 font-mono text-xs"
                                />
                            </label>
                            <label
                                v-if="activeType !== 'items'"
                                class="block text-sm text-stone-300"
                                >Charges<input
                                    v-model.number="form.charges"
                                    type="number"
                                    min="0"
                                    class="mt-1 w-full border border-stone-700 bg-stone-950 p-3"
                            /></label>
                        </section>

                        <label class="block text-sm text-stone-300"
                            >Extra configuration JSON<textarea
                                v-model="form.configuration_json"
                                rows="5"
                                placeholder="{}"
                                class="mt-1 w-full border border-stone-700 bg-stone-950 p-3 font-mono text-xs"
                            />
                        </label>

                        <div
                            v-if="Object.keys(form.errors).length"
                            class="bg-red-950 p-3 text-sm text-red-300"
                        >
                            <p v-for="error in form.errors" :key="error">
                                {{ error }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button
                                :disabled="form.processing"
                                class="flex-1 bg-amber-600 p-3 font-bold text-stone-950 transition hover:bg-amber-500 active:scale-95 disabled:opacity-50"
                            >
                                Save element</button
                            ><button
                                v-if="editing"
                                type="button"
                                class="border border-stone-700 px-4 text-stone-300 hover:border-amber-600 hover:text-amber-400"
                                @click="reset"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <p v-else class="mt-8 text-stone-400">
                An administrator must create a game system version first.
            </p>
        </div>
    </main>
</template>
