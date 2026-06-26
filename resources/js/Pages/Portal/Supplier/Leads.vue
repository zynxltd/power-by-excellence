<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import InputLabel from '@/Components/InputLabel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    leads: Object,
    filters: Object,
    campaigns: Array,
    statuses: Array,
});

const localFilters = ref({ ...props.filters });
const applyFilters = () => router.get(route('portal.supplier.leads'), localFilters.value, { preserveState: true, replace: true });
const clearFilters = () => { localFilters.value = {}; applyFilters(); };
watch(() => props.filters, (f) => { localFilters.value = { ...f }; });

const { formatMoney } = useMoneyFormat();
</script>

<template>
    <Head title="Supplier Leads" />
    <AuthenticatedLayout>
        <PageHeader title="Supplier Leads" description="All leads submitted through your sources — filter by campaign, status, and date.">
            <template #actions>
                <AppButton :href="route('portal.supplier.leads.download', localFilters)" variant="secondary" external>Export CSV</AppButton>
            </template>
        </PageHeader>

        <Panel title="Filters" class="mb-6">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <div><InputLabel value="Search" /><input v-model="localFilters.search" class="form-input mt-1 w-full" placeholder="UUID or email" @keyup.enter="applyFilters" /></div>
                <div>
                    <InputLabel value="Status" />
                    <select v-model="localFilters.status" class="form-select mt-1 w-full">
                        <option value="">All</option>
                        <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Campaign" />
                    <select v-model="localFilters.campaign_id" class="form-select mt-1 w-full">
                        <option value="">All</option>
                        <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div><InputLabel value="From" /><input v-model="localFilters.from_date" type="date" class="form-input mt-1 w-full" /></div>
                <div><InputLabel value="To" /><input v-model="localFilters.to_date" type="date" class="form-input mt-1 w-full" /></div>
            </div>
            <div class="mt-3 flex gap-2">
                <AppButton @click="applyFilters">Apply</AppButton>
                <AppButton variant="secondary" @click="clearFilters">Clear</AppButton>
            </div>
        </Panel>

        <Panel :padding="false">
            <DataTable :empty="!leads.data?.length">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">UUID</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">SID</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Payout</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Received</th>
                </template>
                <tr v-for="lead in leads.data" :key="lead.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4 font-mono text-xs text-slate-500">{{ lead.uuid?.slice(0, 10) }}…</td>
                    <td class="px-6 py-4 text-slate-900 dark:text-white">{{ lead.campaign?.name }}</td>
                    <td class="px-6 py-4 font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ lead.sid }}</td>
                    <td class="px-6 py-4"><StatusBadge :status="lead.status" /></td>
                    <td class="px-6 py-4 font-medium text-emerald-600 dark:text-emerald-400">{{ formatMoney(lead.financials?.payout ?? 0) }}</td>
                    <td class="px-6 py-4"><FormattedDate :value="lead.received_at" /></td>
                </tr>
            </DataTable>
            <Pagination :links="leads.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
