<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    fields: { type: Array, default: () => [] },
    campaignId: { type: [Number, String], required: true },
    previewLimit: { type: Number, default: 12 },
});

const expanded = ref(false);
const search = ref('');

const fieldCount = computed(() => props.fields.length);
const pingFieldCount = computed(() => props.fields.filter((f) => f.ping_field).length);
const requiredCount = computed(() => props.fields.filter((f) => f.required).length);

const filteredFields = computed(() => {
    const query = search.value.trim().toLowerCase();
    if (!query) {
        return props.fields;
    }

    return props.fields.filter((field) => {
        const label = (field.label ?? '').toLowerCase();
        const name = (field.name ?? '').toLowerCase();
        const type = (field.type ?? '').toLowerCase();

        return name.includes(query) || label.includes(query) || type.includes(query);
    });
});

const visibleFields = computed(() => {
    if (expanded.value || search.value.trim() || fieldCount.value <= props.previewLimit) {
        return filteredFields.value;
    }

    return props.fields.slice(0, props.previewLimit);
});

const hiddenCount = computed(() => Math.max(0, fieldCount.value - props.previewLimit));

const statusLabel = computed(() => {
    if (search.value.trim()) {
        return `${filteredFields.value.length} of ${fieldCount.value} fields match`;
    }

    if (expanded.value || fieldCount.value <= props.previewLimit) {
        return `All ${fieldCount.value} fields`;
    }

    return `Showing ${props.previewLimit} of ${fieldCount.value} fields`;
});

watch(search, (value) => {
    if (value.trim()) {
        expanded.value = true;
    }
});

const toggleExpanded = () => {
    expanded.value = !expanded.value;
    if (!expanded.value) {
        search.value = '';
    }
};
</script>

<template>
    <div>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-sm text-slate-500">Fields used for API ingest, validation, and form builder.</p>
                <p class="mt-1 text-xs text-slate-400">
                    {{ statusLabel }}
                    <span v-if="requiredCount"> · {{ requiredCount }} required</span>
                    <span v-if="pingFieldCount"> · {{ pingFieldCount }} ping</span>
                </p>
            </div>
            <Link :href="route('campaigns.api-spec', campaignId)" class="shrink-0 text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-400">
                Edit API spec →
            </Link>
        </div>

        <div v-if="fieldCount > previewLimit" class="mb-3 flex flex-wrap items-center gap-2">
            <input
                v-if="expanded || search"
                v-model="search"
                type="search"
                class="form-input max-w-xs !py-1.5 !text-sm"
                placeholder="Search fields…"
            />
            <button
                v-if="hiddenCount > 0 || expanded"
                type="button"
                class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                @click="toggleExpanded"
            >
                <template v-if="expanded">Show less</template>
                <template v-else>Show all {{ fieldCount }} fields</template>
            </button>
        </div>

        <div
            v-if="visibleFields.length"
            :class="[
                'flex flex-wrap gap-2',
                expanded || search ? 'max-h-72 overflow-y-auto pr-1' : '',
            ]"
        >
            <span
                v-for="field in visibleFields"
                :key="field.id ?? field.name"
                :title="[field.label, field.type, field.required ? 'Required' : null, field.ping_field ? 'Ping field' : null].filter(Boolean).join(' · ')"
                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
            >
                <span>{{ field.name }}</span>
                <span v-if="field.required" class="text-rose-500" aria-label="Required">*</span>
                <span v-if="field.ping_field" class="rounded bg-indigo-100 px-1 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                    ping
                </span>
            </span>
        </div>

        <p v-else-if="search" class="text-sm text-slate-500">No fields match your search.</p>
        <p v-else class="text-sm text-slate-500">No fields defined yet. Add fields in the API spec editor.</p>

        <p v-if="!expanded && hiddenCount > 0 && !search" class="mt-3 text-xs text-slate-400">
            + {{ hiddenCount }} more — open API spec to edit the full schema.
        </p>
    </div>
</template>
