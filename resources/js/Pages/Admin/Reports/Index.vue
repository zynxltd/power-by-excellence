<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import LineChart from '@/Components/UI/LineChart.vue';
import BarChart from '@/Components/UI/BarChart.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import ClickableTableRow from '@/Components/UI/ClickableTableRow.vue';
import TenantContextBanner from '@/Components/UI/TenantContextBanner.vue';
import ReportMetricSection from '@/Components/UI/ReportMetricSection.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import LogFilters from '@/Components/UI/LogFilters.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { useReportDrilldown } from '@/Composables/useReportDrilldown';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    days: { type: Number, default: 28 },
    month: { type: String, default: null },
    periodLabel: { type: String, default: '' },
    currency: String,
    hasMultipleCurrencies: { type: Boolean, default: false },
    currenciesInUse: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    filterOptions: { type: Object, default: () => ({}) },
    charts: Object,
    byBuyer: Object,
    bySupplier: Object,
    byCampaign: Object,
    bySid: Object,
    deliveryPerformance: Object,
    tierSummary: Object,
    distributionOutcome: Object,
    leadStatusBreakdown: Object,
    pingTreeCampaigns: { type: Array, default: () => [] },
    selectedCampaign: { type: Object, default: null },
    summary: Object,
});

const { formatMoney, formatNumber, formatMoneyMulti } = useMoneyFormat(props.currency);
const { leadsDrill, deliveryDrill, financeDrill, periodForLeads, periodForFinance } = useReportDrilldown(props);

const revenueByCurrency = computed(() => props.summary?.revenue_by_currency ?? []);
const financialDecimals = computed(() => (props.hasMultipleCurrencies ? 0 : 0));

const formatFinancial = (amount, field = 'revenue') => {
    if (props.hasMultipleCurrencies && field === 'revenue') {
        return formatMoneyMulti(revenueByCurrency.value, { decimals: 0, field: 'revenue' });
    }

    if (props.hasMultipleCurrencies && field === 'payout') {
        return formatMoneyMulti(revenueByCurrency.value, { decimals: 0, field: 'payout' });
    }

    if (props.hasMultipleCurrencies && field === 'margin') {
        return formatMoneyMulti(revenueByCurrency.value, { decimals: 0, field: 'margin' });
    }

    return formatMoney(amount, { decimals: financialDecimals.value });
};

const formatRowMoney = (amount, rowCurrency) => formatMoney(amount, {
    decimals: 0,
    currency: rowCurrency ?? props.currency,
});

const kpis = computed(() => props.summary?.kpis ?? {});
const delivery = computed(() => props.summary?.delivery ?? {});
const redirect = computed(() => props.summary?.redirect ?? {});
const quality = computed(() => props.summary?.quality ?? {});

const pct = (num, den) => (den > 0 ? Math.round((num / den) * 1000) / 10 : 0);

const kpisByCurrency = computed(() => props.summary?.kpis_by_currency ?? []);

const formatKpiMoneyMulti = (field) => {
    const rows = kpisByCurrency.value;
    if (!rows.length) {
        return '—';
    }

    return rows
        .map((row) => formatMoney(row[field], { decimals: 2, currency: row.currency }))
        .join(' · ');
};

const formatKpiPctMulti = (field) => {
    const rows = kpisByCurrency.value;
    if (!rows.length) {
        return '—';
    }

    return rows.map((row) => `${row.currency} ${row[field]}%`).join(' · ');
};

const payoutSharePct = computed(() => pct(props.summary?.payout_period ?? 0, props.summary?.revenue_period ?? 0));
const netPerLead = computed(() => {
    const leads = props.summary?.leads_period ?? 0;
    return leads > 0 ? (props.summary?.margin_period ?? 0) / leads : 0;
});
const quarantineRate = computed(() => pct(props.summary?.quarantined_period ?? 0, props.summary?.leads_period ?? 0));
const pingFailRate = computed(() => pct(delivery.value.rejections ?? 0, delivery.value.attempts ?? 0));

