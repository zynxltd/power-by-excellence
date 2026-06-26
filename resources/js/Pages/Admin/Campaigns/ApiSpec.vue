<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import CampaignWorkflowNav from '@/Components/UI/CampaignWorkflowNav.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    campaign: Object,
    campaignAccount: Object,
    activeDistributionConfigId: { type: [Number, String], default: null },
    spec: Object,
    sampleRequest: Object,
    sampleResponse: Object,
    sampleStatusResponse: Object,
    curl: String,
    fieldTypes: Array,
    formTypes: Array,
    verticals: Array,
    apiBaseUrl: String,
    premadeTemplates: { type: Array, default: () => [] },
    tenantHost: { type: String, default: '' },
    tenantHub: { type: Object, default: null },
    campaignWorkflow: { type: Object, default: null },
});

const activeTab = ref('overview');
const expandedField = ref(0);
const copied = ref('');
const testMode = ref(true);

const exampleLeadId = computed(() => props.sampleResponse?.lead_id ?? 'your-lead-uuid');
const exampleQueueId = computed(() => props.sampleResponse?.queue_id ?? 'q_example123abc');

const tabs = [
    { key: 'overview', label: 'Overview', icon: '📋' },
    { key: 'auth', label: 'Authentication', icon: '🔐' },
    { key: 'fields', label: 'Field schema', icon: '⚙️' },
    { key: 'preview', label: 'Live preview', icon: '▶️' },
    { key: 'templates', label: 'Templates', icon: '📦' },
];

const campaignNav = computed(() => [
    { label: 'Campaign overview', href: route('campaigns.show', props.campaign.id) },
    { label: 'Edit campaign', href: route('campaigns.edit', props.campaign.id) },
    { label: 'View leads', href: route('leads.index', { campaign_id: props.campaign.id }) },
    { label: 'Ping tree', href: route('distribution.create') + '?campaign_id=' + props.campaign.id },
    { label: 'Form builder', href: route('forms.index') },
]);

const f = useForm({
    spec: JSON.parse(JSON.stringify(props.spec)),
    sync_fields: true,
});

const lockForm = useForm({ locked: false });

const isLocked = computed(() => !!props.spec?.locked);

const templateForm = useForm({ vertical_id: props.campaign.vertical_id ?? 'solar' });

const liveSample = computed(() => {
    const body = {
        campaign_reference: props.campaign.reference,
        source: 'api_example',
    };
    if (testMode.value) {
        body.test = true;
    }
    for (const field of f.spec.fields ?? []) {
        body[field.name] = field.example ?? '';
    }
    return body;
});

