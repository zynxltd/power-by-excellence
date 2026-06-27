<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import BarChart from '@/Components/UI/BarChart.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import BuyerAccountPanel from '@/Components/Portal/BuyerAccountPanel.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    buyer: Object,
    stats: Object,
    account: Object,
    recentLeads: Array,
    recentActivity: Array,
    charts: Object,
    currency: { type: String, default: 'GBP' },
});

const { formatMoney, currency: displayCurrency } = useMoneyFormat(props.currency);

const buyerPortalStrip = computed(() => [
    { label: 'Credit', value: formatMoney(props.buyer.credit_balance), accent: props.account?.is_low_credit ? 'rose' : 'emerald' },
    { label: 'Leads today', value: props.stats.leads_today, accent: 'indigo' },
    { label: 'Spend (7d)', value: formatMoney(props.stats.spend_7d), accent: 'cyan' },
    { label: 'Conversion', value: props.stats.conversion_rate != null ? `${props.stats.conversion_rate}%` : '—', accent: 'violet' },
]);

const activityLabel = (item) => {
    if (item.type === 'return') {
        return `Return · ${item.status}`;
    }

    return `Feedback · ${item.status}${item.converted ? ' · converted' : ''}`;
};
</script>

<template>
    <Head title="Buyer Portal" />
    <AuthenticatedLayout>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Buyer Dashboard</h1>
                <p class="text-xs text-slate-500">Performance & inventory for {{ buyer.name }}</p>
            </div>
            <div class="flex gap-2">
                <AppButton :href="route('portal.buyer.leads.download')" variant="secondary">Download CSV</AppButton>
                <AppButton :href="route('portal.buyer.leads')">View all leads</AppButton>
            </div>
        </div>

        <div
            v-if="stats.pending_returns > 0"
            class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200"
        >
            {{ stats.pending_returns }} return{{ stats.pending_returns === 1 ? '' : 's' }} awaiting platform review.
            <Link :href="route('portal.buyer.leads')" class="ml-1 font-semibold underline">View leads</Link>
        </div>

        <CompactStatStrip :items="buyerPortalStrip" :columns="4" class="mb-6" />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="grid gap-6 lg:grid-cols-2">
                    <Panel title="Leads purchased — last 7 days">
                        <BarChart :labels="charts.labels" :datasets="[{ label: 'Leads', data: charts.leads, color: '#6366f1' }]" />
                    </Panel>
                    <Panel :title="`Spend — last 7 days (${displayCurrency})`">
                        <BarChart
                            :labels="charts.labels"
                            :datasets="[{ label: `Spend (${displayCurrency})`, data: charts.spend, color: '#10b981' }]"
                            :value-formatter="(v) => formatMoney(v)"
                        />
                    </Panel>
                </div>

                <Panel title="Recent purchased leads" :padding="false">
                    <DataTable :empty="!recentLeads?.length">
                        <template #head>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Lead</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Feedback</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Cost</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500" />
                        </template>
                        <tr v-for="lead in recentLeads" :key="lead.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-6 py-4 font-mono text-xs text-slate-500">{{ lead.uuid?.slice(0, 12) }}…</td>
                            <td class="px-6 py-4 text-sm text-slate-900 dark:text-white">
                                {{ lead.field_data?.firstname }} {{ lead.field_data?.lastname }}
                            </td>
                            <td class="px-6 py-4 text-xs capitalize text-slate-500">
                                {{ lead.feedback?.status ?? '—' }}
                            </td>
                            <td class="px-6 py-4 font-medium text-emerald-600 dark:text-emerald-400">{{ formatMoney(lead.financials?.revenue ?? 0) }}</td>
                            <td class="px-6 py-4 text-right">
                                <Link :href="route('portal.buyer.leads.show', lead.uuid)" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">View</Link>
                            </td>
                        </tr>
                    </DataTable>
                </Panel>
            </div>

            <div class="space-y-6">
                <BuyerAccountPanel :account="account" :currency="currency" />

                <Panel title="Recent activity">
                    <div v-if="!recentActivity?.length" class="py-4 text-sm text-slate-500">No feedback or returns yet.</div>
                    <ul v-else class="space-y-3">
                        <li v-for="(item, index) in recentActivity" :key="index" class="border-b border-slate-100 pb-3 last:border-0 dark:border-slate-800">
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ activityLabel(item) }}</p>
                            <p class="mt-0.5 font-mono text-xs text-slate-500">{{ item.lead_uuid?.slice(0, 12) }}…</p>
                            <FormattedDate :value="item.at" class="mt-1 text-xs text-slate-400" />
                        </li>
                    </ul>
                </Panel>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