const volumeStrip = computed(() => [
    { label: `Leads (${props.periodLabel || props.days + 'd'})`, value: formatNumber(props.summary?.leads_period), href: leadsDrill(), title: 'All leads received in this period', accent: 'indigo' },
    { label: 'Sold', value: formatNumber(props.summary?.sold_period), href: leadsDrill({ status: 'sold' }), title: 'Leads sold to buyers in this period', accent: 'emerald' },
    { label: 'Unsold', value: formatNumber(props.summary?.unsold_period), href: leadsDrill({ status: 'unsold' }), title: 'Leads that completed distribution without a sale', accent: 'amber' },
    { label: 'Rejected', value: formatNumber(props.summary?.rejected_period), href: leadsDrill({ status: 'rejected' }), title: 'Leads rejected at validation or ingest', accent: 'rose' },
    { label: 'Quarantined', value: formatNumber(props.summary?.quarantined_period), href: leadsDrill({ status: 'quarantined' }), title: 'Leads held in quarantine during this period', accent: 'orange' },
    {
        label: props.hasMultipleCurrencies ? 'Revenue (by currency)' : 'Revenue',
        title: props.hasMultipleCurrencies
            ? 'Sold-lead revenue by currency — opens sold leads with financials'
            : 'Total buyer revenue from sold leads in this period',
        value: formatFinancial(props.summary?.revenue_period, 'revenue'),
        href: leadsDrill({ status: 'sold' }),
        accent: 'cyan',
    },
    {
        label: props.hasMultipleCurrencies ? 'Payout (by currency)' : 'Payout',
        title: 'Supplier payout totals — opens finance breakdown by buyer and supplier',
        value: formatFinancial(props.summary?.payout_period, 'payout'),
        href: financeDrill(),
        accent: 'amber',
    },
    {
        label: props.hasMultipleCurrencies ? 'Margin (by currency)' : 'Margin',
        title: 'Revenue minus payout — opens finance summary for this period',
        value: formatFinancial(props.summary?.margin_period, 'margin'),
        href: financeDrill(),
        accent: 'violet',
    },
]);

const economicsStrip = computed(() => {
    if (props.hasMultipleCurrencies) {
        return [
            { label: 'EPL (sold)', title: 'Revenue ÷ sold leads — view sold leads with revenue', value: formatKpiMoneyMulti('epl'), href: leadsDrill({ status: 'sold' }), accent: 'cyan' },
            { label: 'EPC (ingest)', title: 'Revenue ÷ leads received — view all leads in period', value: formatKpiMoneyMulti('epc'), href: leadsDrill(), accent: 'indigo' },
            { label: 'CPA (payout)', title: 'Payout ÷ sold leads — view sold leads with payout', value: formatKpiMoneyMulti('cpa'), href: leadsDrill({ status: 'sold' }), accent: 'amber' },
            { label: 'CPL (ingest)', title: 'Payout ÷ leads received — view all leads in period', value: formatKpiMoneyMulti('cpl'), href: leadsDrill(), accent: 'rose' },
            { label: 'MPL (margin)', title: 'Margin ÷ sold leads — view sold leads with margin', value: formatKpiMoneyMulti('mpl'), href: leadsDrill({ status: 'sold' }), accent: 'violet' },
            { label: 'Margin %', title: 'Margin ÷ revenue — opens finance summary', value: formatKpiPctMulti('margin_pct'), href: financeDrill(), accent: 'violet' },
            { label: 'Pay share', title: 'Payout ÷ revenue — opens finance summary', value: formatKpiPctMulti('payout_share_pct'), href: financeDrill(), accent: 'amber' },
            { label: 'Net / lead', title: 'Margin ÷ leads received — view all leads in period', value: formatKpiMoneyMulti('net_per_lead'), href: leadsDrill(), accent: 'violet' },
        ];
    }

    return [
        { label: 'EPL (sold)', title: 'Revenue ÷ sold leads — view sold leads with revenue', value: formatMoney(kpis.value.epl), href: leadsDrill({ status: 'sold' }), accent: 'cyan' },
        { label: 'EPC (ingest)', title: 'Revenue ÷ leads received — view all leads in period', value: formatMoney(kpis.value.epc), href: leadsDrill(), accent: 'indigo' },
        { label: 'CPA (payout)', title: 'Payout ÷ sold leads — view sold leads with payout', value: formatMoney(kpis.value.cpa), href: leadsDrill({ status: 'sold' }), accent: 'amber' },
        { label: 'CPL (ingest)', title: 'Payout ÷ leads received — view all leads in period', value: formatMoney(kpis.value.cpl), href: leadsDrill(), accent: 'rose' },
        { label: 'MPL (margin)', title: 'Margin ÷ sold leads — view sold leads with margin', value: formatMoney(kpis.value.mpl), href: leadsDrill({ status: 'sold' }), accent: 'violet' },
        { label: 'Margin %', title: 'Margin ÷ revenue — opens finance summary', value: `${kpis.value.margin_pct ?? 0}%`, href: financeDrill(), accent: 'violet' },
        { label: 'Pay share', title: 'Payout ÷ revenue — opens finance summary', value: `${payoutSharePct.value}%`, href: financeDrill(), accent: 'amber' },
        { label: 'Net / lead', title: 'Margin ÷ leads received — view all leads in period', value: formatMoney(netPerLead.value), href: leadsDrill(), accent: 'violet' },
    ];
});

