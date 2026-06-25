<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import BarChart from '@/Components/UI/BarChart.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    buyer: Object,
    stats: Object,
    recentLeads: Array,
    charts: Object,
    currency: { type: String, default: 'GBP' },
});

const { formatMoney, currency: displayCurrency } = useMoneyFormat(props.currency);

const buyerPortalStrip = computed(() => [
    { label: 'Credit', value: formatMoney(props.buyer.credit_balance), accent: 'emerald' },
    { label: 'Leads today', value: props.stats.leads_today, accent: 'indigo' },
    { label: 'Total purchased', value: props.stats.total_leads, accent: 'cyan' },
]);
</script>

<template>
    <Head title="Buyer Portal" />
    <AuthenticatedLayout>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Buyer Dashboard</h1>
                <p class="text-xs text-slate-500">Credit & lead performance for {{ buyer.name }}</p>
            </div>
            <div class="flex gap-2">
                <AppButton :href="route('portal.buyer.leads.download')" variant="secondary">Download CSV</AppButton>
                <AppButton :href="route('portal.buyer.leads')">View all leads</AppButton>
            </div>
        </div>

        <CompactStatStrip :items="buyerPortalStrip" :columns="3" class="mb-6" />

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <Panel title="Leads Purchased — Last 7 Days">
                <BarChart :labels="charts.labels" :datasets="[{ label: 'Leads', data: charts.leads, color: '#6366f1' }]" />
            </Panel>
            <Panel :title="`Spend — Last 7 Days (${displayCurrency})`">
                <BarChart :labels="charts.labels" :datasets="[{ label: `Spend (${displayCurrency})`, data: charts.spend, color: '#10b981' }]" />
            </Panel>
        </div>

        <Panel title="Recent Purchased Leads" class="mt-6" :padding="false">
            <DataTable :empty="!recentLeads?.length">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Lead</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Cost</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                </template>
                <tr v-for="lead in recentLeads" :key="lead.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4 font-mono text-xs text-slate-500">{{ lead.uuid?.slice(0, 12) }}…</td>
                    <td class="px-6 py-4 capitalize text-slate-600 dark:text-slate-400">{{ lead.status }}</td>
                    <td class="px-6 py-4 font-medium text-emerald-600 dark:text-emerald-400">{{ formatMoney(lead.financials?.revenue ?? 0) }}</td>
                    <td class="px-6 py-4"><FormattedDate :value="lead.distributed_at" /></td>
                </tr>
            </DataTable>
        </Panel>
    </AuthenticatedLayout>
</template>

<style scoped>
.portal-shine {
    background: linear-gradient(105deg, transparent 40%, rgba(52, 211, 153, 0.12) 50%, transparent 60%);
    background-size: 200% 100%;
    animation: portal-shine 5s ease-in-out infinite;
}
@keyframes portal-shine {
    0%, 100% { background-position: 200% 0; }
    50% { background-position: -200% 0; }
}
</style>
