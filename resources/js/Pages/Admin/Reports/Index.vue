<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import LineChart from '@/Components/UI/LineChart.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import ClickableTableRow from '@/Components/UI/ClickableTableRow.vue';
import TenantContextBanner from '@/Components/UI/TenantContextBanner.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    days: { type: Number, default: 28 },
    month: { type: String, default: null },
    currency: String,
    charts: Object,
    byBuyer: Object,
    bySupplier: Object,
    deliveryPerformance: Object,
    tierSummary: Object,
    distributionOutcome: Object,
    pingTree: Object,
    summary: Object,
});

const selectedDays = ref(props.days);
const selectedMonth = ref(props.month ?? new Date().toISOString().slice(0, 7));
const { formatMoney, formatNumber } = useMoneyFormat(props.currency);

const applyMonth = () => {
    router.get(route('reports.index'), { month: selectedMonth.value }, { preserveState: true, replace: true });
};

const summaryCards = computed(() => [
    { label: `Leads (${props.days}d)`, value: formatNumber(props.summary?.leads_period), href: route('leads.index'), cls: '' },
    { label: 'Sold', value: formatNumber(props.summary?.sold_period), href: route('leads.index', { status: 'sold' }), cls: 'text-emerald-600 dark:text-emerald-400' },
    { label: 'Unsold', value: formatNumber(props.summary?.unsold_period), href: route('leads.index', { status: 'unsold' }), cls: 'text-amber-600 dark:text-amber-400' },
    { label: 'Rejected', value: formatNumber(props.summary?.rejected_period), href: route('leads.index', { status: 'rejected' }), cls: 'text-rose-600 dark:text-rose-400' },
    { label: 'Revenue', value: formatMoney(props.summary?.revenue_period, { decimals: 0 }), href: route('billing.index'), sub: 'Buyer billing ledger', cls: 'text-cyan-600 dark:text-cyan-400' },
    { label: 'Payout', value: formatMoney(props.summary?.payout_period, { decimals: 0 }), href: route('finance.index'), sub: 'Supplier payouts', cls: 'text-amber-600 dark:text-amber-400' },
    { label: 'Margin', value: formatMoney(props.summary?.margin_period, { decimals: 0 }), href: route('finance.index'), sub: 'Revenue − payout', cls: 'text-violet-600 dark:text-violet-400' },
    { label: 'Conversion', value: (props.summary?.conversion ?? 0) + '%', href: route('reports.index', { days: props.days }), cls: '' },
]);

const applyDays = (days) => {
    selectedDays.value = days;
    router.get(route('reports.index'), { days }, { preserveState: true, replace: true });
};
watch(() => props.days, (d) => { selectedDays.value = d; });

const methodLabels = { direct_post: 'Direct API', ping_post: 'Ping Post', store_lead: 'Store', email: 'Email', sms: 'SMS' };
const successRate = (row) => (!row.attempts ? '—' : `${Math.round((row.successes / row.attempts) * 100)}%`);
const winRate = (row) => (!row.attempts ? '—' : `${Math.round((row.wins / row.attempts) * 100)}%`);

const deliveryLogFilter = (params = {}) => route('logs.delivery', { days: props.days, ...params });
</script>

