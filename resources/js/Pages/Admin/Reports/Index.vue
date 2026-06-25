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
    byCampaign: Object,
    bySid: Object,
    deliveryPerformance: Object,
    tierSummary: Object,
    distributionOutcome: Object,
    leadStatusBreakdown: Object,
    pingTree: Object,
    summary: Object,
});

const selectedDays = ref(props.days);
const selectedMonth = ref(props.month ?? new Date().toISOString().slice(0, 7));
const { formatMoney, formatNumber } = useMoneyFormat(props.currency);

const applyMonth = () => {
    router.get(route('reports.index'), { month: selectedMonth.value }, { preserveState: true, replace: true });
};

const applyDays = (days) => {
    selectedDays.value = days;
    router.get(route('reports.index'), { days }, { preserveState: true, replace: true });
};
watch(() => props.days, (d) => { selectedDays.value = d; });

const kpis = computed(() => props.summary?.kpis ?? {});
const delivery = computed(() => props.summary?.delivery ?? {});

const volumeCards = computed(() => [
    { label: `Leads (${props.days}d)`, value: formatNumber(props.summary?.leads_period), href: route('leads.index'), cls: '' },
    { label: 'Sold', value: formatNumber(props.summary?.sold_period), href: route('leads.index', { status: 'sold' }), cls: 'text-emerald-600 dark:text-emerald-400' },
    { label: 'Unsold', value: formatNumber(props.summary?.unsold_period), href: route('leads.index', { status: 'unsold' }), cls: 'text-amber-600 dark:text-amber-400' },
    { label: 'Rejected', value: formatNumber(props.summary?.rejected_period), href: route('leads.index', { status: 'rejected' }), cls: 'text-rose-600 dark:text-rose-400' },
    { label: 'Quarantined', value: formatNumber(props.summary?.quarantined_period), href: route('quarantine.index'), cls: 'text-orange-600 dark:text-orange-400' },
    { label: 'Revenue', value: formatMoney(props.summary?.revenue_period, { decimals: 0 }), href: route('billing.index'), cls: 'text-cyan-600 dark:text-cyan-400' },
    { label: 'Payout', value: formatMoney(props.summary?.payout_period, { decimals: 0 }), href: route('finance.index'), cls: 'text-amber-600 dark:text-amber-400' },
    { label: 'Margin', value: formatMoney(props.summary?.margin_period, { decimals: 0 }), href: route('finance.index'), cls: 'text-violet-600 dark:text-violet-400' },
]);

const economicsCards = computed(() => [
    { label: 'EPL (sold)', hint: 'Revenue ÷ sold leads', value: formatMoney(kpis.value.epl), cls: 'text-cyan-600 dark:text-cyan-400' },
    { label: 'EPC (ingest)', hint: 'Revenue ÷ leads received', value: formatMoney(kpis.value.epc), cls: 'text-indigo-600 dark:text-indigo-400' },
    { label: 'CPA (payout)', hint: 'Payout ÷ sold leads', value: formatMoney(kpis.value.cpa), cls: 'text-amber-600 dark:text-amber-400' },
    { label: 'CPL (buyer)', hint: 'Revenue ÷ sold leads', value: formatMoney(kpis.value.cpl), cls: 'text-emerald-600 dark:text-emerald-400' },
    { label: 'MPL (margin)', hint: 'Margin ÷ sold leads', value: formatMoney(kpis.value.mpl), cls: 'text-violet-600 dark:text-violet-400' },
    { label: 'Margin %', hint: 'Margin ÷ revenue', value: (kpis.value.margin_pct ?? 0) + '%', cls: 'text-violet-600 dark:text-violet-400' },
]);

const rateCards = computed(() => [
    { label: 'Conversion', hint: 'Sold ÷ received', value: (props.summary?.conversion ?? 0) + '%' },
    { label: 'Sell-through', hint: 'Sold ÷ (sold + unsold)', value: (props.summary?.sell_through ?? 0) + '%' },
    { label: 'Reject rate', hint: 'Rejected ÷ received', value: (props.summary?.reject_rate ?? 0) + '%' },
    { label: 'Ping success', hint: 'Successful delivery attempts', value: (delivery.value.success_rate ?? 0) + '%' },
    { label: 'Outbid rate', hint: 'Outbid ÷ delivery attempts', value: (delivery.value.outbid_rate ?? 0) + '%' },
    { label: 'Avg latency', hint: 'Mean delivery duration', value: delivery.value.avg_duration_ms ? `${delivery.value.avg_duration_ms}ms` : '—' },
]);

