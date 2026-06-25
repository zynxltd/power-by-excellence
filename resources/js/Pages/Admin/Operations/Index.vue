<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import ClickableTableRow from '@/Components/UI/ClickableTableRow.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import BarChart from '@/Components/UI/BarChart.vue';
import CampaignWorkflowNav from '@/Components/UI/CampaignWorkflowNav.vue';
import TenantContextBanner from '@/Components/UI/TenantContextBanner.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, inject, ref } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { useLiveStats } from '@/Composables/useLiveStats';

const props = defineProps({
    stats: Object,
    queueBreakdown: Object,
    hourlyLeads: Array,
    topCampaigns: Array,
    recentLeads: Object,
    deliveryPreview: Object,
    campaignWorkflow: { type: Object, default: null },
    filters: { type: Object, default: () => ({}) },
});

const hourlyChart = computed(() => ({
    labels: props.hourlyLeads?.map((h) => h.label) ?? [],
    data: props.hourlyLeads?.map((h) => h.count) ?? [],
}));

const { formatMoney } = useMoneyFormat();
const { stats: liveStats, intervalSeconds } = useLiveStats();
const isNavigating = inject('isNavigating', ref(false));

const stats = computed(() => ({
    ...props.stats,
    ...(liveStats.value ?? {}),
}));

const queueItems = computed(() => [
    { key: 'pending', label: 'Pending', href: route('leads.index', { status: 'pending' }) },
    { key: 'processing', label: 'Processing', href: route('leads.index', { status: 'processing' }), live: true },
    { key: 'accepted', label: 'Accepted', href: route('leads.index', { status: 'accepted' }) },
    { key: 'quarantined', label: 'Quarantined', href: route('quarantine.index') },
]);

const queueBreakdown = computed(() => ({
    ...props.queueBreakdown,
    ...(liveStats.value?.queue_breakdown ?? {}),
}));

const processingCount = computed(() => liveStats.value?.processing_count ?? queueBreakdown.value?.processing ?? 0);
const processingLeads = computed(() => liveStats.value?.processing_leads ?? []);

const opsStatStrip = computed(() => [
    { label: 'Leads today', value: stats.value.leads_today, href: route('leads.index'), accent: 'indigo' },
    { label: 'Sold', value: stats.value.sold_today, href: route('leads.index', { status: 'sold' }), accent: 'emerald' },
    { label: 'Unsold', value: stats.value.unsold_today, href: route('leads.index', { status: 'unsold' }), accent: 'amber' },
    { label: 'Rejected', value: stats.value.rejected_today, href: route('leads.index', { status: 'rejected' }), accent: 'rose' },
    { label: 'In queue', value: stats.value.pending, href: route('operations.index'), accent: 'orange' },
    { label: 'Quarantine', value: stats.value.quarantined, href: route('quarantine.index'), accent: 'rose' },
    { label: 'Ping-posts', value: stats.value.ping_posts_today, href: route('logs.delivery', { method: 'ping-post' }), accent: 'cyan' },
    { label: 'Revenue', value: formatMoney(stats.value.revenue_today, { decimals: 0 }), href: route('finance.index'), accent: 'emerald' },
]);

const topCampaignStrip = computed(() => (props.topCampaigns ?? []).map((c) => ({
    label: c.name,
    value: `${c.leads}/${c.sold}`,
    href: route('leads.index', { campaign_id: c.id }),
    title: `${c.name} — ${c.leads} leads, ${c.sold} sold`,
})));
</script>

