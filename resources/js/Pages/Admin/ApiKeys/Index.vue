<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    apiKeys: Array,
    suppliers: Array,
    apiBaseUrl: String,
    campaigns: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ total: 0, administrator: 0, supplier: 0 }) },
});

const page = usePage();
const copied = ref('');
const tokenDismissed = ref(false);

const newToken = computed(() => (tokenDismissed.value ? null : page.props.flash?.api_token));

const form = useForm({ name: '', type: 'administrator', supplier_id: '' });
const submit = () => form.post(route('api-keys.store'), {
    onSuccess: () => {
        form.reset('name', 'supplier_id');
        tokenDismissed.value = false;
    },
});
const destroy = (id) => {
    if (confirm('Revoke this API key? It will stop working immediately.')) {
        router.delete(route('api-keys.destroy', id));
    }
};

const permissionLabel = (permission) => ({
    'leads.create': 'Create leads',
    'leads.read': 'Read leads',
    'reports.read': 'Reports',
    'quarantine.manage': 'Quarantine',
    'buyers.manage': 'Buyers',
    '*': 'Full access',
}[permission] ?? permission);

const formatPermissions = (permissions) => {
    const list = permissions ?? [];
    if (list.includes('*')) {
        return 'Full access';
    }

    return list.map(permissionLabel).join(', ');
};

const typeBadgeClass = (type) => ({
    administrator: 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
    supplier: 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
}[type] ?? 'bg-slate-100 text-slate-600');

const exampleCampaign = computed(() => props.campaigns[0]?.reference ?? 'your-campaign-ref');

const ingestCurl = computed(() => {
    const body = JSON.stringify({
        campaign_reference: exampleCampaign.value,
        source: 'api_example',
        test: true,
        firstname: 'Jane',
        email: 'jane@example.com',
    }, null, 2);

    return `curl -X POST '${props.apiBaseUrl}/leads' \\\n`
        + `  -H 'Authorization: Bearer your_prefix|your_secret' \\\n`
        + `  -H 'Content-Type: application/json' \\\n`
        + `  -d '${body.replace(/'/g, "'\\''")}'`;
});

const copyText = async (text, key) => {
    await navigator.clipboard.writeText(text);
    copied.value = key;
    setTimeout(() => { copied.value = ''; }, 2000);
};
</script>