const methodLabels = { direct_post: 'Direct API', ping_post: 'Ping Post', store_lead: 'Store', email: 'Email', sms: 'SMS' };
const successRate = (row) => (!row.attempts ? '—' : `${Math.round((row.successes / row.attempts) * 100)}%`);
const winRate = (row) => (!row.attempts ? '—' : `${Math.round((row.wins / row.attempts) * 100)}%`);
const conversionRate = (row) => (!row.received ? '—' : `${Math.round((row.sold / row.received) * 100)}%`);
const eplForRow = (row) => (!row.sold ? '—' : formatMoney(row.revenue / row.sold));

const deliveryLogFilter = (params = {}) => route('logs.delivery', { days: props.days, ...params });

const statusLabels = {
    pending: 'Pending',
    validating: 'Validating',
    accepted: 'Accepted',
    rejected: 'Rejected',
    distributing: 'Distributing',
    sold: 'Sold',
    unsold: 'Unsold',
    quarantined: 'Quarantined',
    returned: 'Returned',
};
</script>

<template>
    <Head title="Reports" />
    <AuthenticatedLayout>
        <PageHeader title="Reports" description="Volume, unit economics (EPL, EPC, CPA), campaign and affiliate performance, and ping-tree delivery analytics.">
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

        <section class="mb-6">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Volume</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
                <Link
                    v-for="card in volumeCards"
                    :key="card.label"
                    :href="card.href"
                    class="rounded-xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 px-4 py-3 shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-slate-800 dark:from-slate-900 dark:to-slate-950"
                >
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ card.label }}</p>
                    <p :class="['mt-1 text-2xl font-bold text-slate-900 dark:text-white', card.cls]">{{ card.value }}</p>
                </Link>
            </div>
        </section>

        <section class="mb-6">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Unit economics</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <div
                    v-for="card in economicsCards"
                    :key="card.label"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900"
                    :title="card.hint"
                >
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ card.label }}</p>
                    <p :class="['mt-1 text-2xl font-bold text-slate-900 dark:text-white', card.cls]">{{ card.value }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ card.hint }}</p>
                </div>
            </div>
            <p class="mt-2 text-xs text-slate-500">
                <strong>EPC (ingest)</strong> uses leads received as the click proxy until dedicated click tracking is enabled.
                <strong>EPL (sold)</strong> is revenue per converted lead.
            </p>
        </section>

        <section class="mb-6">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Rates &amp; delivery health</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <div
                    v-for="card in rateCards"
                    :key="card.label"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-center dark:border-slate-800 dark:bg-slate-900"
                    :title="card.hint"
                >
                    <p class="text-xs font-semibold uppercase text-slate-500">{{ card.label }}</p>
                    <p class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ card.value }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ card.hint }}</p>
                </div>
            </div>
        </section>

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
                    <h3 class="font-semibold text-slate-900 dark:text-white">Revenue, payout &amp; margin — {{ days }} days</h3>
                </template>
                <LineChart
                    :labels="charts?.labels ?? []"
                    :datasets="[
                        { label: `Revenue (${currency})`, data: charts?.revenue ?? [], color: '#06b6d4' },
                        { label: `Payout (${currency})`, data: charts?.payout ?? [], color: '#f59e0b' },
                        { label: `Margin (${currency})`, data: charts?.margin ?? [], color: '#8b5cf6' },
                    ]"
                    :value-formatter="(v) => formatMoney(v, { decimals: 0 })"
                    :drilldown-route="route('finance.index')"
                />
            </Panel>
        </div>

        <div v-if="leadStatusBreakdown && Object.keys(leadStatusBreakdown).length" class="mt-6">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Lead status breakdown</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <Link
                    v-for="(count, status) in leadStatusBreakdown"
                    :key="status"
                    :href="route('leads.index', { status })"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-center transition hover:border-indigo-300 dark:border-slate-800 dark:bg-slate-900"
                >
                    <p class="text-xs font-semibold uppercase text-slate-500">{{ statusLabels[status] ?? status }}</p>
                    <p class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ count }}</p>
                </Link>
            </div>
        </div>

        <div v-if="distributionOutcome && Object.keys(distributionOutcome).length" class="mt-6">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Delivery log outcomes</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
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
        </div>

        <Panel class="mt-6">
            <template #header>
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white">Campaign performance</h3>
                    <p class="mt-1 text-sm text-slate-500">Per-campaign volume, conversion, revenue, and EPL for the selected period.</p>
                </div>
            </template>
            <DataTable :empty="!byCampaign?.data?.length" empty-message="No campaign data for this period.">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Campaign</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Received</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Sold</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Unsold</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Rejected</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Conv.</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Revenue</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Payout</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Margin</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">EPL</th>
                </template>
                <ClickableTableRow
                    v-for="row in byCampaign.data"
                    :key="row.campaign_id"
                    :href="route('campaigns.show', row.campaign_id)"
                >
                    <td class="px-6 py-4">
                        <span class="font-medium">{{ row.name }}</span>
                        <span v-if="row.reference" class="ml-1 text-xs text-slate-400">{{ row.reference }}</span>
                    </td>
                    <td class="px-6 py-4">{{ row.received }}</td>
                    <td class="px-6 py-4 text-emerald-600">{{ row.sold }}</td>
                    <td class="px-6 py-4 text-amber-600">{{ row.unsold }}</td>
                    <td class="px-6 py-4 text-rose-600">{{ row.rejected }}</td>
                    <td class="px-6 py-4">{{ conversionRate(row) }}</td>
                    <td class="px-6 py-4">{{ formatMoney(row.revenue) }}</td>
                    <td class="px-6 py-4">{{ formatMoney(row.payout) }}</td>
                    <td class="px-6 py-4">{{ formatMoney(row.margin) }}</td>
                    <td class="px-6 py-4 font-medium text-cyan-600">{{ eplForRow(row) }}</td>
                </ClickableTableRow>
            </DataTable>
            <Pagination :links="byCampaign.links" />
        </Panel>

        <Panel class="mt-6">
            <template #header>
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white">Affiliate / SID performance</h3>
                    <p class="mt-1 text-sm text-slate-500">Source ID (SID) breakdown with EPL and conversion for affiliate traffic analysis.</p>
                </div>
            </template>
            <DataTable :empty="!bySid?.data?.length" empty-message="No SID data — leads need sid on ingest.">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">SID</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Supplier</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Received</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Sold</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Rejected</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Conv.</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Revenue</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Payout</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">EPL</th>
                </template>
                <ClickableTableRow
                    v-for="row in bySid.data"
                    :key="`${row.sid}-${row.supplier_id}`"
                    :href="row.supplier_id ? route('suppliers.show', row.supplier_id) : route('leads.index')"
                >
                    <td class="px-6 py-4 font-mono text-sm font-medium">{{ row.sid }}</td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ row.supplier_name ?? '—' }}</td>
                    <td class="px-6 py-4">{{ row.received }}</td>
                    <td class="px-6 py-4 text-emerald-600">{{ row.sold }}</td>
                    <td class="px-6 py-4 text-rose-600">{{ row.rejected }}</td>
                    <td class="px-6 py-4">{{ conversionRate(row) }}</td>
                    <td class="px-6 py-4">{{ formatMoney(row.revenue) }}</td>
                    <td class="px-6 py-4">{{ formatMoney(row.payout) }}</td>
                    <td class="px-6 py-4 font-medium text-cyan-600">{{ eplForRow(row) }}</td>
                </ClickableTableRow>
            </DataTable>
            <Pagination :links="bySid.links" />
        </Panel>

        <Panel class="mt-6">
            <template #header>
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white">Ping tree tier summary</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Each <strong>tier</strong> is a step in the ping tree — buyers are pinged in tier order.
                        <span v-if="pingTree?.config_name"> Demo snapshot: {{ pingTree.config_name }} on {{ pingTree.campaign_name }} ({{ pingTree.tier_count }} tiers).</span>
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
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Avg ms</th>
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
                    <td class="px-6 py-4 text-sm text-slate-500">{{ row.avg_duration_ms ? Math.round(row.avg_duration_ms) : '—' }}</td>
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
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Margin</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">EPL</th>
                    </template>
                    <ClickableTableRow
                        v-for="row in byBuyer.data"
                        :key="row.buyer_id"
                        :href="route('billing.show', row.buyer_id)"
                    >
                        <td class="px-6 py-4 font-medium">{{ row.name }}</td>
                        <td class="px-6 py-4">{{ row.leads }}</td>
                        <td class="px-6 py-4 text-emerald-600">{{ formatMoney(row.revenue) }}</td>
                        <td class="px-6 py-4 text-violet-600">{{ formatMoney(row.margin) }}</td>
                        <td class="px-6 py-4 font-medium text-cyan-600">{{ eplForRow({ sold: row.leads, revenue: row.revenue }) }}</td>
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
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Revenue</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">CPA</th>
                    </template>
                    <ClickableTableRow
                        v-for="row in bySupplier.data"
                        :key="row.supplier_id"
                        :href="route('suppliers.show', row.supplier_id)"
                    >
                        <td class="px-6 py-4 font-medium">{{ row.name }}</td>
                        <td class="px-6 py-4">{{ row.leads }}</td>
                        <td class="px-6 py-4 text-amber-600">{{ formatMoney(row.payout) }}</td>
                        <td class="px-6 py-4 text-emerald-600">{{ formatMoney(row.revenue) }}</td>
                        <td class="px-6 py-4 font-medium text-amber-600">{{ row.leads ? formatMoney(row.payout / row.leads) : '—' }}</td>
                    </ClickableTableRow>
                </DataTable>
                <Pagination :links="bySupplier.links" />
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