const liveCurl = computed(() => {
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

const requiredCount = computed(() => (f.spec.fields ?? []).filter((field) => field.required).length);
const pingFieldCount = computed(() => (f.spec.fields ?? []).filter((field) => field.ping_field).length);

const addField = () => {
    if (isLocked.value) return;
    f.spec.fields.push({
        name: `custom_field_${Date.now()}`,
        label: 'New field',
        type: 'string',
        required: false,
        ping_field: false,
        description: '',
        example: 'example',
        enum: [],
        form_type: 'text',
    });
    expandedField.value = f.spec.fields.length - 1;
};

const removeField = (idx) => {
    if (isLocked.value) return;
    f.spec.fields.splice(idx, 1);
};

const moveField = (idx, dir) => {
    if (isLocked.value) return;
    const next = idx + dir;
    if (next < 0 || next >= f.spec.fields.length) return;
    const fields = [...f.spec.fields];
    [fields[idx], fields[next]] = [fields[next], fields[idx]];
    f.spec.fields = fields;
    expandedField.value = next;
};

const copyText = async (text, key) => {
    await navigator.clipboard.writeText(text);
    copied.value = key;
    setTimeout(() => { copied.value = ''; }, 2000);
};

const loadTemplate = () => {
    if (isLocked.value) return;
    templateForm.post(route('campaigns.api-spec.load-template', props.campaign.id));
};
const loadPremade = (key) => {
    if (isLocked.value) return;
    router.post(route('campaigns.api-spec.load-premade', props.campaign.id), { template_key: key });
};

const toggleLock = () => {
    const nextLocked = !isLocked.value;

    if (! nextLocked) {
        const unlock = [
            'Unlock the API spec for editing?',
            '',
            'Changing field names, types, or required flags may break supplier POST requests and hosted forms.',
            'You will be asked to confirm before saving.',
        ].join('\n');

        if (! window.confirm(unlock)) {
            return;
        }
    }

    lockForm.locked = nextLocked;
    lockForm.post(route('campaigns.api-spec.lock', props.campaign.id), {
        preserveScroll: true,
    });
};

const save = () => {
    if (isLocked.value) return;

    const fieldCount = f.spec.fields?.length ?? 0;
    const warning = [
        'You are about to update the live API specification.',
        '',
        '• Existing integrations may break if field names, types, or required flags change.',
        '• In-flight leads using the old schema may fail validation.',
        `• ${fieldCount} field(s) will be saved${f.sync_fields ? ' and synced to campaign fields' : ''}.`,
        '',
        'Continue?',
    ].join('\n');

    if (! window.confirm(warning)) {
        return;
    }

    f.put(route('campaigns.api-spec.update', props.campaign.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`API Spec — ${campaign.name}`" />
    <AuthenticatedLayout>
        <PageHeader :title="`API Spec`" :description="`${campaign.name} · ${campaign.reference}`">
            <template #actions>
                <button
                    type="button"
                    :class="[
                        'inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition',
                        isLocked
                            ? 'border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200'
                            : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200',
                    ]"
                    :disabled="lockForm.processing"
                    @click="toggleLock"
                >
                    <span>{{ isLocked ? '🔒' : '🔓' }}</span>
                    {{ isLocked ? 'Unlock spec' : 'Lock spec' }}
                </button>
                <AppButton :href="route('api-docs.index', { campaign_id: campaign.id })" variant="secondary">API Docs</AppButton>
                <AppButton :href="route('campaigns.show', campaign.id)" variant="secondary">← Campaign</AppButton>
                <AppButton :href="route('api-keys.index')" variant="secondary">API Keys</AppButton>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-wrap items-center gap-2 text-sm text-slate-500">
            <Link :href="route('campaigns.index')" class="hover:text-indigo-600">Campaigns</Link>
            <span>/</span>
            <Link :href="route('campaigns.show', campaign.id)" class="hover:text-indigo-600">{{ campaign.name }}</Link>
            <span>/</span>
            <span class="font-medium text-slate-800 dark:text-slate-200">API Spec</span>
            <span v-if="isLocked" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Locked</span>
            <span v-if="campaignAccount" class="ml-2 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                {{ campaignAccount.name }}
            </span>
        </div>

        <CampaignWorkflowNav
            v-if="campaignWorkflow"
            :campaign="campaignWorkflow.campaign"
            :distribution-config-id="campaignWorkflow.distributionConfigId"
            current="api-spec"
            class="mb-6"
        />

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
                {{ tab.icon }} {{ tab.label }}
            </button>
        </div>

        <div class="grid gap-6 lg:grid-cols-12">
            <!-- Sticky sidebar -->
            <aside class="hidden space-y-4 lg:col-span-3 lg:block lg:sticky lg:top-6 lg:self-start">
                <Panel title="Navigate">
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
                            <span>{{ tab.icon }}</span>
                            {{ tab.label }}
                        </button>
                    </nav>
                </Panel>

                <Panel title="Campaign">
                    <ul class="space-y-1">
                        <li v-for="link in campaignNav" :key="link.href">
                            <Link :href="link.href" class="block rounded-lg px-2 py-1.5 text-sm text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 dark:text-slate-300 dark:hover:bg-indigo-950/30">
                                {{ link.label }}
                            </Link>
                        </li>
                    </ul>
                </Panel>

                <Panel title="Quick copy">
                    <button type="button" class="mb-2 w-full rounded-lg border px-3 py-2 text-left text-xs hover:bg-slate-50 dark:hover:bg-slate-800" @click="copyText(liveCurl, 'curl')">
                        {{ copied === 'curl' ? '✓ Copied ingest cURL' : 'Copy ingest cURL' }}
                    </button>
                    <button type="button" class="mb-2 w-full rounded-lg border px-3 py-2 text-left text-xs hover:bg-slate-50 dark:hover:bg-slate-800" @click="copyText(statusByLeadCurl, 'status-lead')">
                        {{ copied === 'status-lead' ? '✓ Copied status cURL' : 'Copy status cURL (by lead_id)' }}
                    </button>
                    <button type="button" class="mb-2 w-full rounded-lg border px-3 py-2 text-left text-xs hover:bg-slate-50 dark:hover:bg-slate-800" @click="copyText(statusByQueueCurl, 'status-queue')">
                        {{ copied === 'status-queue' ? '✓ Copied status cURL' : 'Copy status cURL (by queue_id)' }}
                    </button>
                    <button type="button" class="w-full rounded-lg border px-3 py-2 text-left text-xs hover:bg-slate-50 dark:hover:bg-slate-800" @click="copyText(JSON.stringify(liveSample, null, 2), 'json')">
                        {{ copied === 'json' ? '✓ Copied JSON' : 'Copy sample JSON body' }}
                    </button>
                </Panel>
            </aside>

            <!-- Main content -->
            <div class="lg:col-span-9">
                <!-- Overview -->
                <div v-show="activeTab === 'overview'" class="space-y-6">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                            <p class="text-xs font-semibold uppercase text-slate-500">Endpoint</p>
                            <p class="mt-1 font-mono text-sm text-indigo-600">POST {{ apiBaseUrl }}/leads</p>
                            <p v-if="tenantHost" class="mt-1 text-xs text-slate-500">Tenant host: {{ tenantHost }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                            <p class="text-xs font-semibold uppercase text-slate-500">Fields</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ f.spec.fields?.length ?? 0 }}</p>
                            <p class="text-xs text-slate-500">{{ requiredCount }} required · {{ pingFieldCount }} ping</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                            <p class="text-xs font-semibold uppercase text-slate-500">Market</p>
                            <p class="mt-1 font-semibold text-slate-900 dark:text-white">{{ campaign.country }} / {{ campaign.currency }}</p>
                        </div>
                    </div>

                    <Panel title="Field summary">
                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            <div v-for="(field, idx) in f.spec.fields" :key="idx" class="flex flex-wrap items-center justify-between gap-2 py-2">
                                <div>
                                    <span class="font-mono text-sm font-medium text-slate-900 dark:text-white">{{ field.name }}</span>
                                    <span class="ml-2 text-xs text-slate-500">{{ field.type }}</span>
                                    <span v-if="field.required" class="ml-1 text-xs font-semibold text-rose-500">required</span>
                                    <span v-if="field.ping_field" class="ml-1 text-xs font-semibold text-indigo-500">ping</span>
                                </div>
                                <button type="button" class="text-xs text-indigo-600 hover:underline" @click="activeTab = 'fields'; expandedField = idx">Edit →</button>
                            </div>
                        </div>
                    </Panel>
                </div>

                <!-- Authentication & flow -->
                <div v-show="activeTab === 'auth'" class="space-y-6">
                    <Panel title="Authentication">
                        <p class="font-mono text-sm text-slate-700 dark:text-slate-300">Authorization: Bearer {prefix}|{secret}</p>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                            Create API keys under <Link :href="route('api-keys.index')" class="text-indigo-600 hover:underline">Tools → API Keys</Link>.
                            Required scopes: <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">leads.create</code> to ingest,
                            <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">leads.read</code> to poll status.
                        </p>
                        <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs dark:border-slate-700 dark:bg-slate-800/50">
                            <p class="font-semibold text-slate-700 dark:text-slate-300">Example header</p>
                            <code class="mt-1 block font-mono text-slate-600 dark:text-slate-400">Authorization: Bearer pbe_live|your_secret_here</code>
                        </div>
                    </Panel>

                    <Panel title="Request flow">
                        <ol class="list-inside list-decimal space-y-2 text-sm text-slate-600 dark:text-slate-400">
                            <li>POST JSON with <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">campaign_reference</code> and required fields. Use <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">"test": true</code> to validate without pinging buyers.</li>
                            <li>Server validates fields against this spec and queues the lead</li>
                            <li>202 Accepted with <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">lead_id</code> and <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">queue_id</code></li>
                            <li>Poll <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">GET /leads/{lead_id}</code> or <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">GET /leads/queue/{queue_id}</code> until status is terminal (<code class="rounded bg-slate-100 px-1 dark:bg-slate-800">sold</code>, <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">accepted</code>, <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">rejected</code>, etc.)</li>
                        </ol>
                    </Panel>

                    <Panel title="Test mode">
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            Pass <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">"test": true</code> in the POST body to run validation and dedupe checks
                            <strong>without</strong> ping-post auctions, buyer deliveries, postbacks, or credit debits.
                            Test leads finish with status <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">accepted</code> when validation passes.
                        </p>
                        <p class="mt-2 text-sm text-amber-700 dark:text-amber-300">
                            Omit <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">test</code> (or set <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">false</code>) for production traffic.
                        </p>
                    </Panel>

                    <Panel title="Response codes">
                        <ul class="space-y-2 text-sm text-slate-600 dark:text-slate-400">
                            <li><code class="rounded bg-slate-100 px-1 dark:bg-slate-800">202</code> — Lead accepted and queued</li>
                            <li><code class="rounded bg-slate-100 px-1 dark:bg-slate-800">401</code> — Invalid or missing API key</li>
                            <li><code class="rounded bg-slate-100 px-1 dark:bg-slate-800">422</code> — Validation failed (field errors in body)</li>
                            <li><code class="rounded bg-slate-100 px-1 dark:bg-slate-800">429</code> — Rate limit exceeded</li>
                        </ul>
                    </Panel>
                </div>

                <!-- Fields editor -->
                <div v-show="activeTab === 'fields'" class="space-y-6">
                    <Panel title="Endpoint">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500">Method</label>
                                <p class="mt-1 font-mono text-sm">POST</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="text-xs font-semibold uppercase text-slate-500">Full URL</label>
                                <p class="mt-1 break-all font-mono text-sm">{{ apiBaseUrl }}/leads</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="text-xs font-semibold uppercase text-slate-500">Description</label>
                                <textarea v-model="f.spec.description" rows="2" :disabled="isLocked" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:disabled:bg-slate-900/60" />
                            </div>
                        </div>
                    </Panel>

                    <Panel title="Fields" :padding="false">
                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            <div v-for="(field, idx) in f.spec.fields" :key="idx">
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                    @click="expandedField = expandedField === idx ? -1 : idx"
                                >
                                    <div>
                                        <span class="font-mono text-sm font-medium">{{ field.name }}</span>
                                        <span class="ml-2 text-xs text-slate-500">{{ field.label }}</span>
                                        <span v-if="field.required" class="ml-1 rounded bg-rose-100 px-1 text-xs text-rose-600">req</span>
                                    </div>
                                    <span class="text-slate-400">{{ expandedField === idx ? '▼' : '▶' }}</span>
                                </button>
                                <div v-show="expandedField === idx" class="border-t border-slate-100 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/50">
                                    <div class="mb-3 flex items-center justify-between">
                                        <span class="text-xs font-bold uppercase text-violet-600">Field {{ idx + 1 }}</span>
                                        <div class="flex gap-2">
                                            <button type="button" class="text-xs text-slate-500 disabled:opacity-40" :disabled="isLocked" @click="moveField(idx, -1)">↑</button>
                                            <button type="button" class="text-xs text-slate-500 disabled:opacity-40" :disabled="isLocked" @click="moveField(idx, 1)">↓</button>
                                            <button type="button" class="text-xs text-rose-500 disabled:opacity-40" :disabled="isLocked" @click="removeField(idx)">Remove</button>
                                        </div>
                                    </div>
                                    <div class="grid gap-3 md:grid-cols-3">
                                        <div>
                                            <label class="text-xs text-slate-500">API name</label>
                                            <input v-model="field.name" :disabled="isLocked" class="mt-1 w-full rounded-lg border px-2 py-1.5 font-mono text-sm disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:disabled:bg-slate-900/60" />
                                        </div>
                                        <div>
                                            <label class="text-xs text-slate-500">Label</label>
                                            <input v-model="field.label" :disabled="isLocked" class="mt-1 w-full rounded-lg border px-2 py-1.5 text-sm disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:disabled:bg-slate-900/60" />
                                        </div>
                                        <div>
                                            <label class="text-xs text-slate-500">API type</label>
                                            <select v-model="field.type" :disabled="isLocked" class="mt-1 w-full rounded-lg border px-2 py-1.5 text-sm disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:disabled:bg-slate-900/60">
                                                <option v-for="t in fieldTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs text-slate-500">Form control</label>
                                            <select v-model="field.form_type" :disabled="isLocked" class="mt-1 w-full rounded-lg border px-2 py-1.5 text-sm disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:disabled:bg-slate-900/60">
                                                <option v-for="t in formTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs text-slate-500">Example value</label>
                                            <input v-model="field.example" :disabled="isLocked" class="mt-1 w-full rounded-lg border px-2 py-1.5 text-sm disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:disabled:bg-slate-900/60" />
                                        </div>
                                        <div class="flex flex-wrap items-end gap-4">
                                            <label class="flex items-center gap-2 text-xs"><input v-model="field.required" type="checkbox" :disabled="isLocked" /> Required</label>
                                            <label class="flex items-center gap-2 text-xs"><input v-model="field.ping_field" type="checkbox" :disabled="isLocked" /> Ping field</label>
                                        </div>
                                        <div class="md:col-span-3">
                                            <label class="text-xs text-slate-500">Description</label>
                                            <input v-model="field.description" :disabled="isLocked" class="mt-1 w-full rounded-lg border px-2 py-1.5 text-sm disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:disabled:bg-slate-900/60" />
                                        </div>
                                        <div v-if="field.type === 'enum'" class="md:col-span-3">
                                            <label class="text-xs text-slate-500">Enum options (one per line)</label>
                                            <textarea
                                                :value="(field.enum || []).join('\n')"
                                                rows="2"
                                                :disabled="isLocked"
                                                class="mt-1 w-full rounded-lg border px-2 py-1.5 font-mono text-sm disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:disabled:bg-slate-900/60"
                                                @input="field.enum = $event.target.value.split('\n').filter(Boolean)"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="border-t border-slate-100 p-4 dark:border-slate-800">
                            <AppButton type="button" variant="secondary" :disabled="isLocked" @click="addField">+ Add field</AppButton>
                        </div>
                    </Panel>

                    <div
                        class="mb-4 rounded-xl border px-4 py-3 text-sm"
                        :class="isLocked
                            ? 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-200'
                            : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200'"
                    >
                        <template v-if="isLocked">
                            <strong>API spec is locked.</strong>
                            Field schema, templates, and save are disabled to protect live supplier integrations and hosted forms. Click <strong>Unlock spec</strong> to make changes.
                        </template>
                        <template v-else>
                            <strong>Amending the API spec affects live integrations.</strong>
                            Changing field names, types, or required flags may break supplier POST requests and hosted forms. Lock the spec when finished, or use <strong>Lock spec</strong> before go-live. You will be asked to confirm before saving.
                        </template>
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="f.sync_fields" type="checkbox" class="rounded" :disabled="isLocked" />
                            Sync fields to campaign on save
                        </label>
                        <PrimaryButton :disabled="f.processing || isLocked" @click="save">Save API spec</PrimaryButton>
                    </div>
                </div>

                <!-- Live preview -->
                <div v-show="activeTab === 'preview'" class="space-y-6">
                    <div
                        class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">Test mode</p>
                            <p class="mt-0.5 text-sm text-slate-500">When enabled, sample requests include <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">"test": true</code> — no live buyer deliveries.</p>
                        </div>
                        <label class="flex cursor-pointer items-center gap-3">
                            <span class="text-sm font-medium" :class="testMode ? 'text-emerald-600' : 'text-slate-500'">
                                {{ testMode ? 'Test (safe)' : 'Live (delivers)' }}
                            </span>
                            <button
                                type="button"
                                :class="[
                                    'relative h-7 w-12 rounded-full transition',
                                    testMode ? 'bg-emerald-500' : 'bg-rose-500',
                                ]"
                                @click="testMode = !testMode"
                            >
                                <span
                                    :class="[
                                        'absolute top-0.5 h-6 w-6 rounded-full bg-white shadow transition',
                                        testMode ? 'left-5' : 'left-0.5',
                                    ]"
                                />
                            </button>
                        </label>
                    </div>

                    <div class="grid gap-6 lg:grid-cols-2">
                        <Panel :title="testMode ? 'Request body (test)' : 'Request body (live)'">
                            <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-emerald-300">{{ JSON.stringify(liveSample, null, 2) }}</pre>
                            <button type="button" class="mt-3 text-sm text-indigo-600" @click="copyText(JSON.stringify(liveSample, null, 2), 'json')">Copy JSON</button>
                        </Panel>
                        <Panel :title="testMode ? 'cURL ingest (test)' : 'cURL ingest (live)'">
                            <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-cyan-300 whitespace-pre-wrap">{{ liveCurl }}</pre>
                            <button type="button" class="mt-3 text-sm text-indigo-600" @click="copyText(liveCurl, 'curl')">Copy ingest cURL</button>
                        </Panel>
                    </div>

                    <Panel title="Success response (202 Accepted)">
                        <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-violet-300">{{ JSON.stringify(sampleResponse, null, 2) }}</pre>
                        <p class="mt-3 text-sm text-slate-500">Save <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">lead_id</code> and <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">queue_id</code> from the response — use either to poll status below.</p>
                    </Panel>

                    <div class="grid gap-6 lg:grid-cols-2">
                        <Panel title="Check status — by lead_id">
                            <p class="mb-3 text-sm text-slate-500">Requires <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">leads.read</code>. Poll every 1–2s until status is no longer in-flight.</p>
                            <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-cyan-300 whitespace-pre-wrap">{{ statusByLeadCurl }}</pre>
                            <button type="button" class="mt-3 text-sm text-indigo-600" @click="copyText(statusByLeadCurl, 'status-lead')">Copy status cURL</button>
                        </Panel>
                        <Panel title="Check status — by queue_id">
                            <p class="mb-3 text-sm text-slate-500">Same payload as lead_id lookup — use whichever ID you stored from the 202 response.</p>
                            <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-cyan-300 whitespace-pre-wrap">{{ statusByQueueCurl }}</pre>
                            <button type="button" class="mt-3 text-sm text-indigo-600" @click="copyText(statusByQueueCurl, 'status-queue')">Copy status cURL</button>
                        </Panel>
                    </div>

                    <Panel title="Status response example (200 OK)">
                        <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-violet-300">{{ JSON.stringify(sampleStatusResponse, null, 2) }}</pre>
                        <p class="mt-3 text-sm text-slate-500">
                            Terminal statuses: <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">sold</code>,
                            <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">unsold</code>,
                            <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">accepted</code> (test mode),
                            <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">rejected</code>,
                            <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">duplicate</code>.
                            In-flight: <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">pending</code>,
                            <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">validating</code>,
                            <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">distributing</code>.
                        </p>
                    </Panel>
                </div>

                <!-- Templates -->
                <div v-show="activeTab === 'templates'" class="space-y-6">
                    <Panel title="Premade API templates">
                        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">One-click field schemas for common verticals. Customise after loading.</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div
                                v-for="tpl in premadeTemplates"
                                :key="tpl.key"
                                class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <p class="font-semibold text-slate-900 dark:text-white">{{ tpl.name }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ tpl.description }}</p>
                                <p class="mt-2 text-xs text-slate-400">{{ tpl.fields?.length ?? 0 }} fields</p>
                                <AppButton
                                    type="button"
                                    variant="secondary"
                                    class="mt-3"
                                    :disabled="isLocked"
                                    @click="loadPremade(tpl.key)"
                                >
                                    Use template
                                </AppButton>
                            </div>
                        </div>
                    </Panel>
                    <Panel title="Load vertical template">
                        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">Start from a pre-built field schema for your vertical, then customise in the Field schema tab.</p>
                        <div class="flex flex-wrap gap-3">
                            <select v-model="templateForm.vertical_id" :disabled="isLocked" class="rounded-xl border px-3 py-2 disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:disabled:bg-slate-900/60">
                                <option v-for="v in verticals" :key="v.id" :value="v.id">{{ v.label }}</option>
                            </select>
                            <AppButton type="button" :disabled="templateForm.processing || isLocked" @click="loadTemplate">Load template</AppButton>
                        </div>
                    </Panel>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