<template>
    <Head title="API Keys" />
    <AuthenticatedLayout>
        <PageHeader
            title="API Keys"
            description="Bearer tokens for lead ingest and integrations. Keys are tenant-scoped — use your platform hostname, not the central admin URL."
        >
            <template #actions>
                <AppButton :href="route('api-docs.index')" variant="secondary">REST API Docs</AppButton>
            </template>
        </PageHeader>

        <div v-if="newToken" class="mb-6 rounded-xl border-2 border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950/30">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-amber-900 dark:text-amber-100">New API key — copy now</p>
                    <p class="mt-1 text-sm text-amber-800/80 dark:text-amber-200/80">This token is shown once. Store it in your secrets manager before leaving this page.</p>
                    <code class="mt-3 block break-all rounded-lg bg-white/80 px-3 py-2 font-mono text-sm text-slate-900 dark:bg-slate-900 dark:text-emerald-300">{{ newToken }}</code>
                </div>
                <div class="flex shrink-0 gap-2">
                    <AppButton type="button" @click="copyText(newToken, 'token')">
                        {{ copied === 'token' ? 'Copied' : 'Copy token' }}
                    </AppButton>
                    <AppButton type="button" variant="secondary" @click="tokenDismissed = true">Dismiss</AppButton>
                </div>
            </div>
        </div>

        <div v-if="page.props.flash?.success && !newToken" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
            {{ page.props.flash.success }}
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Active keys</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ stats.total }}</p>
                <p class="text-xs text-slate-500">{{ stats.administrator }} admin · {{ stats.supplier }} supplier</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Base URL</p>
                <p class="mt-1 break-all font-mono text-sm text-indigo-600 dark:text-indigo-400">{{ apiBaseUrl }}</p>
                <button type="button" class="mt-2 text-xs font-medium text-indigo-600 hover:underline" @click="copyText(apiBaseUrl, 'base')">
                    {{ copied === 'base' ? 'Copied' : 'Copy base URL' }}
                </button>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Auth header</p>
                <p class="mt-1 font-mono text-xs text-slate-700 dark:text-slate-300">Authorization: Bearer prefix|secret</p>
                <p class="mt-1 text-xs text-slate-500">Or <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">X-API-Key</code> with the same value</p>
            </div>
        </div>

        <div class="space-y-6">
            <Panel title="Quick start">
                <ol class="list-inside list-decimal space-y-2 text-sm text-slate-600 dark:text-slate-400">
                    <li>Generate a <strong class="text-slate-800 dark:text-slate-200">supplier key</strong> for affiliate ingest, or an <strong class="text-slate-800 dark:text-slate-200">administrator key</strong> for full API access.</li>
                    <li>POST to <code class="rounded bg-slate-100 px-1 font-mono text-xs dark:bg-slate-800">{{ apiBaseUrl }}/leads</code> with <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">campaign_reference</code> and campaign fields.</li>
                    <li>Poll <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">GET /leads/{lead_id}</code> until status is terminal. See <Link :href="route('api-docs.index')" class="text-indigo-600 hover:underline">REST API Docs</Link> for full reference.</li>
                </ol>
                <div class="mt-4 flex flex-wrap gap-2">
                    <AppButton type="button" variant="secondary" @click="copyText(ingestCurl, 'curl')">
                        {{ copied === 'curl' ? 'Copied cURL' : 'Copy test ingest cURL' }}
                    </AppButton>
                    <AppButton
                        v-if="campaigns.length"
                        :href="route('api-docs.index', { campaign_id: campaigns[0].id })"
                        variant="secondary"
                    >
                        View {{ campaigns[0].name }} schema
                    </AppButton>
                </div>
            </Panel>

            <Panel title="Generate key">
                <form @submit.prevent="submit" class="grid gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel value="Name" />
                        <TextInput v-model="form.name" class="mt-1 block w-full" placeholder="Supplier ingest — Main affiliate" required />
                    </div>
                    <div>
                        <InputLabel value="Type" />
                        <select v-model="form.type" class="form-select mt-1 w-full">
                            <option value="administrator">Administrator — full API access</option>
                            <option value="supplier">Supplier — ingest for one affiliate</option>
                        </select>
                    </div>
                    <div v-if="form.type === 'supplier'" class="md:col-span-2">
                        <InputLabel value="Supplier" />
                        <select v-model="form.supplier_id" class="form-select mt-1 w-full" required>
                            <option disabled value="">Select supplier…</option>
                            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }} ({{ s.reference }})</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Scoped to <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">leads.create</code> and <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">leads.read</code> for this supplier only.</p>
                    </div>
                    <div v-else class="md:col-span-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">
                        Administrator keys receive <strong>full access</strong> (all endpoints). Use supplier keys for affiliate traffic — they cannot access reports, quarantine, or buyer management.
                    </div>
                    <div class="md:col-span-2">
                        <PrimaryButton :disabled="form.processing">Generate key</PrimaryButton>
                    </div>
                </form>
            </Panel>

            <Panel title="Active keys" :padding="false">
                <DataTable :empty="!apiKeys?.length" empty-message="No API keys yet. Generate one above for ingest or integrations.">
                    <template #head>
                        <th class="text-left">Name</th>
                        <th class="text-left">Type</th>
                        <th class="text-left">Supplier</th>
                        <th class="text-left">Access</th>
                        <th class="text-left">Prefix</th>
                        <th class="text-left">Created</th>
                        <th class="text-left">Last used</th>
                        <th class="text-right">Actions</th>
                    </template>
                    <tr v-for="k in apiKeys" :key="k.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="font-medium text-slate-900 dark:text-white">{{ k.name }}</td>
                        <td>
                            <span :class="typeBadgeClass(k.type)" class="rounded-full px-2 py-0.5 text-xs font-medium capitalize">{{ k.type }}</span>
                        </td>
                        <td class="text-slate-600 dark:text-slate-400">{{ k.supplier?.name ?? '—' }}</td>
                        <td class="max-w-[10rem] truncate text-xs text-slate-600 dark:text-slate-400" :title="formatPermissions(k.permissions)">
                            {{ formatPermissions(k.permissions) }}
                        </td>
                        <td class="font-mono text-xs text-slate-500">{{ k.key_prefix }}…</td>
                        <td class="text-xs text-slate-500">
                            <FormattedDate v-if="k.created_at" :value="k.created_at" format="date" />
                        </td>
                        <td class="text-xs text-slate-500">
                            <FormattedDate v-if="k.last_used_at" :value="k.last_used_at" format="relative" />
                            <span v-else class="text-slate-400">Never</span>
                        </td>
                        <td class="text-right">
                            <AppButton variant="ghost" @click="destroy(k.id)">Revoke</AppButton>
                        </td>
                    </tr>
                </DataTable>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
