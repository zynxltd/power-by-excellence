<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    apiBaseUrl: String,
    tenantHost: String,
    accountName: String,
    campaigns: { type: Array, default: () => [] },
    selectedCampaign: { type: Object, default: null },
    selectedSpec: { type: Object, default: null },
    sampleRequest: { type: Object, default: null },
    sampleResponse: Object,
    sampleStatusResponse: Object,
    endpoints: { type: Array, default: () => [] },
    permissions: { type: Array, default: () => [] },
    statusFields: { type: Array, default: () => [] },
    leadStatuses: { type: Array, default: () => [] },
    guides: { type: Array, default: () => [] },
    platformGuides: { type: Array, default: () => [] },
    samplePlatformExport: { type: Object, default: () => ({}) },
});

const activeTab = ref('overview');
const copied = ref('');
const testMode = ref(true);
const campaignId = ref(props.selectedCampaign?.id ?? '');

const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'auth', label: 'Authentication' },
    { key: 'ingest', label: 'Lead ingest' },
    { key: 'status', label: 'Status polling' },
    { key: 'fields', label: 'Response fields' },
    { key: 'campaigns', label: 'Campaign schemas' },
    { key: 'platform', label: 'Platform export' },
];

const exampleLeadId = computed(() => props.sampleResponse?.lead_id ?? 'your-lead-uuid');
const exampleQueueId = computed(() => props.sampleResponse?.queue_id ?? 'q_example123abc');

const liveSample = computed(() => {
    if (!props.selectedCampaign || !props.selectedSpec?.fields) {
        return {
            campaign_reference: 'your-campaign-ref',
            source: 'api_example',
            ...(testMode.value ? { test: true } : {}),
            firstname: 'Jane',
            email: 'jane@example.com',
        };
    }
    const body = {
        campaign_reference: props.selectedCampaign.reference,
        source: 'api_example',
    };
    if (testMode.value) {
        body.test = true;
    }
    for (const field of props.selectedSpec.fields) {
        body[field.name] = field.example ?? '';
    }
    return body;
});

const ingestCurl = computed(() => {
    const url = `${props.apiBaseUrl}/leads`;
    const json = JSON.stringify(liveSample.value, null, 2);
    return `curl -X POST '${url}' \\\n`
        + `  -H 'Authorization: Bearer your_prefix|your_secret' \\\n`
        + `  -H 'Content-Type: application/json' \\\n`
        + `  -H 'Accept: application/json' \\\n`
        + `  -d '${json.replace(/'/g, "'\\''")}'`;
});

const statusByLeadCurl = computed(() => {
    const url = `${props.apiBaseUrl}/leads/${exampleLeadId.value}`;
    return `curl '${url}' \\\n`
        + `  -H 'Authorization: Bearer your_prefix|your_secret' \\\n`
        + `  -H 'Accept: application/json'`;
});

const statusByQueueCurl = computed(() => {
    const url = `${props.apiBaseUrl}/leads/queue/${exampleQueueId.value}`;
    return `curl '${url}' \\\n`
        + `  -H 'Authorization: Bearer your_prefix|your_secret' \\\n`
        + `  -H 'Accept: application/json'`;
});

const platformExportCurl = computed(() => {
    const url = `${props.apiBaseUrl}/platform`;
    return `curl '${url}' \\\n`
        + `  -H 'Authorization: Bearer your_prefix|your_secret' \\\n`
        + `  -H 'Accept: application/json'`;
});

const platformCampaignCurl = computed(() => {
    const ref = props.selectedCampaign?.reference ?? props.campaigns[0]?.reference ?? 'your-campaign-ref';
    const url = `${props.apiBaseUrl}/platform/campaigns/${ref}`;
    return `curl '${url}' \\\n`
        + `  -H 'Authorization: Bearer your_prefix|your_secret' \\\n`
        + `  -H 'Accept: application/json'`;
});

