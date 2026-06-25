<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    provider: String,
    meta: Object,
    config: Object,
    campaigns: Array,
    webhookUrl: String,
    ingestUrl: String,
});

const form = useForm({
    enabled: props.config?.enabled ?? false,
    verify_token: props.config?.verify_token ?? '',
    campaign_id: props.config?.campaign_id ?? '',
});

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

        <div class="grid gap-6 lg:grid-cols-2">
            <Panel title="Connection">
                <form class="space-y-4" @submit.prevent="submit">
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
                    </div>
                    <div>
                        <InputLabel value="Verify token" />
                        <input v-model="form.verify_token" type="text" class="form-input mt-1 w-full font-mono text-sm" />
                        <p class="mt-1 text-xs text-slate-500">Auto-generated if left blank on save.</p>
                    </div>
                    <AppButton type="submit" :disabled="form.processing">Save settings</AppButton>
                </form>
            </Panel>

            <Panel title="Endpoints">
                <div class="space-y-4 text-sm text-slate-600 dark:text-slate-400">
                    <div>
                        <p class="font-medium text-slate-800 dark:text-slate-200">Webhook URL</p>
                        <code class="mt-1 block break-all rounded-lg bg-slate-100 p-3 text-xs dark:bg-slate-800">{{ webhookUrl }}</code>
                    </div>
                    <div>
                        <p class="font-medium text-slate-800 dark:text-slate-200">Direct ingest URL</p>
                        <code class="mt-1 block break-all rounded-lg bg-slate-100 p-3 text-xs dark:bg-slate-800">{{ ingestUrl }}</code>
                    </div>
                    <p class="text-xs text-slate-500">
                        Map lead fields to your campaign API spec after connecting. Incoming payloads are queued for validation and distribution.
                    </p>
                </div>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
