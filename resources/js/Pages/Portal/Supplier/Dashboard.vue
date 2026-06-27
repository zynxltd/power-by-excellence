<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import BarChart from '@/Components/UI/BarChart.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import SupplierAccountPanel from '@/Components/Portal/SupplierAccountPanel.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    supplier: Object,
    stats: Object,
    account: Object,
    recentLeads: Array,
    recentActivity: Array,
    sourcePerformance: Array,
    charts: Object,
    currency: { type: String, default: 'GBP' },
});

const { formatMoney, currency: displayCurrency } = useMoneyFormat(props.currency);

const formatPayout = (v) => formatMoney(v, { decimals: 2 });

const supplierPortalStrip = computed(() => [
    { label: 'Leads today', value: props.stats.leads_today, accent: 'indigo' },
    { label: 'Sold today', value: props.stats.sold_today, accent: 'emerald' },
    { label: `Payout (7d)`, value: formatMoney(props.stats.payout_7d), accent: 'cyan' },
    { label: 'Sold rate', value: props.stats.sold_rate != null ? `${props.stats.sold_rate}%` : '—', accent: 'violet' },
]);

const payoutChartDatasets = computed(() => [
    { label: `Payout (${displayCurrency.value})`, data: props.charts.payout, color: '#06b6d4' },
]);

const payoutChartTitle = computed(() => `Payout — last 7 days (${displayCurrency.value})`);

const activityLabel = (item) => {
    const sid = item.sid ? ` · ${item.sid}` : '';
    return `${item.status}${sid}`;
};
</script>

<template>
    <Head title="Supplier Portal" />
    <AuthenticatedLayout>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Supplier Dashboard</h1>
                <p class="text-xs text-slate-500">Lead submissions & payouts for {{ supplier.name }}</p>
            </div>
            <div class="flex gap-2">
                <AppButton :href="route('portal.supplier.leads.download')" variant="secondary" external>Download CSV</AppButton>
                <AppButton :href="route('portal.supplier.leads')">View all leads</AppButton>
            </div>
        </div>

        <div
            v-if="stats.rejected_today > 0"
            class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200"
        >
            {{ stats.rejected_today }} lead{{ stats.rejected_today === 1 ? '' : 's' }} rejected or quarantined today.
            <Link :href="route('portal.supplier.leads', { status: 'rejected' })" class="ml-1 font-semibold underline">View leads</Link>
        </div>

        <CompactStatStrip :items="supplierPortalStrip" :columns="4" class="mb-6" />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="grid gap-6 lg:grid-cols-2">
                    <Panel title="Leads submitted — last 7 days">
                        <BarChart
                            :labels="charts.labels"
                            :datasets="[
                                { label: 'Submitted', data: charts.leads, color: '#6366f1' },
                                { label: 'Sold', data: charts.sold, color: '#10b981' },
                            ]"
                        />
                    </Panel>
                    <Panel :title="payoutChartTitle">
                        <BarChart
                            :labels="charts.labels"
                            :datasets="payoutChartDatasets"
                            :value-formatter="formatPayout"
                        />
                    </Panel>
                </div>

                <Panel title="Recent submissions" :padding="false">
                    <DataTable :empty="!recentLeads?.length">
                        <template #head>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Lead</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">SID</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Payout</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500" />
                        </template>
                        <tr v-for="lead in recentLeads" :key="lead.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-6 py-4 font-mono text-xs text-slate-500">{{ lead.uuid?.slice(0, 12) }}…</td>
                            <td class="px-6 py-4 text-sm text-slate-900 dark:text-white">
                                {{ lead.field_data?.firstname }} {{ lead.field_data?.lastname }}
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ lead.sid || '—' }}</td>
                            <td class="px-6 py-4"><StatusBadge :status="lead.status" /></td>
                            <td class="px-6 py-4 font-medium text-emerald-600 dark:text-emerald-400">{{ formatMoney(lead.financials?.payout ?? 0) }}</td>
                            <td class="px-6 py-4 text-right">
                                <Link :href="route('portal.supplier.leads.show', lead.uuid)" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">View</Link>
                            </td>
                        </tr>
                    </DataTable>
                </Panel>

                <Panel v-if="sourcePerformance?.length" title="Source performance (30 days)" :padding="false">
                    <DataTable>
                        <template #head>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">SID</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Submitted</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Sold</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Sold rate</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Payout</th>
                        </template>
                        <tr v-for="row in sourcePerformance" :key="row.sid" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-6 py-4 font-mono text-sm font-semibold text-indigo-600 dark:text-indigo-400">{{ row.sid }}</td>
                            <td class="px-6 py-4 text-sm text-slate-900 dark:text-white">{{ row.submitted }}</td>
                            <td class="px-6 py-4 text-sm text-slate-900 dark:text-white">{{ row.sold }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ row.sold_rate != null ? `${row.sold_rate}%` : '—' }}</td>
                            <td class="px-6 py-4 font-medium text-emerald-600 dark:text-emerald-400">{{ formatMoney(row.payout) }}</td>
                        </tr>
                    </DataTable>
                </Panel>
            </div>

            <div class="space-y-6">
                <SupplierAccountPanel :account="account" :currency="currency" />

                <Panel title="Recent activity">
                    <div v-if="!recentActivity?.length" class="py-4 text-sm text-slate-500">No submissions yet.</div>
                    <ul v-else class="space-y-3">
                        <li v-for="(item, index) in recentActivity" :key="index" class="border-b border-slate-100 pb-3 last:border-0 dark:border-slate-800">
                            <p class="text-sm font-medium capitalize text-slate-900 dark:text-white">{{ activityLabel(item) }}</p>
                            <p class="mt-0.5 font-mono text-xs text-slate-500">{{ item.lead_uuid?.slice(0, 12) }}…</p>
                            <FormattedDate :value="item.at" class="mt-1 text-xs text-slate-400" />
                        </li>
                    </ul>
                </Panel>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