const platformPartialCurl = computed(() => {
    const url = `${props.apiBaseUrl}/platform?include=campaigns,buyers,suppliers`;
    return `curl '${url}' \\\n`
        + `  -H 'Authorization: Bearer your_prefix|your_secret' \\\n`
        + `  -H 'Accept: application/json'`;
});

const copyText = async (text, key) => {
    await navigator.clipboard.writeText(text);
    copied.value = key;
    setTimeout(() => { copied.value = ''; }, 2000);
};

const loadCampaign = () => {
    if (!campaignId.value) {
        router.get(route('api-docs.index'));
        return;
    }
    router.get(route('api-docs.index', { campaign_id: campaignId.value }), {}, { preserveState: true });
};

const methodClass = (method) => ({
    GET: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    POST: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
}[method?.toUpperCase()] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300');
</script>

<template>
    <Head title="REST API" />
    <AuthenticatedLayout>
        <PageHeader
            title="REST API"
            :description="`Lead ingest, status polling, and integration reference for ${accountName}. All requests use JSON over HTTPS.`"
        >
            <template #actions>
                <AppButton :href="route('api-keys.index')" variant="secondary">API Keys</AppButton>
                <AppButton v-if="selectedCampaign" :href="route('campaigns.api-spec', selectedCampaign.id)">Edit campaign spec</AppButton>
            </template>
        </PageHeader>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Base URL</p>
                <p class="mt-1 break-all font-mono text-sm text-indigo-600 dark:text-indigo-400">{{ apiBaseUrl }}</p>
                <p v-if="tenantHost" class="mt-1 text-xs text-slate-500">Tenant host: {{ tenantHost }}</p>
                <button type="button" class="mt-2 text-xs font-medium text-indigo-600 hover:underline" @click="copyText(apiBaseUrl, 'base')">
                    {{ copied === 'base' ? 'Copied' : 'Copy base URL' }}
                </button>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Primary endpoint</p>
                <p class="mt-1 font-mono text-sm text-slate-900 dark:text-white">POST /leads</p>
                <p class="mt-1 text-xs text-slate-500">Scope: leads.create</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Campaigns</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ campaigns.length }}</p>
                <p class="text-xs text-slate-500">Each has its own field schema</p>
            </div>
        </div>

        <div class="mb-6 flex gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-800 dark:bg-slate-900 lg:hidden">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                :class="[
                    'shrink-0 rounded-lg px-3 py-2 text-sm font-medium transition',
                    activeTab === tab.key
                        ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200'
                        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400',
                ]"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <div class="grid gap-6 lg:grid-cols-12">
            <aside class="hidden space-y-4 lg:col-span-3 lg:block lg:sticky lg:top-6 lg:self-start">
                <Panel title="Sections">
                    <nav class="space-y-1">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            :class="[
                                'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium transition',
                                activeTab === tab.key
                                    ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200'
                                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
                            ]"
                            @click="activeTab = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </nav>
                </Panel>

                <Panel title="Quick copy">
                    <button type="button" class="mb-2 w-full rounded-lg border px-3 py-2 text-left text-xs hover:bg-slate-50 dark:hover:bg-slate-800" @click="copyText(ingestCurl, 'ingest')">
                        {{ copied === 'ingest' ? '✓ Copied ingest cURL' : 'Copy ingest cURL' }}
                    </button>
                    <button type="button" class="mb-2 w-full rounded-lg border px-3 py-2 text-left text-xs hover:bg-slate-50 dark:hover:bg-slate-800" @click="copyText(statusByLeadCurl, 'status-lead')">
                        {{ copied === 'status-lead' ? '✓ Copied status cURL' : 'Copy status cURL (lead_id)' }}
                    </button>
                    <button type="button" class="w-full rounded-lg border px-3 py-2 text-left text-xs hover:bg-slate-50 dark:hover:bg-slate-800" @click="copyText(statusByQueueCurl, 'status-queue')">
                        {{ copied === 'status-queue' ? '✓ Copied status cURL' : 'Copy status cURL (queue_id)' }}
                    </button>
                </Panel>
            </aside>

            <div class="space-y-6 lg:col-span-9">
                <!-- Overview -->
                <div v-show="activeTab === 'overview'" class="space-y-6">
                    <Panel title="How it works">
                        <ol class="list-inside list-decimal space-y-3 text-sm text-slate-600 dark:text-slate-400">
                            <li>Create an API key under <Link :href="route('api-keys.index')" class="text-indigo-600 hover:underline">Tools → API Keys</Link> with <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">leads.create</code> and <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">leads.read</code>.</li>
                            <li>POST a JSON body to <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">{{ apiBaseUrl }}/leads</code> with <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">campaign_reference</code> and campaign fields.</li>
                            <li>Receive <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">202 Accepted</code> with <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">lead_id</code> and <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">queue_id</code>.</li>
                            <li>Poll <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">GET /leads/{lead_id}</code> until status is terminal (<code class="rounded bg-slate-100 px-1 dark:bg-slate-800">sold</code>, <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">unsold</code>, <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">rejected</code>, etc.).</li>
                            <li>When <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">sold</code>, use <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">redirect_url</code> for consumer thank-you pages and <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">revenue</code> for reconciliation.</li>
                            <li>Running your own portal? Use <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">GET /platform</code> (<code class="rounded bg-slate-100 px-1 dark:bg-slate-800">platform.read</code>) to sync campaigns, buyers, and routing - see the <strong>Platform export</strong> tab.</li>
                        </ol>
                    </Panel>

                    <Panel v-for="guide in guides" :key="guide.title" :title="guide.title">
                        <p class="text-sm text-slate-600 dark:text-slate-400">{{ guide.body }}</p>
                    </Panel>

                    <Panel title="API endpoints" :padding="false">
                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            <div v-for="ep in endpoints" :key="ep.key" class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span :class="methodClass(ep.method)" class="rounded px-2 py-0.5 font-mono text-xs font-bold">{{ ep.method }}</span>
                                    <span class="font-mono text-sm text-slate-700 dark:text-slate-300">{{ ep.path }}</span>
                                    <span v-if="ep.scope" class="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">{{ ep.scope }}</span>
                                </div>
                                <p class="mt-1 text-sm font-medium text-slate-900 dark:text-white">{{ ep.summary }}</p>
                                <p class="mt-0.5 text-sm text-slate-500">{{ ep.description }}</p>
                                <p class="mt-1 font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ apiBaseUrl }}{{ ep.path }}</p>
                            </div>
                        </div>
                    </Panel>
                </div>

                <!-- Auth -->
                <div v-show="activeTab === 'auth'" class="space-y-6">
                    <Panel title="Bearer token format">
                        <p class="font-mono text-sm text-slate-700 dark:text-slate-300">Authorization: Bearer {prefix}|{secret}</p>
                        <p class="mt-2 font-mono text-sm text-slate-700 dark:text-slate-300">X-API-Key: {prefix}|{secret}</p>
                        <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">
                            When you create a key, the full token is shown once. The prefix identifies the key record; the secret is verified server-side.
                            Never commit tokens to source control. Keys only work against this tenant's API base URL above.
                        </p>
                        <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs dark:border-slate-700 dark:bg-slate-800/50">
                            <p class="font-semibold text-slate-700 dark:text-slate-300">Example</p>
                            <code class="mt-1 block font-mono text-slate-600 dark:text-slate-400">Authorization: Bearer pbe_live|your_secret_here</code>
                        </div>
                    </Panel>

                    <Panel title="Permissions & scopes" :padding="false">
                        <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-900/60">
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Permission</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Grants access to</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-for="perm in permissions" :key="perm.permission">
                                    <td class="px-4 py-2 font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ perm.permission }}</td>
                                    <td class="px-4 py-2 text-slate-600 dark:text-slate-400">{{ perm.description }}</td>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                    </Panel>

                    <Panel title="HTTP response codes">
                        <ul class="space-y-2 text-sm text-slate-600 dark:text-slate-400">
                            <li><code class="rounded bg-slate-100 px-1 dark:bg-slate-800">202</code> - Lead accepted and queued (async ingest)</li>
                            <li><code class="rounded bg-slate-100 px-1 dark:bg-slate-800">200</code> - Sync ingest finished, or status poll succeeded</li>
                            <li><code class="rounded bg-slate-100 px-1 dark:bg-slate-800">401</code> - Missing, invalid, or expired API key</li>
                            <li><code class="rounded bg-slate-100 px-1 dark:bg-slate-800">403</code> - Key lacks required permission scope</li>
                            <li><code class="rounded bg-slate-100 px-1 dark:bg-slate-800">422</code> - Validation failed - field errors in JSON body</li>
                            <li><code class="rounded bg-slate-100 px-1 dark:bg-slate-800">429</code> - Rate limit exceeded - back off and retry</li>
                        </ul>
                    </Panel>
                </div>

                <!-- Ingest -->
                <div v-show="activeTab === 'ingest'" class="space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">Test mode in examples</p>
                            <p class="mt-0.5 text-sm text-slate-500">Includes <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">"test": true</code> - validates without live buyer delivery.</p>
                        </div>
                        <label class="flex cursor-pointer items-center gap-3">
                            <span class="text-sm font-medium" :class="testMode ? 'text-emerald-600' : 'text-rose-500'">
                                {{ testMode ? 'Test (safe)' : 'Live (delivers)' }}
                            </span>
                            <button
                                type="button"
                                :class="['relative h-7 w-12 rounded-full transition', testMode ? 'bg-emerald-500' : 'bg-rose-500']"
                                @click="testMode = !testMode"
                            >
                                <span :class="['absolute top-0.5 h-6 w-6 rounded-full bg-white shadow transition', testMode ? 'left-5' : 'left-0.5']" />
                            </button>
                        </label>
                    </div>

                    <div class="grid gap-6 lg:grid-cols-2">
                        <Panel :title="testMode ? 'Request body (test)' : 'Request body (live)'">
                            <template #header>
                                <button type="button" class="text-xs font-medium text-indigo-600 hover:underline" @click="copyText(JSON.stringify(liveSample, null, 2), 'body')">
                                    {{ copied === 'body' ? 'Copied' : 'Copy' }}
                                </button>
                            </template>
                            <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-emerald-300">{{ JSON.stringify(liveSample, null, 2) }}</pre>
                        </Panel>
                        <Panel title="cURL">
                            <template #header>
                                <button type="button" class="text-xs font-medium text-indigo-600 hover:underline" @click="copyText(ingestCurl, 'ingest')">
                                    {{ copied === 'ingest' ? 'Copied' : 'Copy' }}
                                </button>
                            </template>
                            <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-cyan-300 whitespace-pre-wrap">{{ ingestCurl }}</pre>
                        </Panel>
                    </div>

                    <Panel title="Async response (202 Accepted)">
                        <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-violet-300">{{ JSON.stringify(sampleResponse, null, 2) }}</pre>
                        <p class="mt-3 text-sm text-slate-500">
                            Store both IDs from this response. Add <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">"sync": true</code> to block until distribution completes and receive the final status object in one call.
                        </p>
                    </Panel>

                    <Panel title="Request body parameters">
                        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="bg-slate-50/90 dark:bg-slate-800/60">
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">Field</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">Required</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">Description</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr>
                                        <td class="px-3 py-2 font-mono text-xs">campaign_reference</td>
                                        <td class="px-3 py-2 text-rose-500">Yes*</td>
                                        <td class="px-3 py-2 text-slate-600 dark:text-slate-400">Campaign slug (e.g. loans-uk). Required unless campaign_id is sent.</td>
                                    </tr>
                                    <tr>
                                        <td class="px-3 py-2 font-mono text-xs">campaign_id</td>
                                        <td class="px-3 py-2 text-rose-500">Yes*</td>
                                        <td class="px-3 py-2 text-slate-600 dark:text-slate-400">Numeric campaign ID. Alternative to campaign_reference.</td>
                                    </tr>
                                    <tr>
                                        <td class="px-3 py-2 font-mono text-xs">test</td>
                                        <td class="px-3 py-2">No</td>
                                        <td class="px-3 py-2 text-slate-600 dark:text-slate-400">When true, validates only - no buyer pings, postbacks, or billing.</td>
                                    </tr>
                                    <tr>
                                        <td class="px-3 py-2 font-mono text-xs">sync</td>
                                        <td class="px-3 py-2">No</td>
                                        <td class="px-3 py-2 text-slate-600 dark:text-slate-400">When true, waits for full pipeline and returns final status (200).</td>
                                    </tr>
                                    <tr>
                                        <td class="px-3 py-2 font-mono text-xs">source</td>
                                        <td class="px-3 py-2">No</td>
                                        <td class="px-3 py-2 text-slate-600 dark:text-slate-400">Supplier tracking label - appears in reports and lead metadata.</td>
                                    </tr>
                                    <tr>
                                        <td class="px-3 py-2 font-mono text-xs">+ campaign fields</td>
                                        <td class="px-3 py-2">Per spec</td>
                                        <td class="px-3 py-2 text-slate-600 dark:text-slate-400">See Campaign schemas tab - each campaign defines required lead fields.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </Panel>
                </div>

                <!-- Status polling -->
                <div v-show="activeTab === 'status'" class="space-y-6">
                    <Panel title="When to poll">
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            After async ingest (202), poll every <strong>1–2 seconds</strong> until <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">status</code> is terminal.
                            Stop on: <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">sold</code>,
                            <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">unsold</code>,
                            <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">rejected</code>,
                            <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">duplicate</code>,
                            or <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">accepted</code> (test mode).
                        </p>
                    </Panel>

                    <div class="grid gap-6 lg:grid-cols-2">
                        <Panel title="GET /leads/{lead_id}">
                            <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-cyan-300 whitespace-pre-wrap">{{ statusByLeadCurl }}</pre>
                        </Panel>
                        <Panel title="GET /leads/queue/{queue_id}">
                            <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-cyan-300 whitespace-pre-wrap">{{ statusByQueueCurl }}</pre>
                        </Panel>
                    </div>

                    <Panel title="Status response example">
                        <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-violet-300">{{ JSON.stringify(sampleStatusResponse, null, 2) }}</pre>
                    </Panel>

                    <Panel title="Lead status lifecycle" :padding="false">
                        <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-900/60">
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Status</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Terminal</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Meaning</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-for="row in leadStatuses" :key="row.status">
                                    <td class="px-4 py-2 font-mono text-xs">{{ row.status }}</td>
                                    <td class="px-4 py-2">
                                        <span :class="row.terminal ? 'text-emerald-600' : 'text-amber-600'">{{ row.terminal ? 'Yes' : 'No' }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-slate-600 dark:text-slate-400">{{ row.description }}</td>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                    </Panel>
                </div>

                <!-- Response fields -->
                <div v-show="activeTab === 'fields'" class="space-y-6">
                    <Panel title="Status response field reference" :padding="false">
                        <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-900/60">
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Field</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Type</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">When present</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Description</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-for="field in statusFields" :key="field.field">
                                    <td class="px-4 py-2 font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ field.field }}</td>
                                    <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ field.type }}</td>
                                    <td class="px-4 py-2 text-xs text-slate-500">{{ field.when }}</td>
                                    <td class="px-4 py-2 text-slate-600 dark:text-slate-400">{{ field.description }}</td>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                    </Panel>

                    <Panel title="reject_reason vs buyer rejections">
                        <div class="space-y-3 text-sm text-slate-600 dark:text-slate-400">
                            <p>
                                <strong class="text-slate-900 dark:text-white">reject_reason</strong> on the status API is only populated when the lead fails
                                <em>before</em> or <em>during</em> platform validation - duplicate email, campaign cap, suppression list, invalid phone, inactive campaign, etc.
                            </p>
                            <p>
                                When a <strong>buyer</strong> rejects a ping or post during routing, the lead may still finish as
                                <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">unsold</code>. The buyer's message is stored in
                                <Link :href="route('logs.delivery')" class="text-indigo-600 hover:underline">Delivery Logs</Link>
                                under <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">ping_response</code> or
                                <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">post_response</code> - not in reject_reason.
                            </p>
                        </div>
                    </Panel>

                    <Panel title="redirect_url resolution">
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            When <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">status</code> is <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">sold</code>, redirect_url is resolved in order:
                        </p>
                        <ol class="mt-3 list-inside list-decimal space-y-1 text-sm text-slate-600 dark:text-slate-400">
                            <li>Ping-tree tier <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">redirect_url</code> (if the winning delivery's tier has one set)</li>
                            <li>Delivery config <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">redirect_url</code></li>
                            <li>Delivery config <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">accept_url</code> (fallback)</li>
                        </ol>
                    </Panel>

                    <Panel title="decline_url resolution">
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            When <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">status</code> is <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">unsold</code> or <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">quarantined</code> after all ping-tree tiers pass, <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">decline_url</code> is returned when configured on the ping tree (final “No tier accepts” step).
                        </p>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                            Use it to send consumers to a fallback or “sorry, no match” page. Tracked via the same <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">/r/{lead_id}</code> redirect endpoint as sold thank-you URLs.
                        </p>
                    </Panel>
                </div>

                <!-- Campaign schemas -->
                <div v-show="activeTab === 'campaigns'" class="space-y-6">
                    <Panel title="Select campaign">
                        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                            Each campaign has its own field schema. Select a campaign to preview its ingest payload, or open the full editor to change fields.
                        </p>
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="min-w-[14rem] flex-1">
                                <label class="text-xs font-semibold uppercase text-slate-500">Campaign</label>
                                <select v-model="campaignId" class="form-select mt-1 w-full">
                                    <option value="">- Select campaign -</option>
                                    <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }} ({{ c.reference }})</option>
                                </select>
                            </div>
                            <AppButton type="button" @click="loadCampaign">Load schema</AppButton>
                            <AppButton v-if="selectedCampaign" :href="route('campaigns.api-spec', selectedCampaign.id)" variant="secondary">
                                Open API spec editor →
                            </AppButton>
                        </div>
                    </Panel>

                    <template v-if="selectedCampaign && selectedSpec">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                                <p class="text-xs font-semibold uppercase text-slate-500">Campaign</p>
                                <p class="mt-1 font-semibold text-slate-900 dark:text-white">{{ selectedCampaign.name }}</p>
                                <p class="font-mono text-xs text-slate-500">{{ selectedCampaign.reference }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                                <p class="text-xs font-semibold uppercase text-slate-500">Fields</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ selectedSpec.fields?.length ?? 0 }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                                <p class="text-xs font-semibold uppercase text-slate-500">Market</p>
                                <p class="mt-1 font-semibold text-slate-900 dark:text-white">{{ selectedCampaign.country }} / {{ selectedCampaign.currency }}</p>
                            </div>
                        </div>

                        <Panel title="Field schema" :padding="false">
                            <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-900/60">
                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Name</th>
                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Type</th>
                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Flags</th>
                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Example</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr v-for="field in selectedSpec.fields" :key="field.name">
                                        <td class="px-4 py-2">
                                            <span class="font-mono text-xs font-medium">{{ field.name }}</span>
                                            <p v-if="field.label" class="text-xs text-slate-500">{{ field.label }}</p>
                                        </td>
                                        <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ field.type }}</td>
                                        <td class="px-4 py-2 text-xs">
                                            <span v-if="field.required" class="mr-1 text-rose-500">required</span>
                                            <span v-if="field.ping_field" class="text-indigo-500">ping</span>
                                        </td>
                                        <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ field.example ?? '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </Panel>

                        <Panel title="Sample request for this campaign">
                            <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-emerald-300">{{ JSON.stringify(sampleRequest, null, 2) }}</pre>
                        </Panel>
                    </template>

                    <p v-else class="text-sm text-slate-500">Select a campaign above to view its field schema and sample payload.</p>
                </div>

                <!-- Platform export -->
                <div v-show="activeTab === 'platform'" class="space-y-6">
                    <Panel title="Pull your full platform configuration">
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            Use <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">GET /platform</code> with an API key that has
                            <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">platform.read</code> (or a full administrator key) to export campaigns, buyers, suppliers, routing, webhooks, postbacks, and hosted forms.
                            Ideal when you run your own portal or CRM but want PowerByExcellence to handle lead distribution.
                        </p>
                    </Panel>

                    <Panel v-for="guide in platformGuides" :key="guide.title" :title="guide.title">
                        <p class="text-sm text-slate-600 dark:text-slate-400">{{ guide.body }}</p>
                    </Panel>

                    <div class="grid gap-6 lg:grid-cols-2">
                        <Panel title="Full export">
                            <template #header>
                                <button type="button" class="text-xs font-medium text-indigo-600 hover:underline" @click="copyText(platformExportCurl, 'platform-full')">
                                    {{ copied === 'platform-full' ? 'Copied' : 'Copy' }}
                                </button>
                            </template>
                            <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-cyan-300 whitespace-pre-wrap">{{ platformExportCurl }}</pre>
                        </Panel>
                        <Panel title="Partial export (include filter)">
                            <template #header>
                                <button type="button" class="text-xs font-medium text-indigo-600 hover:underline" @click="copyText(platformPartialCurl, 'platform-partial')">
                                    {{ copied === 'platform-partial' ? 'Copied' : 'Copy' }}
                                </button>
                            </template>
                            <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-cyan-300 whitespace-pre-wrap">{{ platformPartialCurl }}</pre>
                        </Panel>
                    </div>

                    <Panel title="Single campaign">
                        <template #header>
                            <button type="button" class="text-xs font-medium text-indigo-600 hover:underline" @click="copyText(platformCampaignCurl, 'platform-campaign')">
                                {{ copied === 'platform-campaign' ? 'Copied' : 'Copy' }}
                            </button>
                        </template>
                        <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-cyan-300 whitespace-pre-wrap">{{ platformCampaignCurl }}</pre>
                    </Panel>

                    <Panel title="Response shape (abbreviated)">
                        <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-violet-300">{{ JSON.stringify(samplePlatformExport, null, 2) }}</pre>
                    </Panel>

                    <Panel title="include query values">
                        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="bg-slate-50/90 dark:bg-slate-800/60">
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">Value</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">Section</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr v-for="row in [
                                        { value: 'campaigns', section: 'Campaigns, fields, API specs, deliveries, ping-tree configs' },
                                        { value: 'buyers', section: 'Buyers with credit, caps, and delivery routes' },
                                        { value: 'suppliers', section: 'Suppliers, SIDs, and sub-suppliers' },
                                        { value: 'webhooks', section: 'Outbound buyer webhooks (secrets redacted)' },
                                        { value: 'postbacks', section: 'Supplier postback URLs and events' },
                                        { value: 'forms', section: 'Hosted form embed URLs and config' },
                                    ]" :key="row.value">
                                        <td class="px-3 py-2 font-mono text-xs">{{ row.value }}</td>
                                        <td class="px-3 py-2 text-slate-600 dark:text-slate-400">{{ row.section }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </Panel>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
