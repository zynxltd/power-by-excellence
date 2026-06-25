<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import ClickableTableRow from '@/Components/UI/ClickableTableRow.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import CampaignWorkflowNav from '@/Components/UI/CampaignWorkflowNav.vue';
import TenantContextBanner from '@/Components/UI/TenantContextBanner.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, inject, ref, watch } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { useLiveStats } from '@/Composables/useLiveStats';

const props = defineProps({
    leads: Object,
    campaigns: Array,
    filters: Object,
    statuses: Array,
    pipelineSummary: Object,
    showTenantColumn: Boolean,
    campaignWorkflow: { type: Object, default: null },
});

const { formatMoney } = useMoneyFormat();
const { stats: liveStats } = useLiveStats();
const isNavigating = inject('isNavigating', ref(false));

const pipelineSummary = computed(() => ({
    ...props.pipelineSummary,
    ...(liveStats.value?.pipeline_summary ?? {}),
}));

const processingCount = computed(() => liveStats.value?.processing_count ?? pipelineSummary.value?.processing ?? 0);

const localFilters = ref({ ...props.filters });

const applyFilters = () => {
    router.get(route('leads.index'), localFilters.value, { preserveState: true, replace: true });
};

const clearFilters = () => {
    localFilters.value = {};
    applyFilters();
};

const filterByStatus = (status) => {
    localFilters.value = { ...localFilters.value, status: localFilters.value.status === status ? '' : status };
    applyFilters();
};

const summaryCards = [
    { key: '', label: 'All', accent: 'slate' },
    { key: 'pending', label: 'Pending', accent: 'indigo' },
    { key: 'processing', label: 'Processing', accent: 'violet', live: true },
    { key: 'sold', label: 'Sold', accent: 'emerald' },
    { key: 'unsold', label: 'Unsold', accent: 'amber' },
    { key: 'quarantined', label: 'Quarantined', accent: 'rose' },
    { key: 'rejected', label: 'Rejected', accent: 'rose' },
];

watch(() => props.filters, (f) => { localFilters.value = { ...f }; });
</script>

<template>
    <Head title="Lead Pipeline" />
    <AuthenticatedLayout>
        <PageHeader title="Lead Pipeline" description="Track leads through ingest, validation, distribution, and sale. Click a status to filter.">
            <template #actions>
                <AppButton :href="route('operations.index')" variant="secondary">Live ops</AppButton>
                <AppButton :href="route('quarantine.index')" variant="secondary">Quarantine</AppButton>
                <AppButton :href="route('leads.export', localFilters)" variant="secondary" external>Export CSV</AppButton>
            </template>
        </PageHeader>

        <TenantContextBanner />

        <CampaignWorkflowNav
            v-if="campaignWorkflow"
            :campaign="campaignWorkflow.campaign"
            :distribution-config-id="campaignWorkflow.distributionConfigId"
            :tenant-hub="campaignWorkflow.tenantHub"
            current="leads"
            class="mb-6"
        />

        <div class="mb-6 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
            <button
                v-for="card in summaryCards"
                :key="card.key || 'all'"
                type="button"
                :class="[
                    'rounded-xl border px-3 py-2.5 text-left transition',
                    (localFilters.status || '') === card.key
                        ? 'border-indigo-400 bg-indigo-50 ring-1 ring-indigo-200 dark:border-indigo-600 dark:bg-indigo-950/40'
                        : 'border-slate-200 bg-white hover:border-indigo-200 dark:border-slate-800 dark:bg-slate-900',
                ]"
                @click="filterByStatus(card.key)"
            >
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ card.label }}</p>
                <p class="mt-0.5 flex items-center gap-2 text-lg font-bold text-slate-900 dark:text-white">
                    {{ card.key ? (pipelineSummary?.[card.key] ?? 0) : (pipelineSummary?.total ?? 0) }}
                    <span
                        v-if="card.key === 'processing' && processingCount > 0"
                        class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-violet-700 dark:bg-violet-900/40 dark:text-violet-300"
                    >
                        <span class="relative flex h-1.5 w-1.5">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-violet-400 opacity-75" />
                            <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-violet-500" />
                        </span>
                        Live
                    </span>
                </p>
            </button>
        </div>

        <Panel v-if="processingCount > 0 && liveStats?.processing_leads?.length" title="Processing now" class="mb-6">
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <Link
                    v-for="lead in liveStats.processing_leads"
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

        <Panel class="mb-6">
            <div class="flex flex-wrap items-center justify-center gap-1 text-xs font-medium text-slate-500 sm:gap-2">
                <span class="rounded-full bg-slate-100 px-3 py-1 dark:bg-slate-800">Ingest</span>
                <span class="text-slate-300">→</span>
                <span class="rounded-full bg-violet-100 px-3 py-1 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">Validate</span>
                <span class="text-slate-300">→</span>
                <span class="rounded-full bg-indigo-100 px-3 py-1 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">Distribute</span>
                <span class="text-slate-300">→</span>
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Sold / Unsold</span>
            </div>
        </Panel>

        <Panel title="Filters" class="mb-6">
            <div class="grid gap-4 md:grid-cols-5">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Search UUID</label>
                    <input v-model="localFilters.search" type="text" class="form-input" placeholder="UUID or queue ID" @keyup.enter="applyFilters" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Status</label>
                    <select v-model="localFilters.status" class="form-select">
                        <option value="">All statuses</option>
                        <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Campaign</label>
                    <select v-model="localFilters.campaign_id" class="form-select">
                        <option value="">All campaigns</option>
                        <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">From</label>
                    <input v-model="localFilters.from_date" type="date" class="form-input" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">To</label>
                    <input v-model="localFilters.to_date" type="date" class="form-input" />
                </div>
            </div>
            <div class="mt-4 flex gap-3">
                <AppButton @click="applyFilters">Apply filters</AppButton>
                <AppButton variant="secondary" @click="clearFilters">Clear</AppButton>
            </div>
        </Panel>

        <Panel :padding="false">
            <template #header>
                <span class="text-sm text-slate-500">{{ leads.total }} leads matching filters</span>
            </template>
            <DataTable :empty="!leads.data?.length" empty-message="No leads match your filters." :loading="isNavigating">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">UUID</th>
                    <th v-if="showTenantColumn" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Platform</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Revenue</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Received</th>
                </template>
                <ClickableTableRow v-for="lead in leads.data" :key="lead.id" :href="route('leads.show', lead.id)">
                    <td class="px-6 py-4 font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ lead.uuid?.slice(0, 12) }}…</td>
                    <td v-if="showTenantColumn" class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                        {{ lead.account?.brand_name || lead.account?.name || lead.campaign?.account?.brand_name || '—' }}
                    </td>
                    <td class="px-6 py-4 text-slate-900 dark:text-white">{{ lead.campaign?.name }}</td>
                    <td class="px-6 py-4"><StatusBadge :status="lead.status" /></td>
                    <td class="px-6 py-4 font-medium">{{ formatMoney(lead.financials?.revenue ?? 0) }}</td>
                    <td class="px-6 py-4"><FormattedDate :value="lead.received_at" /></td>
                </ClickableTableRow>
            </DataTable>
            <Pagination :links="leads.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
