<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import BarChart from '@/Components/UI/BarChart.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    buyer: Object,
    stats: Object,
    recentLeads: Array,
    charts: Object,
    currency: { type: String, default: 'GBP' },
});

const symbol = computed(() => ({ GBP: '£', USD: '$', EUR: '€' }[props.currency] ?? props.currency + ' '));
</script>

<template>
    <Head title="Buyer Portal" />
    <AuthenticatedLayout>
        <div class="portal-hero relative mb-6 overflow-hidden rounded-2xl border border-emerald-200/40 bg-gradient-to-br from-slate-900 via-emerald-950 to-teal-950 p-6 dark:border-emerald-500/20">
            <div class="portal-shine pointer-events-none absolute inset-0" />
            <div class="relative z-10 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-white">Buyer Dashboard</h1>
                    <p class="text-sm text-emerald-200/80">Credit & lead performance for {{ buyer.name }}</p>
                </div>
                <div class="flex gap-2">
                    <AppButton :href="route('portal.buyer.leads.download')" variant="secondary">Download CSV</AppButton>
                    <AppButton :href="route('portal.buyer.leads')">View all leads</AppButton>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <StatCard label="Credit Balance" :value="symbol + buyer.credit_balance" accent="emerald" />
            <StatCard label="Leads Today" :value="stats.leads_today" accent="indigo" />
            <StatCard label="Total Leads Purchased" :value="stats.total_leads" accent="cyan" />
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <Panel title="Leads Purchased — Last 7 Days">
                <BarChart :labels="charts.labels" :datasets="[{ label: 'Leads', data: charts.leads, color: '#6366f1' }]" />
            </Panel>
            <Panel :title="`Spend — Last 7 Days (${currency})`">
                <BarChart :labels="charts.labels" :datasets="[{ label: `Spend (${symbol.trim()})`, data: charts.spend, color: '#10b981' }]" />
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
                    <td class="px-6 py-4 font-medium text-emerald-600 dark:text-emerald-400">{{ symbol }}{{ lead.financials?.revenue ?? 0 }}</td>
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
