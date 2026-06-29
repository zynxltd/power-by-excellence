<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    provider: String,
    meta: Object,
    config: Object,
    campaigns: Array,
    campaignFields: Array,
    webhookUrl: String,
    ingestUrl: String,
});

const copied = ref('');

const mappingToRows = (mapping) => {
    if (!mapping || typeof mapping !== 'object') {
        return [{ source: '', target: '' }];
    }

    if (Array.isArray(mapping)) {
        return mapping.length
            ? mapping.map((row) => ({ source: row.source ?? '', target: row.target ?? '' }))
            : [{ source: '', target: '' }];
    }

    const rows = Object.entries(mapping).map(([source, target]) => ({ source, target }));

    return rows.length ? rows : [{ source: '', target: '' }];
};

const mappingToObject = (rows) => rows.reduce((acc, row) => {
    if (row.source && row.target) {
        acc[row.source] = row.target;
    }

    return acc;
}, {});

const form = useForm({
    enabled: props.config?.enabled ?? false,
    verify_token: props.config?.verify_token ?? '',
    page_access_token: props.config?.page_access_token ?? '',
    campaign_id: props.config?.campaign_id ?? '',
    field_mapping: mappingToRows(props.config?.field_mapping),
});

watch(
    () => props.config,
    (config) => {
        form.defaults({
            enabled: config?.enabled ?? false,
            verify_token: config?.verify_token ?? '',
            page_access_token: config?.page_access_token ?? '',
            campaign_id: config?.campaign_id ?? '',
            field_mapping: mappingToRows(config?.field_mapping),
        });
        form.reset();
    },
    { deep: true },
);

watch(
    () => form.campaign_id,
    (campaignId, previousId) => {
        if (campaignId && campaignId !== previousId) {
            router.get(route('integrations.lead-source', props.provider), { campaign_id: campaignId }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }
    },
);

const campaignFieldOptions = computed(() => props.campaignFields ?? []);
const mappedRowCount = computed(() => form.field_mapping.filter((row) => row.source && row.target).length);

const mappedTargets = computed(() => new Set(
    form.field_mapping.filter((row) => row.target).map((row) => row.target),
));

const unmappedCampaignFields = computed(() => campaignFieldOptions.value.filter(
    (field) => !mappedTargets.value.has(field.name),
));

const addMappingRowForTarget = (fieldName) => {
    const emptyRow = form.field_mapping.find((row) => !row.source && !row.target);
    if (emptyRow) {
        emptyRow.target = fieldName;
        return;
    }

    form.field_mapping.push({ source: '', target: fieldName });
};

const moveMappingRow = (index, direction) => {
    const targetIndex = index + direction;
    if (targetIndex < 0 || targetIndex >= form.field_mapping.length) {
        return;
    }

    const rows = [...form.field_mapping];
    [rows[index], rows[targetIndex]] = [rows[targetIndex], rows[index]];
    form.field_mapping = rows;
};

const addMappingRow = () => form.field_mapping.push({ source: '', target: '' });
const removeMappingRow = (index) => {
    if (form.field_mapping.length > 1) {
        form.field_mapping.splice(index, 1);
    }
};

const applySuggestedMappings = () => {
    const suggestions = props.meta?.suggested_field_mappings ?? {};
    const rows = Object.entries(suggestions).map(([source, target]) => ({ source, target }));
    form.field_mapping = rows.length ? rows : [{ source: '', target: '' }];
};

const clearMappings = () => {
    form.field_mapping = [{ source: '', target: '' }];
};

const isConfigured = computed(() => {
    if (!form.enabled || !form.campaign_id) {
        return false;
    }

    if (props.meta?.requires_verify_token) {
        return Boolean(form.verify_token);
    }

    return true;
});

const copyText = async (key, value) => {
    if (!value) {
        return;
    }

    await navigator.clipboard.writeText(value);
    copied.value = key;
    setTimeout(() => { copied.value = ''; }, 2000);
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        field_mapping: mappingToObject(data.field_mapping),
    })).put(route('integrations.lead-source.update', props.provider));
};
</script>

