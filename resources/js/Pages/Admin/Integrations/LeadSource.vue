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

const form = useForm({
    enabled: props.config?.enabled ?? false,
    verify_token: props.config?.verify_token ?? '',
    page_access_token: props.config?.page_access_token ?? '',
    campaign_id: props.config?.campaign_id ?? '',
    field_mapping: props.config?.field_mapping?.length
        ? [...props.config.field_mapping]
        : [{ source: '', target: '' }],
});

watch(
    () => props.config,
    (config) => {
        form.defaults({
            enabled: config?.enabled ?? false,
            verify_token: config?.verify_token ?? '',
            page_access_token: config?.page_access_token ?? '',
            campaign_id: config?.campaign_id ?? '',
            field_mapping: config?.field_mapping?.length
                ? [...config.field_mapping]
                : [{ source: '', target: '' }],
        });
        form.reset();
    },
    { deep: true },
);

watch(
    () => form.campaign_id,
    (campaignId) => {
        if (campaignId) {
            router.get(route('integrations.lead-source', props.provider), { campaign_id: campaignId }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }
    },
);

const campaignFieldOptions = computed(() => props.campaignFields ?? []);

const addMappingRow = () => form.field_mapping.push({ source: '', target: '' });
const removeMappingRow = (index) => {
    if (form.field_mapping.length > 1) {
        form.field_mapping.splice(index, 1);
    }
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

const submit = () => form.put(route('integrations.lead-source.update', props.provider));
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
                        <div class="mb-2 flex items-center justify-between">
                            <InputLabel value="Field mapping" />
                            <button type="button" class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400" @click="addMappingRow">
                                + Add row
                            </button>
                        </div>
                        <p class="mb-3 text-xs text-slate-500">Map source platform field names to your campaign field names.</p>
                        <div class="space-y-2">
                            <div
                                v-for="(row, index) in form.field_mapping"
                                :key="index"
                                class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]"
                            >
                                <input v-model="row.source" type="text" class="form-input font-mono text-sm" placeholder="Source field (e.g. email)" />
                                <select v-model="row.target" class="form-select">
                                    <option value="">Campaign field</option>
                                    <option v-for="field in campaignFieldOptions" :key="field.id ?? field.name" :value="field.name">
                                        {{ field.label ?? field.name }}
                                    </option>
                                </select>
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
