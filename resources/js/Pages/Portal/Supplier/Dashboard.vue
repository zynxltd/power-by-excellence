<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import Panel from '@/Components/UI/Panel.vue';
import BarChart from '@/Components/UI/BarChart.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head } from '@inertiajs/vue3';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    supplier: Object,
    stats: Object,
    sources: Array,
    charts: Object,
    currency: { type: String, default: 'GBP' },
});

const { formatMoney, currency: displayCurrency } = useMoneyFormat(props.currency);
</script>

<template>
    <Head title="Supplier Portal" />
    <AuthenticatedLayout>
        <div class="portal-hero relative mb-6 overflow-hidden rounded-2xl border border-indigo-200/40 bg-gradient-to-br from-slate-900 via-indigo-950 to-cyan-950 p-6">
            <div class="portal-shine pointer-events-none absolute inset-0" />
            <div class="relative z-10 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-white">Supplier Dashboard</h1>
                    <p class="text-sm text-indigo-200/80">Lead submissions & payouts for {{ supplier.name }}</p>
                </div>
                <AppButton :href="route('portal.supplier.leads')">View all leads</AppButton>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <StatCard label="Leads Submitted Today" :value="stats.leads_today" accent="indigo" />
            <StatCard label="Sold Today" :value="stats.sold_today" accent="emerald" />
            <StatCard :label="`Payout Today (${displayCurrency})`" :value="formatMoney(stats.revenue_today)" accent="cyan" />
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <Panel title="Leads Submitted — Last 7 Days">
                <BarChart
                    :labels="charts.labels"
                    :datasets="[
                        { label: 'Submitted', data: charts.leads, color: '#6366f1' },
                        { label: 'Sold', data: charts.sold, color: '#10b981' },
                    ]"
                />
            </Panel>
            <Panel :title="`Payout — Last 7 Days (${displayCurrency})`">
                <BarChart :labels="charts.labels" :datasets="[{ label: `Payout (${displayCurrency})`, data: charts.payout, color: '#06b6d4' }]" />
            </Panel>
        </div>

        <Panel title="Your Sources (SID)" class="mt-6">
            <div v-if="!sources?.length" class="py-6 text-center text-sm text-slate-500">No sources configured. Contact your platform admin.</div>
            <div v-for="s in sources" :key="s.id" class="flex items-center justify-between border-b border-slate-100 py-3 last:border-0 dark:border-slate-800">
                <div>
                    <span class="font-mono text-sm font-semibold text-indigo-600 dark:text-indigo-400">{{ s.sid }}</span>
                    <span class="ml-2 text-slate-600 dark:text-slate-400">{{ s.name }}</span>
                </div>
            </div>
        </Panel>
    </AuthenticatedLayout>
</template>

<style scoped>
.portal-shine {
    background: linear-gradient(105deg, transparent 40%, rgba(99, 102, 241, 0.12) 50%, transparent 60%);
    background-size: 200% 100%;
    animation: portal-shine 5s ease-in-out infinite;
}
@keyframes portal-shine {
    0%, 100% { background-position: 200% 0; }
    50% { background-position: -200% 0; }
}
</style>