<template>
    <Head :title="meta.name" />
    <AuthenticatedLayout>
        <PageHeader :title="meta.name" :description="meta.description">
            <template #actions>
                <Link :href="route('integrations.index')" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                    ← Integrations
                </Link>
            </template>
        </PageHeader>

        <Panel class="mb-6" title="How this connects">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                This integration uses <strong>webhook push</strong> - {{ meta.name }} (or a tool like Zapier/Make) POSTs lead data to your platform.
                There is no in-app OAuth “Connect with Google” button; you configure the webhook or ingest URL in the ad platform or automation tool.
            </p>
            <p v-if="meta.oauth === false && provider === 'facebook'" class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                Meta Lead Ads also needs a <strong>Page access token</strong> (pasted below) so we can fetch full lead fields from the Graph API when webhooks fire.
            </p>
            <ol v-if="meta.setup_steps?.length" class="mt-3 list-decimal space-y-1 pl-5 text-sm text-slate-600 dark:text-slate-400">
                <li v-for="(step, index) in meta.setup_steps" :key="index">{{ step }}</li>
            </ol>
        </Panel>

        <div
            v-if="isConfigured"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-100"
        >
            {{ meta.configured_message }}
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <Panel title="Connection">
                <form class="space-y-4" @submit.prevent="submit">
                    <FormErrorSummary :errors="form.errors" />
                    <label class="flex items-center gap-3">
                        <input v-model="form.enabled" type="checkbox" class="rounded border-slate-300" />
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Enable {{ meta.name }}</span>
                    </label>
                    <div>
                        <InputLabel value="Target campaign" />
                        <select v-model="form.campaign_id" class="form-select mt-1 w-full">
                            <option value="">- Select campaign -</option>
                            <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }} ({{ c.reference }})</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.campaign_id" />
                    </div>
                    <div v-if="meta.show_verify_token">
                        <div class="flex items-center justify-between gap-2">
                            <InputLabel :value="meta.verify_token_label" />
                            <button
                                v-if="form.verify_token"
                                type="button"
                                class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                @click="copyText('token', form.verify_token)"
                            >
                                {{ copied === 'token' ? 'Copied' : 'Copy' }}
                            </button>
                        </div>
                        <input v-model="form.verify_token" type="text" class="form-input mt-1 w-full font-mono text-sm" placeholder="Save to generate" />
                        <p class="mt-1 text-xs text-slate-500">{{ meta.verify_token_help }}</p>
                        <InputError class="mt-1" :message="form.errors.verify_token" />
                    </div>
                    <div v-if="meta.show_page_access_token">
                        <div class="flex items-center justify-between gap-2">
                            <InputLabel :value="meta.page_access_token_label" />
                            <button
                                v-if="form.page_access_token"
                                type="button"
                                class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                @click="copyText('page', form.page_access_token)"
                            >
                                {{ copied === 'page' ? 'Copied' : 'Copy' }}
                            </button>
                        </div>
                        <input v-model="form.page_access_token" type="password" class="form-input mt-1 w-full font-mono text-sm" autocomplete="off" />
                        <p class="mt-1 text-xs text-slate-500">{{ meta.page_access_token_help }}</p>
                        <InputError class="mt-1" :message="form.errors.page_access_token" />
                    </div>

                    <div v-if="form.campaign_id">
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <InputLabel value="Field mapping" />
                                <p class="text-xs text-slate-500">
                                    {{ mappedRowCount }} mapped field{{ mappedRowCount === 1 ? '' : 's' }}
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <button
                                    v-if="meta.suggested_field_mappings && Object.keys(meta.suggested_field_mappings).length"
                                    type="button"
                                    class="text-xs font-medium text-slate-600 hover:underline dark:text-slate-400"
                                    @click="applySuggestedMappings"
                                >
                                    Apply suggestions
                                </button>
                                <button
                                    v-if="mappedRowCount > 0"
                                    type="button"
                                    class="text-xs font-medium text-slate-600 hover:underline dark:text-slate-400"
                                    @click="clearMappings"
                                >
                                    Clear
                                </button>
                                <button type="button" class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400" @click="addMappingRow">
                                    + Add row
                                </button>
                            </div>
                        </div>
                        <p class="mb-3 text-xs text-slate-500">
                            Map {{ meta.name }} field names (left) to your campaign fields (right). Unmapped source fields pass through when names already match.
                        </p>
                        <InputError class="mb-2" :message="form.errors.field_mapping" />
                        <div v-if="campaignFieldOptions.length" class="mb-3 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/40">
                            <p class="mb-2 text-xs font-medium text-slate-600 dark:text-slate-300">Campaign field coverage</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="field in campaignFieldOptions"
                                    :key="field.id ?? field.name"
                                    type="button"
                                    class="rounded-full px-2.5 py-1 text-xs font-medium transition"
                                    :class="mappedTargets.has(field.name)
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                        : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:ring-indigo-300 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-600'"
                                    :title="mappedTargets.has(field.name) ? 'Mapped' : 'Click to add mapping row'"
                                    @click="!mappedTargets.has(field.name) && addMappingRowForTarget(field.name)"
                                >
                                    {{ field.label ?? field.name }}
                                </button>
                            </div>
                            <p v-if="unmappedCampaignFields.length" class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                                {{ unmappedCampaignFields.length }} campaign field{{ unmappedCampaignFields.length === 1 ? '' : 's' }} not mapped yet.
                            </p>
                        </div>
                        <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                            <div class="grid grid-cols-[1fr_1fr_auto] gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-slate-800/50">
                                <span>Source field</span>
                                <span>Campaign field</span>
                                <span class="sr-only">Reorder / remove</span>
                            </div>
                            <div class="divide-y divide-slate-200 dark:divide-slate-700">
                                <div
                                    v-for="(row, index) in form.field_mapping"
                                    :key="index"
                                    class="grid gap-2 px-3 py-2 sm:grid-cols-[1fr_1fr_auto]"
                                >
                                    <input v-model="row.source" type="text" class="form-input font-mono text-sm" placeholder="e.g. email_address" />
                                    <select v-model="row.target" class="form-select">
                                        <option value="">Select campaign field</option>
                                        <option v-for="field in campaignFieldOptions" :key="field.id ?? field.name" :value="field.name">
                                            {{ field.label ?? field.name }}
                                        </option>
                                    </select>
                                    <div class="flex items-center gap-2 self-center">
                                        <button
                                            v-if="index > 0"
                                            type="button"
                                            class="text-xs text-slate-500 hover:underline"
                                            title="Move up"
                                            @click="moveMappingRow(index, -1)"
                                        >
                                            ↑
                                        </button>
                                        <button
                                            v-if="index < form.field_mapping.length - 1"
                                            type="button"
                                            class="text-xs text-slate-500 hover:underline"
                                            title="Move down"
                                            @click="moveMappingRow(index, 1)"
                                        >
                                            ↓
                                        </button>
                                        <button
                                            v-if="form.field_mapping.length > 1"
                                            type="button"
                                            class="text-xs text-rose-600 hover:underline"
                                            @click="removeMappingRow(index)"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <AppButton type="submit" :disabled="form.processing">Save settings</AppButton>
                </form>
            </Panel>

            <Panel title="Endpoints">
                <div class="space-y-4 text-sm text-slate-600 dark:text-slate-400">
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-medium text-slate-800 dark:text-slate-200">Webhook URL</p>
                            <button
                                type="button"
                                class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                @click="copyText('webhook', webhookUrl)"
                            >
                                {{ copied === 'webhook' ? 'Copied' : 'Copy' }}
                            </button>
                        </div>
                        <code class="mt-1 block break-all rounded-lg bg-slate-100 p-3 text-xs dark:bg-slate-800">{{ webhookUrl }}</code>
                        <p class="mt-1 text-xs text-slate-500">{{ meta.webhook_help }}</p>
                    </div>
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-medium text-slate-800 dark:text-slate-200">Direct ingest URL</p>
                            <button
                                type="button"
                                class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                @click="copyText('ingest', ingestUrl)"
                            >
                                {{ copied === 'ingest' ? 'Copied' : 'Copy' }}
                            </button>
                        </div>
                        <code class="mt-1 block break-all rounded-lg bg-slate-100 p-3 text-xs dark:bg-slate-800">{{ ingestUrl }}</code>
                        <p class="mt-1 text-xs text-slate-500">{{ meta.ingest_help }}</p>
                    </div>
                </div>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
