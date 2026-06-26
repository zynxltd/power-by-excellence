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
import { Head, router, useForm, usePage } from '@inertiajs/vue3';

defineProps({ apiKeys: Array, suppliers: Array });

const page = usePage();

const form = useForm({ name: '', type: 'administrator', supplier_id: '' });
const submit = () => form.post(route('api-keys.store'), { onSuccess: () => form.reset('name', 'supplier_id') });
const destroy = (id) => { if (confirm('Revoke this API key? It will stop working immediately.')) router.delete(route('api-keys.destroy', id)); };

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
</script>

<template>
    <Head title="API Keys" />
    <AuthenticatedLayout>
        <PageHeader
            title="API Keys"
            description="Bearer tokens for lead ingest and integrations. Copy new tokens immediately — they are only shown once."
        >
            <template #actions>
                <AppButton :href="route('api-docs.index')" variant="secondary">API Documentation</AppButton>
            </template>
        </PageHeader>

        <div v-if="page.props.flash?.success" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
            {{ page.props.flash.success }}
        </div>

        <div class="space-y-6">
            <Panel title="Generate key">
                <form @submit.prevent="submit" class="grid gap-4 md:grid-cols-2">
                    <div><InputLabel value="Name" /><TextInput v-model="form.name" class="mt-1 block w-full" placeholder="Supplier ingest — Main affiliate" required /></div>
                    <div>
                        <InputLabel value="Type" />
                        <select v-model="form.type" class="form-select">
                            <option value="administrator">Administrator (full API access)</option>
                            <option value="supplier">Supplier (ingest for one supplier)</option>
                        </select>
                    </div>
                    <div v-if="form.type === 'supplier'" class="md:col-span-2">
                        <InputLabel value="Supplier" />
                        <select v-model="form.supplier_id" class="form-select" required>
                            <option disabled value="">Select supplier…</option>
                            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Supplier keys are scoped to create and read leads for this source only.</p>
                    </div>
                    <div v-else class="md:col-span-2 text-xs text-slate-500">
                        Administrator keys receive full API access. Use supplier keys for affiliate ingest.
                    </div>
                    <div class="md:col-span-2"><PrimaryButton :disabled="form.processing">Generate key</PrimaryButton></div>
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
                        <th class="text-left">Last used</th>
                        <th class="text-right">Actions</th>
                    </template>
                    <tr v-for="k in apiKeys" :key="k.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="font-medium text-slate-900 dark:text-white">{{ k.name }}</td>
                        <td class="capitalize text-slate-600 dark:text-slate-400">{{ k.type }}</td>
                        <td class="text-slate-600 dark:text-slate-400">{{ k.supplier?.name ?? '—' }}</td>
                        <td class="text-xs text-slate-600 dark:text-slate-400">{{ formatPermissions(k.permissions) }}</td>
                        <td class="font-mono text-xs text-slate-500">{{ k.key_prefix }}…</td>
                        <td class="text-slate-500">
                            <FormattedDate v-if="k.last_used_at" :value="k.last_used_at" format="relative" />
                            <span v-else>Never</span>
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