const rateStrip = computed(() => [
    { label: 'Conversion', title: 'Sold ÷ received — view sold leads in this period', value: `${props.summary?.conversion ?? 0}%`, href: leadsDrill({ status: 'sold' }), accent: 'indigo' },
    { label: 'Sell-through', title: 'Sold ÷ (sold + unsold) — view sold leads', value: `${props.summary?.sell_through ?? 0}%`, href: leadsDrill({ status: 'sold' }), accent: 'emerald' },
    { label: 'Reject rate', title: 'Rejected ÷ received — view rejected leads', value: `${props.summary?.reject_rate ?? 0}%`, href: leadsDrill({ status: 'rejected' }), accent: 'rose' },
    { label: 'Quarantine', title: 'Quarantined ÷ received — view quarantined leads in period', value: `${quarantineRate.value}%`, href: leadsDrill({ status: 'quarantined' }), accent: 'orange' },
    { label: 'Avg quality', title: 'Mean lead quality score — view all scored leads', value: quality.value.avg_score ?? '—', href: leadsDrill(), accent: 'violet' },
    { label: 'Email pass', title: 'Email deliverability pass rate — view leads that passed email check', value: quality.value.email_checked ? `${quality.value.email_pass_rate}%` : '—', href: quality.value.email_checked ? leadsDrill({ validation: 'email_passed' }) : null, accent: 'cyan' },
    { label: 'HLR pass', title: 'Mobile HLR pass rate — view leads that passed HLR check', value: quality.value.hlr_checked ? `${quality.value.hlr_pass_rate}%` : '—', href: quality.value.hlr_checked ? leadsDrill({ validation: 'hlr_passed' }) : null, accent: 'indigo' },
    { label: 'Ping success', title: 'Successful buyer delivery attempts — view success logs', value: `${delivery.value.success_rate ?? 0}%`, href: deliveryDrill({ status: 'success' }), accent: 'emerald' },
    { label: 'Outbid rate', title: 'Outbid ÷ delivery attempts — view outbid logs', value: `${delivery.value.outbid_rate ?? 0}%`, href: deliveryDrill({ status: 'outbid' }), accent: 'amber' },
    { label: 'Ping fail', title: 'Failed delivery attempts — view failed ping/post logs', value: `${pingFailRate.value}%`, href: deliveryDrill({ status: 'failed' }), accent: 'rose' },
    { label: 'Redirect rate', title: 'Thank-you page clicks ÷ redirects offered — view sold leads that followed redirect', value: `${redirect.value.redirect_rate ?? 0}%`, href: leadsDrill({ status: 'sold', redirect: 'followed' }), accent: 'cyan' },
    { label: 'Avg latency', title: 'Mean delivery duration — view all delivery attempts', value: delivery.value.avg_duration_ms ? `${delivery.value.avg_duration_ms}ms` : '—', href: deliveryDrill(), accent: 'cyan' },
]);

