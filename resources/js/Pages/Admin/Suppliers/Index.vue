<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import ClickableTableRow from '@/Components/UI/ClickableTableRow.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import TenantContextBanner from '@/Components/UI/TenantContextBanner.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    suppliers: Object,
    filters: Object,
    stats: Object,
});

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');

const applyFilters = () => {
    router.get(route('suppliers.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true, replace: true });
};

const suppliersStrip = computed(() => [
    { label: 'Total', value: props.stats?.total ?? 0 },
    { label: 'Active', value: props.stats?.active ?? 0, accent: 'emerald' },
    { label: 'Sources', value: props.stats?.sources ?? 0, accent: 'indigo' },
]);
</script>

<template>
    <Head title="Suppliers" />
    <AuthenticatedLayout>
        <PageHeader title="Suppliers (Affiliates)" description="Publishers who submit leads — SIDs, API keys, and supplier portal access.">
            <template #actions>
                <AppButton :href="route('api-keys.index')" variant="secondary">API Keys</AppButton>
                <AppButton :href="route('suppliers.create')">New Supplier</AppButton>
            </template>
        </PageHeader>

        <TenantContextBanner />

        <CompactStatStrip :items="suppliersStrip" :columns="3" class="mb-6" />

        <Panel :padding="false">
            <template #header>
                <div class="flex flex-wrap items-center gap-3">
                    <TextInput v-model="search" class="max-w-xs" placeholder="Search name, reference, SID…" @keyup.enter="applyFilters" />
                    <select v-model="status" class="form-select text-sm" @change="applyFilters">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </template>
            <DataTable :empty="!suppliers?.data?.length" empty-message="No suppliers yet. Add affiliates to start receiving leads via API.">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Reference</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">SIDs</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Leads</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                </template>
                <ClickableTableRow v-for="s in suppliers.data" :key="s.id" :href="route('suppliers.show', s.id)">
                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ s.name }}</td>
                    <td class="px-6 py-4 font-mono text-xs text-slate-500">{{ s.reference }}</td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ s.sources?.map((x) => x.sid).join(', ') || '—' }}</td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ s.leads_count ?? 0 }}</td>
                    <td class="px-6 py-4"><StatusBadge :status="s.status" /></td>
                    <td class="px-6 py-4 text-right space-x-3" @click.stop>
                        <Link :href="route('api-keys.index')" class="text-sm text-slate-500 hover:text-indigo-600">API</Link>
                        <Link :href="route('suppliers.edit', s.id)" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Edit</Link>
                    </td>
                </ClickableTableRow>
            </DataTable>
            <Pagination :links="suppliers.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