<template>
    <Head title="Live Operations" />
    <AuthenticatedLayout>
        <PageHeader title="Live Operations" description="Real-time queue, hourly ingest, and delivery activity. Click any row to drill down.">
            <template #actions>
                <AppButton :href="route('finance.index')" variant="secondary">Finance</AppButton>
                <AppButton :href="route('reports.index')" variant="secondary">Reports</AppButton>
                <AppButton :href="route('logs.delivery')" variant="secondary">Delivery Logs</AppButton>
                <AppButton :href="route('leads.index')">Lead Pipeline</AppButton>
            </template>
        </PageHeader>

        <TenantContextBanner />

        <CampaignWorkflowNav
            v-if="campaignWorkflow"
            :campaign="campaignWorkflow.campaign"
            :distribution-config-id="campaignWorkflow.distributionConfigId"
            current="operations"
            class="mb-6"
        />

        <CompactStatStrip :items="opsStatStrip" :columns="8" class="mb-6" />

        <Panel v-if="topCampaignStrip.length" title="Top campaigns today" class="mt-6">
            <CompactStatStrip :items="topCampaignStrip" :columns="topCampaignStrip.length" />
        </Panel>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <Panel title="Queue breakdown" class="lg:col-span-1">
                <div class="space-y-2">
                    <Link
                        v-for="item in queueItems"
                        :key="item.key"
                        :href="item.href"
                        class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 transition hover:border-indigo-300 hover:bg-indigo-50 dark:border-slate-700 dark:hover:bg-indigo-950/30"
                    >
                        <span class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ item.label }}
                            <span
                                v-if="item.key === 'processing' && processingCount > 0"
                                class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-violet-700 dark:bg-violet-900/40 dark:text-violet-300"
                            >
                                <span class="relative flex h-1.5 w-1.5">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-violet-400 opacity-75" />
                                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-violet-500" />
                                </span>
                                Live
                            </span>
                        </span>
                        <span class="text-lg font-bold text-slate-900 dark:text-white">{{ queueBreakdown?.[item.key] ?? 0 }}</span>
                    </Link>
                </div>
            </Panel>
            <Panel v-if="processingLeads.length" title="Processing now" class="lg:col-span-3">
                <p class="mb-3 text-sm text-slate-600 dark:text-slate-400">Leads actively moving through the pipeline — updates every {{ intervalSeconds }}s.</p>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="lead in processingLeads"
                        :key="lead.id"
                        :href="route('leads.show', lead.id)"
                        class="flex items-center justify-between rounded-xl border border-violet-200 bg-violet-50/50 px-3 py-2 transition hover:border-violet-400 dark:border-violet-800 dark:bg-violet-950/30"
                    >
                        <div>
                            <p class="font-mono text-xs text-violet-700 dark:text-violet-300">{{ lead.uuid?.slice(0, 10) }}…</p>
                            <p class="text-xs text-slate-500">{{ lead.campaign ?? 'Campaign' }}</p>
                        </div>
                        <StatusBadge status="processing" />
                    </Link>
                </div>
            </Panel>
            <Panel title="Leads received — last 24 hours" class="lg:col-span-2">
                <BarChart
                    :labels="hourlyChart.labels"
                    :datasets="[{ label: 'Leads', data: hourlyChart.data, color: '#6366f1' }]"
                    :height="140"
                />
            </Panel>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <Panel title="Recent Leads" :padding="false">
                <DataTable :empty="!recentLeads?.data?.length" :loading="isNavigating">
                    <template #head>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">UUID</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Supplier</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Time</th>
                    </template>
                    <ClickableTableRow v-for="lead in recentLeads.data" :key="lead.id" :href="route('leads.show', lead.id)">
                        <td class="px-6 py-4">
                            <span class="font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ lead.uuid?.slice(0, 8) }}…</span>
                        </td>
                        <td class="px-6 py-4"><StatusBadge :status="lead.status" /></td>
                        <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                            <Link v-if="lead.campaign_id" :href="route('campaigns.show', lead.campaign_id)" class="hover:text-indigo-600" @click.stop>{{ lead.campaign }}</Link>
                            <span v-else>—</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-500">{{ lead.supplier ?? '—' }}</td>
                        <td class="px-6 py-4"><FormattedDate :value="lead.received_at" format="relative" /></td>
                    </ClickableTableRow>
                </DataTable>
                <Pagination :links="recentLeads.links" />
            </Panel>

            <Panel :padding="false">
                <template #header>
                    <div class="flex w-full items-center justify-between">
                        <h3 class="font-semibold text-slate-900 dark:text-white">Latest Deliveries</h3>
                        <Link :href="route('logs.delivery')" class="text-sm font-semibold text-indigo-600 hover:underline">View all →</Link>
                    </div>
                </template>
                <DataTable :empty="!deliveryPreview?.data?.length" :loading="isNavigating">
                    <template #head>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Delivery</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Buyer</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tier</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Time</th>
                    </template>
                    <ClickableTableRow v-for="log in deliveryPreview.data" :key="log.id" :href="route('logs.delivery.show', log.id)">
                        <td class="px-6 py-4 text-sm text-slate-900 dark:text-white">
                            <Link v-if="log.delivery_id" :href="route('deliveries.show', log.delivery_id)" class="hover:text-indigo-600" @click.stop>{{ log.delivery ?? '—' }}</Link>
                            <span v-else>—</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-500">{{ log.buyer ?? '—' }}</td>
                        <td class="px-6 py-4 text-xs text-slate-500">{{ log.tier ? `T${log.tier}` : '—' }}</td>
                        <td class="px-6 py-4"><StatusBadge :status="log.status" /></td>
                        <td class="px-6 py-4"><FormattedDate :value="log.created_at" format="relative" /></td>
                    </ClickableTableRow>
                </DataTable>
                <Pagination :links="deliveryPreview.links" />
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
