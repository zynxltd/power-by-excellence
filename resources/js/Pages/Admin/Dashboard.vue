<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import BarChart from '@/Components/UI/BarChart.vue';
import LineChart from '@/Components/UI/LineChart.vue';
import DonutChart from '@/Components/UI/DonutChart.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import ClickableTableRow from '@/Components/UI/ClickableTableRow.vue';
import TenantContextBanner from '@/Components/UI/TenantContextBanner.vue';
import QuickLinkChips from '@/Components/UI/QuickLinkChips.vue';
import HorizontalSwipeScroll from '@/Components/UI/HorizontalSwipeScroll.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, inject, ref, watch } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { useLiveStats } from '@/Composables/useLiveStats';

const page = usePage();
const isSuperAdmin = computed(() => page.props.auth.isSuperAdmin);

const props = defineProps({
    stats: Object,
    recentLeads: Object,
    charts: Object,
    chartDays: { type: Number, default: 7 },
    currency: { type: String, default: 'GBP' },
    tenantOverview: Array,
    showTenantColumn: Boolean,
    quickLinkGroups: Array,
});

const tenantView = ref('cards');
const chartDaysLocal = ref(props.chartDays);
const { formatMoney } = useMoneyFormat(props.currency);
const { stats: liveStats } = useLiveStats();
const isNavigating = inject('isNavigating', ref(false));

watch(() => props.chartDays, (days) => {
    chartDaysLocal.value = days;
});

const stats = computed(() => ({
    ...props.stats,
    ...(liveStats.value ?? {}),
}));

const applyChartDays = (d) => {
    chartDaysLocal.value = d;
    router.get(route('dashboard'), { chart_days: d }, { preserveState: true, replace: true });
};

const chartDatasets = computed(() => [
    { label: 'Received', data: props.charts?.leads ?? [], color: '#6366f1', colorTo: '#818cf8', gradient: true },
    { label: 'Sold', data: props.charts?.sold ?? [], color: '#059669', colorTo: '#34d399', gradient: true },
]);

const revenueDataset = computed(() => ([
    {
        label: 'Revenue',
        data: props.charts?.revenue ?? [],
        color: '#0891b2',
        colorTo: '#22d3ee',
        gradient: true,
    },
]));

const statLinks = computed(() => [
    { label: 'Leads Today', value: stats.value.leads_today, href: route('leads.index'), accent: 'indigo' },
    { label: 'Sold Today', value: stats.value.sold_today, href: route('leads.index', { status: 'sold' }), accent: 'emerald' },
    { label: 'Unsold Today', value: stats.value.unsold_today, href: route('leads.index', { status: 'unsold' }), accent: 'amber' },
    { label: 'Revenue Today', value: formatMoney(stats.value.revenue_today), href: route('billing.index'), accent: 'cyan' },
    { label: 'Reject Rate', value: stats.value.reject_rate + '%', href: route('leads.index', { status: 'rejected' }), accent: 'rose' },
    { label: 'Quarantined', value: stats.value.quarantined, href: route('leads.index', { status: 'quarantined' }), accent: 'amber' },
    { label: 'Pending', value: stats.value.pending, href: route('operations.index'), accent: 'indigo' },
]);