const qualityStrip = computed(() => [
    { label: 'Avg score', value: quality.value.avg_score ?? '—', title: 'All leads with quality scores in this period', href: leadsDrill(), accent: 'violet' },
    { label: 'Excellent (80+)', value: formatNumber(quality.value.excellent ?? 0), title: 'Leads scoring 80 or above', href: leadsDrill({ quality_min: 80 }), accent: 'emerald' },
    { label: 'Good (60–79)', value: formatNumber(quality.value.good ?? 0), title: 'Leads scoring 60–79', href: leadsDrill({ quality_min: 60, quality_max: 79 }), accent: 'cyan' },
    { label: 'Fair (40–59)', value: formatNumber(quality.value.fair ?? 0), title: 'Leads scoring 40–59', href: leadsDrill({ quality_min: 40, quality_max: 59 }), accent: 'amber' },
    { label: 'Poor (<40)', value: formatNumber(quality.value.poor ?? 0), title: 'Leads scoring below 40', href: leadsDrill({ quality_max: 39 }), accent: 'rose' },
    { label: 'Email checked', value: formatNumber(quality.value.email_checked ?? 0), title: 'Leads with an email validation result', href: leadsDrill({ validation: 'email_checked' }), accent: 'indigo' },
    { label: 'Email failed', value: formatNumber(quality.value.email_failed ?? 0), title: 'Leads that failed email deliverability check', href: leadsDrill({ validation: 'email_failed' }), accent: 'rose' },
    { label: 'HLR checked', value: formatNumber(quality.value.hlr_checked ?? 0), title: 'Leads with a mobile HLR check', href: leadsDrill({ validation: 'hlr_checked' }), accent: 'indigo' },
    { label: 'HLR failed', value: formatNumber(quality.value.hlr_failed ?? 0), title: 'Leads that failed mobile reachability check', href: leadsDrill({ validation: 'hlr_failed' }), accent: 'rose' },
    { label: 'IP checked', value: formatNumber(quality.value.ip_checked ?? 0), title: 'Leads with an IP fraud check', href: leadsDrill({ validation: 'ip_checked' }), accent: 'indigo' },
    { label: 'IP failed', value: formatNumber(quality.value.ip_failed ?? 0), title: 'Leads that failed IP fraud check', href: leadsDrill({ validation: 'ip_failed' }), accent: 'rose' },
]);

const deliveryLogFilter = (params = {}) => deliveryDrill(params);

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

const leadStatusStrip = computed(() => Object.entries(props.leadStatusBreakdown ?? {}).map(([status, count]) => ({
    label: statusLabels[status] ?? status,
    value: formatNumber(count),
    title: `View ${statusLabels[status] ?? status} leads in this period`,
    href: leadsDrill({ status }),
})));

const deliveryOutcomeStrip = computed(() => Object.entries(props.distributionOutcome ?? {}).map(([status, count]) => ({
    label: status,
    value: formatNumber(count),
    title: `View ${status} delivery log entries in this period`,
    href: deliveryLogFilter({ status }),
})));

const methodLabels = { direct_post: 'Direct API', ping_post: 'Ping Post', store_lead: 'Store', email: 'Email', sms: 'SMS' };
const successRate = (row) => (!row.attempts ? '—' : `${Math.round((row.successes / row.attempts) * 100)}%`);
const winRate = (row) => (!row.attempts ? '—' : `${Math.round((row.wins / row.attempts) * 100)}%`);
const redirectRate = (row) => (!row.redirects_offered ? '—' : `${Math.round((row.redirects_followed / row.redirects_offered) * 100)}%`);
const conversionRate = (row) => (!row.received ? '—' : `${Math.round((row.sold / row.received) * 100)}%`);
const eplForRow = (row) => (!row.sold ? '—' : formatRowMoney(row.revenue / row.sold, row.currency));

const revenueDataset = computed(() => ([
    {
        label: 'Revenue',
        data: props.charts?.revenue ?? [],
        color: '#0891b2',
        colorTo: '#22d3ee',
        gradient: true,
    },
]));
</script>

