<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import LineChart from '@/Components/UI/LineChart.vue';
import DonutChart from '@/Components/UI/DonutChart.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import ClickableTableRow from '@/Components/UI/ClickableTableRow.vue';
import TenantContextBanner from '@/Components/UI/TenantContextBanner.vue';
import QuickLinkChips from '@/Components/UI/QuickLinkChips.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, inject, ref, watch } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { useLiveStats } from '@/Composables/useLiveStats';

const page = usePage();
const isSuperAdmin = computed(() => page.props.auth.isSuperAdmin);
const canCreateCampaign = computed(() => Boolean(page.props.auth.account));

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
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Platform Overview</h1>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    {{ $page.props.auth.account ? `Managing ${$page.props.auth.account.display_name}` : 'Super admin — all partner platforms' }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <AppButton v-if="isSuperAdmin" :href="route('accounts.index')" variant="secondary">Platforms</AppButton>
                <AppButton v-else :href="route('settings.edit')" variant="secondary">Platform settings</AppButton>
                <AppButton :href="route('operations.index')" variant="secondary">Live ops</AppButton>
                <AppButton v-if="canCreateCampaign" :href="route('campaigns.create')">New campaign</AppButton>
            </div>
        </div>

        <TenantContextBanner />

        <Panel v-if="tenantOverview?.length" title="Partner platforms" class="mb-4" :padding="false">
            <DataTable :empty="!tenantOverview?.length" :loading="isNavigating">
                <template #head>
                    <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">Platform</th>
                    <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">Campaigns</th>
                    <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">Leads today</th>
                    <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">Buyers</th>
                    <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">Suppliers</th>
                    <th class="px-3 py-2 text-right text-[10px] font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                </template>
                <tr
                    v-for="tenant in tenantOverview"
                    :key="tenant.id"
                    :class="tenant.is_active ? 'bg-indigo-50/50 dark:bg-indigo-950/20' : ''"
                >
                    <td class="px-3 py-2">
                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ tenant.name }}</p>
                        <p class="text-[10px] text-slate-500">{{ tenant.slug }}</p>
                    </td>
                    <td class="px-3 py-2 text-sm text-slate-600">{{ tenant.campaigns_count }}</td>
                    <td class="px-3 py-2 text-sm text-slate-600">{{ tenant.leads_today }}</td>
                    <td class="px-3 py-2 text-sm text-slate-600">{{ tenant.buyers_count }}</td>
                    <td class="px-3 py-2 text-sm text-slate-600">{{ tenant.suppliers_count }}</td>
                    <td class="px-3 py-2 text-right">
                        <div class="flex flex-wrap justify-end gap-1">
                            <button
                                v-if="!tenant.is_active"
                                type="button"
                                class="rounded-md bg-indigo-600 px-2 py-1 text-[10px] font-semibold text-white hover:bg-indigo-500"
                                @click="switchToTenant(tenant.id)"
                            >
                                Switch
                            </button>
                            <span v-else class="text-[10px] font-semibold text-indigo-600 dark:text-indigo-400">Active</span>
                            <Link :href="route('campaigns.index')" class="rounded-md border border-slate-200 px-2 py-1 text-[10px] font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400">Campaigns</Link>
                        </div>
                    </td>
                </tr>
            </DataTable>
        </Panel>

        <CompactStatStrip :items="statLinks" :columns="7" class="mb-4" />

        <div class="mb-4 grid grid-cols-1 items-stretch gap-4 lg:grid-cols-12">
            <Panel
                v-for="group in quickLinkGroups"
                :key="group.title"
                :title="group.title"
                class="lg:col-span-6"
            >
                <QuickLinkChips :links="group.links" />
            </Panel>
        </div>

        <div class="grid grid-cols-1 items-stretch gap-4 lg:grid-cols-12">
            <Panel class="lg:col-span-8">
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
                    :height="220"
                    :value-formatter="(v) => v"
                    :drilldown-route="route('leads.index')"
                />
            </Panel>
            <Panel class="lg:col-span-4">
                <template #header>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="font-semibold text-slate-900 dark:text-white">Status breakdown — {{ chartDaysLocal }} days</h3>
                    </div>
                </template>
                <DonutChart :items="charts.status_breakdown" :drilldown-route="route('leads.index')" :period-days="chartDaysLocal" />
            </Panel>
        </div>

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