const switchToTenant = (accountId) => {
    router.post(route('accounts.switch'), { account_id: accountId }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <div class="dashboard-hero relative mb-6 overflow-hidden rounded-2xl border border-indigo-200/50 bg-gradient-to-br from-slate-900 via-indigo-950 to-violet-950 p-6 dark:border-indigo-500/20 sm:p-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Platform Overview</h1>
                    <p class="mt-1 text-sm text-indigo-200/80">
                        {{ $page.props.auth.account ? `Managing ${$page.props.auth.account.display_name}` : 'Super admin — all partner platforms' }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <AppButton v-if="isSuperAdmin" :href="route('accounts.index')" variant="secondary">Platforms</AppButton>
                    <AppButton v-else :href="route('settings.edit')" variant="secondary">Platform settings</AppButton>
                    <AppButton :href="route('operations.index')" variant="secondary">Live ops</AppButton>
                    <AppButton :href="route('campaigns.create')">New campaign</AppButton>
                </div>
            </div>
        </div>

        <TenantContextBanner />

        <Panel v-if="tenantOverview?.length" title="Partner platforms" class="mb-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600 dark:text-slate-400">Switch tenant to manage buyers, suppliers, and scoped settings.</p>
                <div class="flex rounded-lg border border-slate-200 p-0.5 dark:border-slate-700">
                    <button
                        type="button"
                        :class="['rounded-md px-3 py-1.5 text-xs font-semibold transition', tenantView === 'cards' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800']"
                        @click="tenantView = 'cards'"
                    >
                        Cards
                    </button>
                    <button
                        type="button"
                        :class="['rounded-md px-3 py-1.5 text-xs font-semibold transition', tenantView === 'table' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800']"
                        @click="tenantView = 'table'"
                    >
                        Table
                    </button>
                </div>
            </div>

            <HorizontalSwipeScroll v-if="tenantView === 'cards'">
                    <div
                        v-for="tenant in tenantOverview"
                        :key="tenant.id"
                        :class="[
                            'w-72 shrink-0 snap-start rounded-xl border p-4 transition',
                            tenant.is_active
                                ? 'border-indigo-300 bg-indigo-50/50 ring-1 ring-indigo-200 dark:border-indigo-600 dark:bg-indigo-950/30'
                                : 'border-slate-200 bg-white hover:border-indigo-200 dark:border-slate-800 dark:bg-slate-900',
                        ]"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold text-slate-900 dark:text-white">{{ tenant.name }}</p>
                                <p class="text-xs text-slate-500">{{ tenant.slug }}</p>
                            </div>
                            <span
                                v-if="tenant.is_active"
                                class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
                            >
                                Active
                            </span>
                        </div>
                        <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                            <div><dt class="text-slate-500">Campaigns</dt><dd class="font-semibold">{{ tenant.campaigns_count }}</dd></div>
                            <div><dt class="text-slate-500">Leads today</dt><dd class="font-semibold">{{ tenant.leads_today }}</dd></div>
                            <div><dt class="text-slate-500">Buyers</dt><dd class="font-semibold">{{ tenant.buyers_count }}</dd></div>
                            <div><dt class="text-slate-500">Suppliers</dt><dd class="font-semibold">{{ tenant.suppliers_count }}</dd></div>
                        </dl>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                v-if="!tenant.is_active"
                                type="button"
                                class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500"
                                @click="switchToTenant(tenant.id)"
                            >
                                Switch to tenant
                            </button>
                            <Link
                                :href="route('campaigns.index')"
                                class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                            >
                                Campaigns
                            </Link>
                            <Link
                                :href="route('buyers.index')"
                                class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                            >
                                Buyers
                            </Link>
                        </div>
                    </div>
            </HorizontalSwipeScroll>

            <DataTable v-else :empty="!tenantOverview?.length" :loading="isNavigating">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Platform</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaigns</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Leads today</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Buyers</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Suppliers</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                </template>
                <tr
                    v-for="tenant in tenantOverview"
                    :key="tenant.id"
                    :class="tenant.is_active ? 'bg-indigo-50/50 dark:bg-indigo-950/20' : ''"
                >
                    <td class="px-6 py-4">
                        <p class="font-medium text-slate-900 dark:text-white">{{ tenant.name }}</p>
                        <p class="text-xs text-slate-500">{{ tenant.slug }}</p>
                    </td>
                    <td class="px-6 py-4 text-slate-600">{{ tenant.campaigns_count }}</td>
                    <td class="px-6 py-4 text-slate-600">{{ tenant.leads_today }}</td>
                    <td class="px-6 py-4 text-slate-600">{{ tenant.buyers_count }}</td>
                    <td class="px-6 py-4 text-slate-600">{{ tenant.suppliers_count }}</td>
                    <td class="px-6 py-4 text-right">
                        <button
                            v-if="!tenant.is_active"
                            type="button"
                            class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500"
                            @click="switchToTenant(tenant.id)"
                        >
                            Switch
                        </button>
                        <span v-else class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">Active</span>
                    </td>
                </tr>
            </DataTable>
        </Panel>

        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
            <Link
                v-for="card in statLinks"
                :key="card.label"
                :href="card.href"
                class="rounded-xl border border-slate-200 bg-white px-4 py-3 transition hover:border-indigo-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-700"
            >
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ card.label }}</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ card.value }}</p>
            </Link>
        </div>

        <div class="mb-6 grid gap-6 lg:grid-cols-2">
            <Panel
                v-for="group in quickLinkGroups"
                :key="group.title"
                :title="group.title"
            >
                <QuickLinkChips :links="group.links" />
            </Panel>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <Panel class="lg:col-span-2">
                <template #header>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="font-semibold text-slate-900 dark:text-white">Lead volume — {{ chartDaysLocal }} days</h3>
                        <div class="flex rounded-lg border border-slate-200 p-0.5 dark:border-slate-700">
                            <button
                                v-for="d in [7, 14, 30]"
                                :key="d"
                                type="button"
                                :class="['rounded-md px-2.5 py-1 text-xs font-semibold transition', chartDaysLocal === d ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800']"
                                @click="applyChartDays(d)"
                            >
                                {{ d }}d
                            </button>
                        </div>
                    </div>
                </template>
                <LineChart
                    :labels="charts.labels"
                    :datasets="chartDatasets"
                    :height="280"
                    :value-formatter="(v) => v"
                    :drilldown-route="route('leads.index')"
                />
            </Panel>
            <Panel>
                <template #header>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="font-semibold text-slate-900 dark:text-white">Status breakdown — {{ chartDaysLocal }} days</h3>
                    </div>
                </template>
                <DonutChart :items="charts.status_breakdown" :drilldown-route="route('leads.index')" :period-days="chartDaysLocal" />
            </Panel>
        </div>

        <Panel class="mt-6">
            <template #header>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white">Revenue — {{ chartDaysLocal }} days</h3>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ currency }} · sold lead revenue by day</p>
                    </div>
                    <div class="flex rounded-lg border border-slate-200 p-0.5 dark:border-slate-700">
                        <button
                            v-for="d in [7, 14, 30]"
                            :key="`rev-${d}`"
                            type="button"
                            :class="['rounded-md px-2.5 py-1 text-xs font-semibold transition', chartDaysLocal === d ? 'bg-cyan-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800']"
                            @click="applyChartDays(d)"
                        >
                            {{ d }}d
                        </button>
                    </div>
                </div>
            </template>
            <BarChart
                :labels="charts.labels"
                :datasets="revenueDataset"
                :height="280"
                :scrollable="chartDaysLocal > 14"
                :value-formatter="(v) => formatMoney(v, { decimals: 0 })"
                :drilldown-route="route('billing.index')"
            />
        </Panel>

        <Panel title="Recent Leads" class="mt-6" :padding="false">
            <DataTable :empty="!recentLeads?.data?.length" empty-message="No leads yet. Submit via API or CSV import." :loading="isNavigating">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">UUID</th>
                    <th v-if="showTenantColumn" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Platform</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Revenue</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Received</th>
                </template>
                <ClickableTableRow v-for="lead in recentLeads.data" :key="lead.id" :href="route('leads.show', lead.id)">
                    <td class="whitespace-nowrap px-6 py-4 font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ lead.uuid?.slice(0, 8) }}…</td>
                    <td v-if="showTenantColumn" class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                        {{ lead.account?.brand_name || lead.account?.name || '—' }}
                    </td>
                    <td class="px-6 py-4 text-slate-900 dark:text-white">{{ lead.campaign?.name }}</td>
                    <td class="px-6 py-4"><StatusBadge :status="lead.status" /></td>
                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ formatMoney(lead.financials?.revenue ?? 0) }}</td>
                    <td class="px-6 py-4"><FormattedDate :value="lead.received_at" format="relative" /></td>
                </ClickableTableRow>
            </DataTable>
            <Pagination :links="recentLeads.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
