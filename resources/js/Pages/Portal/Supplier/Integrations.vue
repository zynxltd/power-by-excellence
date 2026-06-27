<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    apiBaseUrl: String,
    tenantHost: String,
    currency: String,
    partner: Object,
    sources: { type: Array, default: () => [] },
    campaigns: { type: Array, default: () => [] },
    selectedCampaign: { type: Object, default: null },
    selectedSpec: { type: Object, default: null },
    sampleIngest: { type: Object, default: null },
    sampleStatus: { type: Object, default: null },
    apiKeys: { type: Array, default: () => [] },
    postbacks: { type: Array, default: () => [] },
    defaultPostbackUrl: String,
    endpoints: { type: Array, default: () => [] },
    guides: { type: Array, default: () => [] },
});

const copied = ref('');
const campaignId = ref(props.selectedCampaign?.id ?? '');

const ingestCurl = computed(() => {
    const body = JSON.stringify(props.sampleIngest ?? { campaign_reference: 'your-campaign', sid: 'your_sid', test: true }, null, 2);
    return `curl -X POST '${props.apiBaseUrl}/leads' \\\n`
        + `  -H 'Authorization: Bearer your_prefix|your_secret' \\\n`
        + `  -H 'Content-Type: application/json' \\\n`
        + `  -d '${body.replace(/'/g, "'\\''")}'`;
});

const statusCurl = computed(() => (
    `curl '${props.apiBaseUrl}/leads/your-lead-uuid' \\\n`
    + `  -H 'Authorization: Bearer your_prefix|your_secret' \\\n`
    + `  -H 'Accept: application/json'`
));

const loadCampaign = () => {
    if (!campaignId.value) {
        router.get(route('portal.supplier.integrations'));
        return;
    }
    router.get(route('portal.supplier.integrations', { campaign_id: campaignId.value }), {}, { preserveState: true });
};

const copyText = async (text, key) => {
    await navigator.clipboard.writeText(text);
    copied.value = key;
    setTimeout(() => { copied.value = ''; }, 2000);
};

const methodClass = (method) => {
    const verb = method?.split('/')[0]?.toUpperCase();
    return {
        GET: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
        POST: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
    }[verb] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
};
</script>