<template>
    <Head title="Reports" />
    <AuthenticatedLayout>
        <PageHeader title="Reports" description="Volume, unit economics (EPL, EPC, CPA), campaign and affiliate performance, and ping-tree delivery analytics.">
            <template #actions>
                <AppButton :href="route('billing.index')" variant="secondary">Billing ledger</AppButton>
                <AppButton :href="route('finance.index')" variant="secondary">Finance</AppButton>
                <AppButton :href="route('distribution.index')" variant="secondary">Ping trees</AppButton>
            </template>
        </PageHeader>

        <TenantContextBanner />

        <LogFilters
            route-name="reports.index"
            :filters="filters"
            :show-days="true"
            :show-month="true"
            :show-date-range="true"
            :show-campaign="true"
            :show-currency="(filterOptions.currencies?.length ?? 0) > 1"
            :campaigns="filterOptions.campaigns ?? []"
            :currencies="filterOptions.currencies ?? []"
            class="mb-4"
        />

        <div
            v-if="hasMultipleCurrencies"
            class="mb-4 rounded-lg border border-amber-200/80 bg-amber-50/90 px-3 py-2 text-sm text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
        >
            <strong>Multiple currencies</strong> — totals and unit economics are shown <strong>per currency</strong> (not combined).
            Filter by <strong>currency</strong> or <strong>campaign</strong> for a single-currency view.
        </div>

        <ReportMetricSection
            title="Volume"
            :description="`Lead flow and financial totals for ${periodLabel || 'the selected period'}. Click a metric to drill down.`"
            :items="volumeStrip"
        />

        <ReportMetricSection
            title="Unit economics"
            description="Revenue and payout efficiency per lead. Click any metric to open the underlying leads or finance view for this period."
            :items="economicsStrip"
        />

        <ReportMetricSection
            title="Rates & delivery health"
            description="Conversion, rejection, and ping-post delivery quality. Click to open filtered leads or delivery logs."
            :items="rateStrip"
        />

        <ReportMetricSection
            title="Lead quality"
            description="Quality scores from email, HLR, and IP checks at ingest. Click to see the matching leads."
            :items="qualityStrip"
        />

        <div class="grid gap-6 lg:grid-cols-2">
            <Panel>
                <template #header>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-semibold text-slate-900 dark:text-white">Lead volume — {{ periodLabel || days + ' days' }}</h3>
                        <p class="text-xs text-slate-500">Hover points · click to open leads</p>
                    </div>
                </template>
                <LineChart
                    :labels="charts?.labels ?? []"
                    :dates="charts?.dates ?? []"
                    :datasets="[
                        { label: 'Received', data: charts?.leads ?? [], color: '#6366f1' },
                        { label: 'Sold', data: charts?.sold ?? [], color: '#10b981' },
                        { label: 'Rejected', data: charts?.rejected ?? [], color: '#f43f5e' },
                    ]"
                    :drilldown-route="route('leads.index')"
                    :drilldown-query="periodForLeads"
                />
            </Panel>
            <Panel>
                <template #header>
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white">Revenue — {{ periodLabel || days + ' days' }}</h3>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ hasMultipleCurrencies ? 'Per-currency totals — filter to one currency for a single chart' : currency }}
                            · sold lead revenue by day
                        </p>
                    </div>
                </template>
                <BarChart
                    :labels="charts?.labels ?? []"
                    :dates="charts?.dates ?? []"
                    :datasets="revenueDataset"
                    :height="220"
                    :scrollable="days > 14"
                    :value-formatter="(v) => formatMoney(v, { decimals: 0 })"
                    :drilldown-route="route('finance.index')"
                    :drilldown-query="periodForFinance"
                />
            </Panel>
        </div>

        <Panel class="mt-6">
            <template #header>
                <h3 class="font-semibold text-slate-900 dark:text-white">Payout &amp; margin — {{ periodLabel || days + ' days' }}</h3>
            </template>
            <LineChart
                :labels="charts?.labels ?? []"
                :dates="charts?.dates ?? []"
                :datasets="[
                    { label: `Payout (${currency})`, data: charts?.payout ?? [], color: '#f59e0b' },
                    { label: `Margin (${currency})`, data: charts?.margin ?? [], color: '#8b5cf6' },
                ]"
                :value-formatter="(v) => formatMoney(v, { decimals: 0 })"
                :drilldown-route="route('finance.index')"
                :drilldown-query="periodForFinance"
            />
        </Panel>

        <div v-if="leadStatusStrip.length" class="mt-6">
            <h2 class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500">Lead status breakdown</h2>
            <CompactStatStrip :items="leadStatusStrip" />
        </div>

        <div v-if="deliveryOutcomeStrip.length" class="mt-4">
            <h2 class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500">Delivery log outcomes</h2>
            <CompactStatStrip :items="deliveryOutcomeStrip" />
        </div>

        <Panel class="mt-6">
            <template #header>
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white">Campaign performance</h3>
                    <p class="mt-1 text-xs text-slate-500">Per-campaign volume, conversion, revenue, and EPL for the selected period.</p>
                </div>
            </template>
            <DataTable :empty="!byCampaign?.data?.length" empty-message="No campaign data for this period.">
                <template #head>
                    <th class="text-left">Campaign</th>
                    <th class="text-left">Received</th>
                    <th class="text-left">Sold</th>
                    <th class="text-left">Unsold</th>
                    <th class="text-left">Rejected</th>
                    <th class="text-left">Conv.</th>
                    <th class="text-left">Revenue</th>
                    <th class="text-left">Payout</th>
                    <th class="text-left">Margin</th>
                    <th class="text-left">EPL</th>
                </template>
                <ClickableTableRow
                    v-for="row in byCampaign.data"
                    :key="row.campaign_id"
                    :href="route('campaigns.show', row.campaign_id)"
                >
                    <td class="">
                        <span class="font-medium">{{ row.name }}</span>
                        <span v-if="row.reference" class="ml-1 text-xs text-slate-400">{{ row.reference }}</span>
                    </td>
                    <td class="text-xs text-slate-500">{{ row.currency }}</td>
                    <td class="">{{ row.received }}</td>
                    <td class="text-emerald-600">{{ row.sold }}</td>
                    <td class="text-amber-600">{{ row.unsold }}</td>
                    <td class="text-rose-600">{{ row.rejected }}</td>
                    <td class="">{{ conversionRate(row) }}</td>
                    <td class="">{{ formatRowMoney(row.revenue, row.currency) }}</td>
                    <td class="">{{ formatRowMoney(row.payout, row.currency) }}</td>
                    <td class="">{{ formatRowMoney(row.margin, row.currency) }}</td>
                    <td class="font-medium text-cyan-600">{{ eplForRow(row) }}</td>
                </ClickableTableRow>
            </DataTable>
            <Pagination :links="byCampaign.links" />
        </Panel>

        <Panel class="mt-6">
            <template #header>
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white">Affiliate / SID performance</h3>
                    <p class="mt-1 text-xs text-slate-500">Source ID (SID) breakdown with EPL and conversion for affiliate traffic analysis.</p>
                </div>
            </template>
            <DataTable :empty="!bySid?.data?.length" empty-message="No SID data — leads need sid on ingest.">
                <template #head>
                    <th class="text-left">SID</th>
                    <th class="text-left">Supplier</th>
                    <th class="text-left">Received</th>
                    <th class="text-left">Sold</th>
                    <th class="text-left">Rejected</th>
                    <th class="text-left">Conv.</th>
                    <th class="text-left">Revenue</th>
                    <th class="text-left">Payout</th>
                    <th class="text-left">EPL</th>
                </template>
                <ClickableTableRow
                    v-for="row in bySid.data"
                    :key="`${row.sid}-${row.supplier_id}`"
                    :href="row.supplier_id ? route('suppliers.show', row.supplier_id) : route('leads.index')"
                >
                    <td class="font-mono text-xs font-medium">{{ row.sid }}</td>
                    <td class="text-xs text-slate-600 dark:text-slate-400">{{ row.supplier_name ?? '—' }}</td>
                    <td class="">{{ row.received }}</td>
                    <td class="text-emerald-600">{{ row.sold }}</td>
                    <td class="text-rose-600">{{ row.rejected }}</td>
                    <td class="">{{ conversionRate(row) }}</td>
                    <td class="">{{ formatMoney(row.revenue) }}</td>
                    <td class="">{{ formatMoney(row.payout) }}</td>
                    <td class="font-medium text-cyan-600">{{ eplForRow(row) }}</td>
                </ClickableTableRow>
            </DataTable>
            <Pagination :links="bySid.links" />
        </Panel>

        <Panel class="mt-6">
            <template #header>
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white">Ping tree tier summary</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Each <strong>tier</strong> is a step in the ping tree — buyers are pinged in tier order.
                        <template v-if="selectedCampaign">
                            Showing delivery logs for <strong>{{ selectedCampaign.name }}</strong> only.
                        </template>
                        <template v-else-if="pingTreeCampaigns?.length">
                            Aggregated across all ping-tree campaigns — filter by <strong>campaign</strong> above to scope tier stats.
                        </template>
                    </p>
                    <div v-if="pingTreeCampaigns?.length" class="mt-2 flex flex-wrap gap-2">
                        <Link
                            v-for="c in pingTreeCampaigns"
                            :key="c.id"
                            :href="route('campaigns.show', c.id)"
                            class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700 transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-600 dark:hover:bg-indigo-950/40 dark:hover:text-indigo-300"
                            :class="selectedCampaign?.id === c.id ? 'border-indigo-400 bg-indigo-50 text-indigo-800 dark:border-indigo-500 dark:bg-indigo-950/50 dark:text-indigo-200' : ''"
                        >
                            {{ c.name }}
                            <span class="text-slate-400">· {{ c.tier_count }} tiers</span>
                        </Link>
                    </div>
                </div>
            </template>
            <DataTable :empty="!tierSummary?.data?.length" empty-message="No tier data — run php artisan db:seed to populate demo history.">
                <template #head>
                    <th class="text-left">Tier</th>
                    <th class="text-left">Attempts</th>
                    <th class="text-left">Wins</th>
                    <th class="text-left">Outbid</th>
                    <th class="text-left">Rejections</th>
                    <th class="text-left">Win rate</th>
                    <th class="text-left">Redirects</th>
                    <th class="text-left">Redirect rate</th>
                    <th class="text-left">Revenue</th>
                </template>
                <ClickableTableRow
                    v-for="row in tierSummary.data"
                    :key="row.tier"
                    :href="deliveryLogFilter({ tier: row.tier })"
                >
                    <td class="font-medium">Tier {{ row.tier }}</td>
                    <td class="">{{ row.attempts }}</td>
                    <td class="text-emerald-600">{{ row.wins }}</td>
                    <td class="text-amber-600">{{ row.outbid }}</td>
                    <td class="text-rose-600">{{ row.rejections }}</td>
                    <td class="">{{ winRate(row) }}</td>
                    <td class="text-xs text-slate-500">{{ row.redirects_followed ?? 0 }} / {{ row.redirects_offered ?? 0 }}</td>
                    <td class="text-cyan-600">{{ redirectRate(row) }}</td>
                    <td class="">{{ formatMoney(row.revenue) }}</td>
                </ClickableTableRow>
            </DataTable>
            <Pagination :links="tierSummary.links" />
        </Panel>

        <Panel class="mt-6" :padding="false">
            <template #header>
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white">Delivery performance by buyer route</h3>
                    <p class="mt-1 text-xs text-slate-500">Each row is a buyer delivery endpoint. Click to view individual post/ping attempts in delivery logs.</p>
                </div>
            </template>
            <DataTable :empty="!deliveryPerformance?.data?.length">
                <template #head>
                    <th class="text-left">Delivery</th>
                    <th class="text-left">Buyer</th>
                    <th class="text-left">Tier</th>
                    <th class="text-left">Method</th>
                    <th class="text-left">Attempts</th>
                    <th class="text-left">Success</th>
                    <th class="text-left">Outbid</th>
                    <th class="text-left">Rate</th>
                    <th class="text-left">Redirects</th>
                    <th class="text-left">Redirect rate</th>
                    <th class="text-left">Avg ms</th>
                    <th class="text-left">Revenue</th>
                </template>
                <ClickableTableRow
                    v-for="row in deliveryPerformance.data"
                    :key="row.delivery_id"
                    :href="deliveryLogFilter({ delivery_id: row.delivery_id })"
                >
                    <td class="">
                        <Link :href="route('deliveries.show', row.delivery_id)" class="font-medium text-indigo-600 hover:underline" @click.stop>{{ row.name }}</Link>
                    </td>
                    <td class="text-xs text-slate-600 dark:text-slate-400">{{ row.buyer_name ?? '—' }}</td>
                    <td class="">{{ row.tier ?? '—' }}</td>
                    <td class="text-xs capitalize text-slate-500">{{ methodLabels[row.method] ?? row.method }}</td>
                    <td class="">{{ row.attempts }}</td>
                    <td class="text-emerald-600">{{ row.successes }}</td>
                    <td class="text-amber-600">{{ row.outbid }}</td>
                    <td class="">{{ successRate(row) }}</td>
                    <td class="text-xs text-slate-500">{{ row.redirects_followed ?? 0 }} / {{ row.redirects_offered ?? 0 }}</td>
                    <td class="text-cyan-600">{{ redirectRate(row) }}</td>
                    <td class="text-xs text-slate-500">{{ row.avg_duration_ms ? Math.round(row.avg_duration_ms) : '—' }}</td>
                    <td class="">{{ formatMoney(row.revenue) }}</td>
                </ClickableTableRow>
            </DataTable>
            <Pagination :links="deliveryPerformance.links" />
        </Panel>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <Panel title="Top buyers (period)" :padding="false">
                <DataTable :empty="!byBuyer?.data?.length">
                    <template #head>
                        <th class="text-left">Buyer</th>
                        <th class="text-left">Leads</th>
                        <th class="text-left">Redirects</th>
                        <th class="text-left">Redirect rate</th>
                        <th class="text-left">Revenue</th>
                        <th class="text-left">Margin</th>
                        <th class="text-left">EPL</th>
                    </template>
                    <ClickableTableRow
                        v-for="row in byBuyer.data"
                        :key="row.buyer_id"
                        :href="route('billing.show', row.buyer_id)"
                    >
                        <td class="font-medium">{{ row.name }}</td>
                        <td class="">{{ row.leads }}</td>
                        <td class="text-xs text-slate-500">{{ row.redirects_followed ?? 0 }} / {{ row.redirects_offered ?? 0 }}</td>
                        <td class="text-cyan-600">{{ redirectRate(row) }}</td>
                        <td class="text-emerald-600">{{ formatMoney(row.revenue) }}</td>
                        <td class="text-violet-600">{{ formatMoney(row.margin) }}</td>
                        <td class="font-medium text-cyan-600">{{ eplForRow({ sold: row.leads, revenue: row.revenue }) }}</td>
                    </ClickableTableRow>
                </DataTable>
                <Pagination :links="byBuyer.links" />
            </Panel>
            <Panel title="Top suppliers (period)" :padding="false">
                <DataTable :empty="!bySupplier?.data?.length">
                    <template #head>
                        <th class="text-left">Supplier</th>
                        <th class="text-left">Leads</th>
                        <th class="text-left">Payout</th>
                        <th class="text-left">Revenue</th>
                        <th class="text-left">CPA</th>
                    </template>
                    <ClickableTableRow
                        v-for="row in bySupplier.data"
                        :key="row.supplier_id"
                        :href="route('suppliers.show', row.supplier_id)"
                    >
                        <td class="font-medium">{{ row.name }}</td>
                        <td class="">{{ row.leads }}</td>
                        <td class="text-amber-600">{{ formatMoney(row.payout) }}</td>
                        <td class="text-emerald-600">{{ formatMoney(row.revenue) }}</td>
                        <td class="font-medium text-amber-600">{{ row.leads ? formatMoney(row.payout / row.leads) : '—' }}</td>
                    </ClickableTableRow>
                </DataTable>
                <Pagination :links="bySupplier.links" />
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
