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

const templateForm = useForm({ vertical_id: props.campaign.vertical_id ?? 'solar' });

const liveSample = computed(() => {
    const body = {
        campaign_reference: props.campaign.reference,
        source: 'api_example',
    };
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
        + `  -d '${json.replace(/'/g, "'\\''")}'`;
});

const requiredCount = computed(() => (f.spec.fields ?? []).filter((field) => field.required).length);
const pingFieldCount = computed(() => (f.spec.fields ?? []).filter((field) => field.ping_field).length);

const addField = () => {
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

const removeField = (idx) => f.spec.fields.splice(idx, 1);

const moveField = (idx, dir) => {
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

const loadTemplate = () => templateForm.post(route('campaigns.api-spec.load-template', props.campaign.id));
const loadPremade = (key) => router.post(route('campaigns.api-spec.load-premade', props.campaign.id), { template_key: key });

const save = () => {
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
            <span v-if="campaignAccount" class="ml-2 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                {{ campaignAccount.name }}
            </span>
        </div>

        <CampaignWorkflowNav
            v-if="campaignWorkflow"
            :campaign="campaignWorkflow.campaign"
            :distribution-config-id="campaignWorkflow.distributionConfigId"
            :tenant-hub="campaignWorkflow.tenantHub"
            current="api-spec"
            class="mb-6"
        />

        <div class="grid gap-6 lg:grid-cols-12">
            <!-- Sticky sidebar -->
            <aside class="space-y-4 lg:col-span-3 lg:sticky lg:top-6 lg:self-start">
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
                        {{ copied === 'curl' ? '✓ Copied cURL' : 'Copy cURL command' }}
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
                            Required scope: <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">leads.create</code>
                        </p>
                        <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs dark:border-slate-700 dark:bg-slate-800/50">
                            <p class="font-semibold text-slate-700 dark:text-slate-300">Example header</p>
                            <code class="mt-1 block font-mono text-slate-600 dark:text-slate-400">Authorization: Bearer pbe_live|your_secret_here</code>
                        </div>
                    </Panel>

                    <Panel title="Request flow">
                        <ol class="list-inside list-decimal space-y-2 text-sm text-slate-600 dark:text-slate-400">
                            <li>POST JSON body with <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">campaign_reference</code> and all required fields</li>
                            <li>Server validates fields against this spec and queues the lead</li>
                            <li>202 Accepted returned with <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">uuid</code> and <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">queue_id</code></li>
                            <li>Distribution runs asynchronously — check <Link :href="route('leads.index', { campaign_id: campaign.id })" class="text-indigo-600 hover:underline">lead pipeline</Link> for status</li>
                        </ol>
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
                                <textarea v-model="f.spec.description" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800" />
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
                                            <button type="button" class="text-xs text-slate-500" @click="moveField(idx, -1)">↑</button>
                                            <button type="button" class="text-xs text-slate-500" @click="moveField(idx, 1)">↓</button>
                                            <button type="button" class="text-xs text-rose-500" @click="removeField(idx)">Remove</button>
                                        </div>
                                    </div>
                                    <div class="grid gap-3 md:grid-cols-3">
                                        <div>
                                            <label class="text-xs text-slate-500">API name</label>
                                            <input v-model="field.name" class="mt-1 w-full rounded-lg border px-2 py-1.5 font-mono text-sm dark:border-slate-700 dark:bg-slate-800" />
                                        </div>
                                        <div>
                                            <label class="text-xs text-slate-500">Label</label>
                                            <input v-model="field.label" class="mt-1 w-full rounded-lg border px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800" />
                                        </div>
                                        <div>
                                            <label class="text-xs text-slate-500">API type</label>
                                            <select v-model="field.type" class="mt-1 w-full rounded-lg border px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
                                                <option v-for="t in fieldTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs text-slate-500">Form control</label>
                                            <select v-model="field.form_type" class="mt-1 w-full rounded-lg border px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
                                                <option v-for="t in formTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs text-slate-500">Example value</label>
                                            <input v-model="field.example" class="mt-1 w-full rounded-lg border px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800" />
                                        </div>
                                        <div class="flex flex-wrap items-end gap-4">
                                            <label class="flex items-center gap-2 text-xs"><input v-model="field.required" type="checkbox" /> Required</label>
                                            <label class="flex items-center gap-2 text-xs"><input v-model="field.ping_field" type="checkbox" /> Ping field</label>
                                        </div>
                                        <div class="md:col-span-3">
                                            <label class="text-xs text-slate-500">Description</label>
                                            <input v-model="field.description" class="mt-1 w-full rounded-lg border px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800" />
                                        </div>
                                        <div v-if="field.type === 'enum'" class="md:col-span-3">
                                            <label class="text-xs text-slate-500">Enum options (one per line)</label>
                                            <textarea
                                                :value="(field.enum || []).join('\n')"
                                                rows="2"
                                                class="mt-1 w-full rounded-lg border px-2 py-1.5 font-mono text-sm dark:border-slate-700 dark:bg-slate-800"
                                                @input="field.enum = $event.target.value.split('\n').filter(Boolean)"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="border-t border-slate-100 p-4 dark:border-slate-800">
                            <AppButton type="button" variant="secondary" @click="addField">+ Add field</AppButton>
                        </div>
                    </Panel>

                    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                        <strong>Amending the API spec affects live integrations.</strong>
                        Changing field names, types, or required flags may break supplier POST requests and hosted forms. You will be asked to confirm before saving.
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="f.sync_fields" type="checkbox" class="rounded" />
                            Sync fields to campaign on save
                        </label>
                        <PrimaryButton :disabled="f.processing" @click="save">Save API spec</PrimaryButton>
                    </div>
                </div>

                <!-- Live preview -->
                <div v-show="activeTab === 'preview'" class="grid gap-6 lg:grid-cols-2">
                    <Panel title="Request body (live)">
                        <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-emerald-300">{{ JSON.stringify(liveSample, null, 2) }}</pre>
                        <button type="button" class="mt-3 text-sm text-indigo-600" @click="copyText(JSON.stringify(liveSample, null, 2), 'json')">Copy JSON</button>
                    </Panel>
                    <Panel title="cURL (live)">
                        <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-cyan-300 whitespace-pre-wrap">{{ liveCurl }}</pre>
                        <button type="button" class="mt-3 text-sm text-indigo-600" @click="copyText(liveCurl, 'curl')">Copy cURL</button>
                    </Panel>
                    <Panel title="Success response (202)" class="lg:col-span-2">
                        <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-violet-300">{{ JSON.stringify(sampleResponse, null, 2) }}</pre>
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
                            <select v-model="templateForm.vertical_id" class="rounded-xl border px-3 py-2 dark:border-slate-700 dark:bg-slate-800">
                                <option v-for="v in verticals" :key="v.id" :value="v.id">{{ v.label }}</option>
                            </select>
                            <AppButton type="button" :disabled="templateForm.processing" @click="loadTemplate">Load template</AppButton>
                        </div>
                    </Panel>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
