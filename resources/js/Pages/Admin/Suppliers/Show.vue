<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import ClickableTableRow from '@/Components/UI/ClickableTableRow.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import ManagementHubNav from '@/Components/UI/ManagementHubNav.vue';
import LineChart from '@/Components/UI/LineChart.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    supplier: Object,
    recentLeads: Array,
    leadStats: Object,
    portalUser: Object,
    qualityScorecard: { type: Object, default: null },
    scorecardDays: { type: Number, default: 30 },
});

const { formatMoney } = useMoneyFormat();

const scorecard = computed(() => props.qualityScorecard ?? {});
const sparkline = computed(() => scorecard.value.sparkline ?? {});

const scorecardStrip = computed(() => [
    { label: 'Submitted', value: scorecard.value.submitted ?? 0, accent: 'indigo' },
    { label: 'Sold rate', value: scorecard.value.sold_rate_pct != null ? `${scorecard.value.sold_rate_pct}%` : '—', accent: 'emerald' },
    { label: 'Reject rate', value: scorecard.value.reject_rate_pct != null ? `${scorecard.value.reject_rate_pct}%` : '—', accent: 'amber' },
    { label: 'EPL', value: scorecard.value.epl != null ? formatMoney(scorecard.value.epl) : '—', accent: 'cyan' },
    { label: 'Grade', value: scorecard.value.quality_grade ?? '—', accent: 'violet' },
]);

const chartDatasets = computed(() => [
    { label: 'Submitted', data: sparkline.value.submitted ?? [], color: '#6366f1' },
    { label: 'Sold', data: sparkline.value.sold ?? [], color: '#10b981' },
    {
        label: 'Reject %',
        data: (sparkline.value.reject_rate_pct ?? []).map((v) => v ?? 0),
        color: '#f59e0b',
    },
]);

const supplierStatStrip = computed(() => [
    { label: 'Reference', value: props.supplier.reference, accent: 'indigo' },
    { label: 'Total leads', value: props.leadStats.total, accent: 'cyan' },
    { label: 'Sold', value: props.leadStats.sold, accent: 'emerald' },
    { label: 'Status', value: props.supplier.status, accent: 'amber' },
]);

const applyScorecardDays = (days) => {
    router.get(route('suppliers.show', props.supplier.id), { days }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head :title="supplier.name" />
    <AuthenticatedLayout>
        <PageHeader
            :title="supplier.name"
            description="Affiliate / publisher profile - sources, attribution, and submitted leads."
        >
            <template #actions>
                <AppButton :href="route('api-keys.index')" variant="secondary">API Keys</AppButton>
                <AppButton :href="route('suppliers.edit', supplier.id)">Edit supplier</AppButton>
            </template>
        </PageHeader>

        <ManagementHubNav type="supplier" :entity="supplier" />

        <CompactStatStrip :items="supplierStatStrip" :columns="4" class="mb-6" />

        <Panel title="Quality scorecard" class="mt-6">
            <template #header>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs text-slate-500">
                        {{ scorecard.from }} → {{ scorecard.to }}
                    </span>
                    <div class="flex rounded-lg border border-slate-200 p-0.5 dark:border-slate-700">
                        <button
                            v-for="d in [7, 30, 90]"
                            :key="d"
                            type="button"
                            :class="[
                                'rounded-md px-2.5 py-1 text-xs font-semibold transition',
                                scorecardDays === d
                                    ? 'bg-indigo-600 text-white shadow-sm'
                                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
                            ]"
                            @click="applyScorecardDays(d)"
                        >
                            {{ d }}d
                        </button>
                    </div>
                </div>
            </template>

            <div
                v-if="scorecard.warnings?.length"
                class="mb-4 rounded-lg border border-amber-200 bg-amber-50/80 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-200"
            >
                <p v-for="(warning, i) in scorecard.warnings" :key="i">{{ warning }}</p>
            </div>

            <CompactStatStrip :items="scorecardStrip" :columns="5" class="mb-6" />

            <div class="mb-4 grid gap-4 text-sm sm:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Outcomes</p>
                    <p class="mt-1 text-slate-700 dark:text-slate-300">
                        Rejected {{ scorecard.rejected ?? 0 }} · Quarantined {{ scorecard.quarantined ?? 0 }} · Duplicate {{ scorecard.duplicate ?? 0 }}
                    </p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Supplier payout</p>
                    <p class="mt-1 font-medium text-emerald-600 dark:text-emerald-400">{{ formatMoney(scorecard.total_supplier_payout ?? 0) }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Revenue / sold</p>
                    <p class="mt-1 font-medium text-slate-900 dark:text-white">
                        {{ scorecard.revenue_per_sold != null ? formatMoney(scorecard.revenue_per_sold) : '—' }}
                    </p>
                </div>
            </div>

            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">7-day trend</p>
            <LineChart
                :labels="sparkline.labels ?? []"
                :datasets="chartDatasets"
                :height="180"
                :max-x-ticks="7"
                :value-formatter="(v) => v"
            />
        </Panel>

        <Panel v-if="portalUser" title="Supplier portal" class="mt-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Portal login: <strong>{{ portalUser.email }}</strong>
                    <span class="ml-2 text-slate-500">·</span>
                    <Link :href="route('portal.supplier.dashboard')" class="text-indigo-600 hover:underline">/portal/supplier</Link>
                </p>
                <AppButton :href="route('impersonate.start', portalUser.id)" method="post" variant="secondary">
                    Log in as supplier
                </AppButton>
            </div>
        </Panel>

        <Panel title="Traffic sources (SIDs)" class="mt-6">
            <div v-if="!supplier.sources?.length" class="text-sm text-slate-500">No sources configured. Add SIDs when editing this supplier.</div>
            <div v-else class="flex flex-wrap gap-2">
                <div
                    v-for="source in supplier.sources"
                    :key="source.id"
                    class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50"
                >
                    <p class="font-mono text-sm font-semibold text-indigo-600 dark:text-indigo-400">{{ source.sid }}</p>
                    <p class="text-xs text-slate-500">{{ source.name }}</p>
                    <p v-if="source.sub_suppliers?.length" class="mt-1 text-xs text-slate-400">
                        SSIDs: {{ source.sub_suppliers.map((s) => s.ssid).join(', ') }}
                    </p>
                </div>
            </div>
        </Panel>

        <Panel title="Recent submitted leads" class="mt-6" :padding="false">
            <DataTable :empty="!recentLeads?.length">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Lead</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Payout</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Received</th>
                </template>
                <ClickableTableRow v-for="lead in recentLeads" :key="lead.id" :href="route('leads.show', lead.id)">
                    <td class="px-6 py-4 font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ lead.uuid?.slice(0, 10) }}…</td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ lead.campaign?.name }}</td>
                    <td class="px-6 py-4"><StatusBadge :status="lead.status" /></td>
                    <td class="px-6 py-4 font-medium text-emerald-600 dark:text-emerald-400">{{ formatMoney(lead.financials?.payout ?? 0) }}</td>
                    <td class="px-6 py-4"><FormattedDate :value="lead.received_at" format="relative" /></td>
                </ClickableTableRow>
            </DataTable>
        </Panel>
    </AuthenticatedLayout>
</template>