<template>
    <Head title="Reports" />
    <AuthenticatedLayout>
        <PageHeader title="Reports" description="Revenue, conversion, and ping-tree delivery analytics. Click any row to drill down.">
            <template #actions>
                <AppButton :href="route('billing.index')" variant="secondary">Billing ledger</AppButton>
                <AppButton :href="route('finance.index')" variant="secondary">Finance</AppButton>
                <AppButton v-if="pingTree?.campaign_id" :href="route('campaigns.show', pingTree.campaign_id)" variant="secondary">Ping tree campaign</AppButton>
                <div class="flex items-center gap-2">
                    <label class="text-xs font-medium text-slate-500">Monthly report</label>
                    <input v-model="selectedMonth" type="month" class="form-input text-sm" @change="applyMonth" />
                </div>
                <div class="flex rounded-lg border border-slate-200 p-0.5 dark:border-slate-700">
                    <button v-for="d in [7, 14, 28, 30]" :key="d" type="button" :class="['rounded-md px-3 py-1.5 text-xs font-semibold transition', selectedDays === d ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800']" @click="applyDays(d)">{{ d }}d</button>
                </div>
            </template>
        </PageHeader>

        <TenantContextBanner />

        <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
            <Link
                v-for="card in summaryCards"
                :key="card.label"
                :href="card.href"
                class="rounded-xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 px-4 py-3 shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-slate-800 dark:from-slate-900 dark:to-slate-950"
            >
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ card.label }}</p>
                <p :class="['mt-1 text-2xl font-bold text-slate-900 dark:text-white', card.cls]">{{ card.value }}</p>
                <p v-if="card.sub" class="mt-1 text-xs text-indigo-600 dark:text-indigo-400">{{ card.sub }} →</p>
            </Link>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <Panel>
                <template #header>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-semibold text-slate-900 dark:text-white">Lead volume — {{ days }} days</h3>
                        <p class="text-xs text-slate-500">Hover points · click to open leads</p>
                    </div>
                </template>
                <LineChart
                    :labels="charts?.labels ?? []"
                    :datasets="[
                        { label: 'Received', data: charts?.leads ?? [], color: '#6366f1' },
                        { label: 'Sold', data: charts?.sold ?? [], color: '#10b981' },
                        { label: 'Rejected', data: charts?.rejected ?? [], color: '#f43f5e' },
                    ]"
                    :drilldown-route="route('leads.index')"
                />
            </Panel>
            <Panel>
                <template #header>
                    <h3 class="font-semibold text-slate-900 dark:text-white">Revenue — {{ days }} days</h3>
                </template>
                <LineChart
                    :labels="charts?.labels ?? []"
                    :datasets="[{ label: `Revenue (${currency})`, data: charts?.revenue ?? [], color: '#06b6d4' }]"
                    :value-formatter="(v) => formatMoney(v, { decimals: 0 })"
                    :drilldown-route="route('billing.index')"
                />
            </Panel>
        </div>

        <div v-if="distributionOutcome && Object.keys(distributionOutcome).length" class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <Link
                v-for="(count, status) in distributionOutcome"
                :key="status"
                :href="deliveryLogFilter({ status })"
                class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-center transition hover:border-indigo-300 dark:border-slate-800 dark:bg-slate-900"
            >
                <p class="text-xs font-semibold uppercase text-slate-500">{{ status }}</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ count }}</p>
            </Link>
        </div>

        <Panel class="mt-6">
            <template #header>
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white">Ping tree tier summary</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Each <strong>tier</strong> is a step in the ping tree — buyers are pinged in tier order (parallel auction, waterfall, etc.).
                        There is <strong>no tier limit</strong>; add as many as you need on the ping tree.
                        <span v-if="pingTree?.config_name"> Demo snapshot: {{ pingTree.config_name }} on {{ pingTree.campaign_name }} ({{ pingTree.tier_count }} tiers seeded for reporting).</span>
                    </p>
                </div>
            </template>
            <DataTable :empty="!tierSummary?.data?.length" empty-message="No tier data — run php artisan db:seed to populate demo history.">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Tier</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Attempts</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Wins</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Outbid</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Rejections</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Win rate</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Revenue</th>
                </template>
                <ClickableTableRow
                    v-for="row in tierSummary.data"
                    :key="row.tier"
                    :href="deliveryLogFilter({ tier: row.tier })"
                >
                    <td class="px-6 py-4 font-medium">Tier {{ row.tier }}</td>
                    <td class="px-6 py-4">{{ row.attempts }}</td>
                    <td class="px-6 py-4 text-emerald-600">{{ row.wins }}</td>
                    <td class="px-6 py-4 text-amber-600">{{ row.outbid }}</td>
                    <td class="px-6 py-4 text-rose-600">{{ row.rejections }}</td>
                    <td class="px-6 py-4">{{ winRate(row) }}</td>
                    <td class="px-6 py-4">{{ formatMoney(row.revenue) }}</td>
                </ClickableTableRow>
            </DataTable>
            <Pagination :links="tierSummary.links" />
        </Panel>

        <Panel class="mt-6" :padding="false">
            <template #header>
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white">Delivery performance by buyer route</h3>
                    <p class="mt-1 text-sm text-slate-500">Each row is a buyer delivery endpoint. Click to view individual post/ping attempts in delivery logs.</p>
                </div>
            </template>
            <DataTable :empty="!deliveryPerformance?.data?.length">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Delivery</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Buyer</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Tier</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Method</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Attempts</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Success</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Outbid</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Rate</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Revenue</th>
                </template>
                <ClickableTableRow
                    v-for="row in deliveryPerformance.data"
                    :key="row.delivery_id"
                    :href="deliveryLogFilter({ delivery_id: row.delivery_id })"
                >
                    <td class="px-6 py-4">
                        <Link :href="route('deliveries.show', row.delivery_id)" class="font-medium text-indigo-600 hover:underline" @click.stop>{{ row.name }}</Link>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ row.buyer_name ?? '—' }}</td>
                    <td class="px-6 py-4">{{ row.tier ?? '—' }}</td>
                    <td class="px-6 py-4 text-xs capitalize text-slate-500">{{ methodLabels[row.method] ?? row.method }}</td>
                    <td class="px-6 py-4">{{ row.attempts }}</td>
                    <td class="px-6 py-4 text-emerald-600">{{ row.successes }}</td>
                    <td class="px-6 py-4 text-amber-600">{{ row.outbid }}</td>
                    <td class="px-6 py-4">{{ successRate(row) }}</td>
                    <td class="px-6 py-4">{{ formatMoney(row.revenue) }}</td>
                </ClickableTableRow>
            </DataTable>
            <Pagination :links="deliveryPerformance.links" />
        </Panel>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <Panel title="Top buyers (period)" :padding="false">
                <DataTable :empty="!byBuyer?.data?.length">
                    <template #head>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Buyer</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Leads</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Revenue</th>
                    </template>
                    <ClickableTableRow
                        v-for="row in byBuyer.data"
                        :key="row.buyer_id"
                        :href="route('billing.show', row.buyer_id)"
                    >
                        <td class="px-6 py-4 font-medium">{{ row.name }}</td>
                        <td class="px-6 py-4">
                            <Link :href="route('leads.index', { status: 'sold' })" class="hover:text-indigo-600" @click.stop>{{ row.leads }}</Link>
                        </td>
                        <td class="px-6 py-4 text-emerald-600">{{ formatMoney(row.revenue) }}</td>
                    </ClickableTableRow>
                </DataTable>
                <Pagination :links="byBuyer.links" />
            </Panel>
            <Panel title="Top suppliers (period)" :padding="false">
                <DataTable :empty="!bySupplier?.data?.length">
                    <template #head>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Supplier</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Leads</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Payout</th>
                    </template>
                    <ClickableTableRow
                        v-for="row in bySupplier.data"
                        :key="row.supplier_id"
                        :href="route('suppliers.show', row.supplier_id)"
                    >
                        <td class="px-6 py-4 font-medium">{{ row.name }}</td>
                        <td class="px-6 py-4">{{ row.leads }}</td>
                        <td class="px-6 py-4 text-amber-600">{{ formatMoney(row.payout) }}</td>
                    </ClickableTableRow>
                </DataTable>
                <Pagination :links="bySupplier.links" />
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
