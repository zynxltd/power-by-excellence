<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    provider: String,
    meta: Object,
    config: Object,
    campaigns: Array,
    webhookUrl: String,
    ingestUrl: String,
});

const copied = ref('');

const form = useForm({
    enabled: props.config?.enabled ?? false,
    verify_token: props.config?.verify_token ?? '',
    page_access_token: props.config?.page_access_token ?? '',
    campaign_id: props.config?.campaign_id ?? '',
});

watch(
    () => props.config,
    (config) => {
        form.defaults({
            enabled: config?.enabled ?? false,
            verify_token: config?.verify_token ?? '',
            page_access_token: config?.page_access_token ?? '',
            campaign_id: config?.campaign_id ?? '',
        });
        form.reset();
    },
    { deep: true },
);

const isConfigured = computed(() => (
    form.enabled && form.campaign_id && form.verify_token
));

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

        <div
            v-if="isConfigured"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-100"
        >
            Integration enabled. Use the verify token below in Facebook’s webhook setup, then paste your Page access token to pull lead fields automatically.
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
                            <option value="">— Select campaign —</option>
                            <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }} ({{ c.reference }})</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.campaign_id" />
                    </div>
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <InputLabel value="Verify token" />
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
                        <p class="mt-1 text-xs text-slate-500">Paste this into Facebook’s webhook “Verify token” field. Auto-generated when left blank on save.</p>
                        <InputError class="mt-1" :message="form.errors.verify_token" />
                    </div>
                    <div v-if="provider === 'facebook'">
                        <div class="flex items-center justify-between gap-2">
                            <InputLabel value="Page access token (optional)" />
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
                        <p class="mt-1 text-xs text-slate-500">Required to fetch lead name/email/phone from Facebook when webhooks fire. Without it, only direct ingest works.</p>
                        <InputError class="mt-1" :message="form.errors.page_access_token" />
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
                        <p class="mt-1 text-xs text-slate-500">Use in Facebook App → Webhooks → Page → leadgen.</p>
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
                        <p class="mt-1 text-xs text-slate-500">POST JSON with campaign field names (email, firstname, phone1, etc.) for testing or Zapier.</p>
                    </div>
                </div>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
