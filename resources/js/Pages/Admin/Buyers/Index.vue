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
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    buyers: Object,
    filters: Object,
    stats: Object,
    currency: { type: String, default: null },
});

const { formatMoney } = useMoneyFormat(props.currency);

const formatBuyerCredit = (buyer) => formatMoney(buyer.credit_balance, { currency: buyer.resolved_currency });

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');

const applyFilters = () => {
    router.get(route('buyers.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Buyers" />
    <AuthenticatedLayout>
        <PageHeader title="Buyers" description="Lead buyers — credit, deliveries, caps, and buyer portal access.">
            <template #actions>
                <AppButton :href="route('deliveries.index')" variant="secondary">Deliveries</AppButton>
                <AppButton :href="route('buyers.create')">New Buyer</AppButton>
            </template>
        </PageHeader>

        <TenantContextBanner />

        <div class="mb-6 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Total buyers</p>
                <p class="mt-1 text-2xl font-bold">{{ stats?.total ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Active</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600">{{ stats?.active ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Total credit</p>
                <p class="mt-1 text-2xl font-bold">{{ formatMoney(stats?.total_credit ?? 0, { decimals: 0 }) }}</p>
            </div>
        </div>

        <Panel :padding="false">
            <template #header>
                <div class="flex flex-wrap items-center gap-3">
                    <TextInput v-model="search" class="max-w-xs" placeholder="Search name, reference, email…" @keyup.enter="applyFilters" />
                    <select v-model="status" class="form-select text-sm" @change="applyFilters">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </template>
            <DataTable :empty="!buyers?.data?.length" empty-message="No buyers yet. Create your first buyer to start routing leads.">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Reference</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Credit</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Deliveries</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Leads</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                </template>
                <ClickableTableRow v-for="b in buyers.data" :key="b.id" :href="route('buyers.show', b.id)">
                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ b.name }}</td>
                    <td class="px-6 py-4 font-mono text-xs text-slate-500">{{ b.reference }}</td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ b.email || '—' }}</td>
                    <td class="px-6 py-4 font-medium text-emerald-600 dark:text-emerald-400">{{ formatBuyerCredit(b) }}</td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ b.deliveries_count ?? 0 }}</td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ b.leads_count ?? 0 }}</td>
                    <td class="px-6 py-4"><StatusBadge :status="b.status" /></td>
                    <td class="px-6 py-4 text-right space-x-3" @click.stop>
                        <Link :href="route('billing.show', b.id)" class="text-sm text-emerald-600 hover:text-emerald-500">Billing</Link>
                        <Link :href="route('buyers.edit', b.id)" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Edit</Link>
                    </td>
                </ClickableTableRow>
            </DataTable>
            <Pagination :links="buyers.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