<template>
    <Head title="Integrations & API" />
    <AuthenticatedLayout>
        <PageHeader
            title="Integrations & API"
            :description="`Submit leads, poll status, receive postbacks, and export data for ${partner?.name ?? 'your supplier account'}.`"
        >
            <template #actions>
                <AppButton :href="route('portal.supplier.embeds')" variant="secondary">Form embeds</AppButton>
                <AppButton :href="route('portal.supplier.leads.download')" variant="secondary" external>Download CSV</AppButton>
            </template>
        </PageHeader>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">API base URL</p>
                <p class="mt-1 break-all font-mono text-sm text-indigo-600 dark:text-indigo-400">{{ apiBaseUrl }}</p>
                <button type="button" class="mt-2 text-xs font-medium text-indigo-600 hover:underline" @click="copyText(apiBaseUrl, 'base')">
                    {{ copied === 'base' ? 'Copied' : 'Copy' }}
                </button>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Your API keys</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ apiKeys.length }}</p>
                <p class="text-xs text-slate-500">Prefix shown only — secrets are not stored in the portal</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Postbacks</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ postbacks.length }}</p>
                <p v-if="defaultPostbackUrl" class="mt-1 truncate font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ defaultPostbackUrl }}</p>
            </div>
        </div>

        <Panel title="Endpoints" class="mb-6">
            <div class="space-y-4">
                <div v-for="endpoint in endpoints" :key="endpoint.key" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded px-2 py-0.5 text-xs font-bold uppercase" :class="methodClass(endpoint.method)">{{ endpoint.method.split('/')[0] }}</span>
                        <code class="font-mono text-sm text-slate-800 dark:text-slate-200">{{ endpoint.path }}</code>
                        <span v-if="endpoint.scope" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400">{{ endpoint.scope }}</span>
                    </div>
                    <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">{{ endpoint.summary }}</p>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ endpoint.description }}</p>
                </div>
            </div>
        </Panel>

        <div class="grid gap-6 lg:grid-cols-2">
            <Panel title="Submit a lead">
                <div v-if="campaigns.length" class="mb-4">
                    <InputLabel value="Campaign schema" />
                    <select v-model="campaignId" class="form-select mt-1 w-full" @change="loadCampaign">
                        <option value="">Select campaign…</option>
                        <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }} ({{ c.reference }})</option>
                    </select>
                </div>
                <p class="mb-3 text-sm text-slate-600 dark:text-slate-400">
                    Include your <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">sid</code> on every request. Use
                    <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">test: true</code> for validation runs.
                </p>
                <code class="block overflow-x-auto rounded-xl bg-slate-50 p-4 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ ingestCurl }}</code>
                <button type="button" class="mt-3 text-sm font-semibold text-indigo-600 hover:underline" @click="copyText(ingestCurl, 'ingest')">
                    {{ copied === 'ingest' ? 'Copied' : 'Copy ingest curl' }}
                </button>
                <div v-if="selectedSpec?.fields?.length" class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead><tr class="text-slate-500"><th class="py-1 pr-4">Field</th><th class="py-1 pr-4">Required</th><th class="py-1">Example</th></tr></thead>
                        <tbody>
                            <tr v-for="field in selectedSpec.fields" :key="field.name" class="border-t border-slate-100 dark:border-slate-800">
                                <td class="py-1.5 pr-4 font-mono">{{ field.name }}</td>
                                <td class="py-1.5 pr-4">{{ field.required ? 'Yes' : 'No' }}</td>
                                <td class="py-1.5 text-slate-500">{{ field.example }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Panel>

            <Panel title="Poll status & export">
                <p class="mb-3 text-sm font-semibold text-slate-900 dark:text-white">GET lead status</p>
                <code class="block overflow-x-auto rounded-xl bg-slate-50 p-4 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ statusCurl }}</code>
                <button type="button" class="mt-2 text-sm font-semibold text-indigo-600 hover:underline" @click="copyText(statusCurl, 'status')">
                    {{ copied === 'status' ? 'Copied' : 'Copy status curl' }}
                </button>
                <pre v-if="sampleStatus" class="mt-4 overflow-x-auto rounded-xl bg-slate-950 p-4 text-xs text-emerald-300">{{ JSON.stringify(sampleStatus, null, 2) }}</pre>

                <div v-if="apiKeys.length" class="mt-6 border-t border-slate-200 pt-4 dark:border-slate-700">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Your API keys</p>
                    <ul class="mt-2 space-y-2">
                        <li v-for="key in apiKeys" :key="key.prefix" class="rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/50">
                            <span class="font-medium">{{ key.name }}</span>
                            <span class="ml-2 font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ key.prefix }}…</span>
                            <span class="mt-1 block text-xs text-slate-500">{{ key.permissions?.join(', ') }}</span>
                        </li>
                    </ul>
                </div>
                <p v-else class="mt-6 text-sm text-slate-500">No API keys linked to your supplier yet. Ask your platform administrator to create a supplier key.</p>
            </Panel>
        </div>

        <Panel v-if="sources?.length" title="Tracking (SID)" class="mt-6">
            <div class="flex flex-wrap gap-2">
                <span v-for="source in sources" :key="source.sid" class="rounded-lg bg-indigo-50 px-3 py-1.5 font-mono text-sm text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">
                    {{ source.sid }}
                    <span class="ml-1 font-sans text-xs text-indigo-500">{{ source.name }}</span>
                </span>
            </div>
        </Panel>

        <Panel v-if="postbacks?.length" title="Postback events" class="mt-6">
            <ul class="space-y-2">
                <li v-for="postback in postbacks" :key="postback.name" class="text-sm">
                    <span class="font-medium text-slate-900 dark:text-white">{{ postback.name }}</span>
                    <span class="ml-2 text-xs uppercase text-slate-500">{{ postback.method }}</span>
                    <span class="ml-2 text-xs text-slate-500">{{ postback.events?.join(', ') }}</span>
                </li>
            </ul>
        </Panel>

        <Panel title="Guides" class="mt-6">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div v-for="guide in guides" :key="guide.title">
                    <dt class="text-sm font-semibold text-slate-900 dark:text-white">{{ guide.title }}</dt>
                    <dd class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ guide.body }}</dd>
                </div>
            </dl>
        </Panel>
    </AuthenticatedLayout>
</template>
