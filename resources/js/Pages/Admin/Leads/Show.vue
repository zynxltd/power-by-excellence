<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import CampaignWorkflowNav from '@/Components/UI/CampaignWorkflowNav.vue';
import DeliveryAttemptLog from '@/Components/Delivery/DeliveryAttemptLog.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    lead: Object,
    pipelineStages: Array,
    outcomeDetail: Object,
    processingMs: Number,
    navigation: Object,
    campaignWorkflow: { type: Object, default: null },
});

const activeTab = ref('overview');

const { formatMoney } = useMoneyFormat();

const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'fields', label: 'Fields' },
    { key: 'events', label: 'Events' },
    { key: 'deliveries', label: 'Deliveries' },
];

const fieldRows = computed(() => {
    const data = props.lead?.field_data ?? {};
    return Object.entries(data).map(([key, value]) => ({ key, value }));
});

const processingStatus = computed(() => {
    if (!props.processingMs) return null;
    if (props.processingMs < 200) return { label: 'Fast', class: 'text-emerald-600' };
    if (props.processingMs < 500) return { label: 'OK', class: 'text-amber-600' };
    return { label: 'Slow', class: 'text-rose-600' };
});

const eventLabels = {
    'pipeline.started': 'Processing started',
    'pipeline.completed': 'Processing completed',
    'lead.ingested': 'Lead received via API',
    'lead.validated': 'Validation passed',
    'lead.quarantined': 'Held in quarantine',
    'lead.duplicate': 'Marked as duplicate',
    'lead.rejected': 'Rejected',
    sold: 'Sold to buyer',
    processed: 'Processed through pipeline',
    distributed: 'Sent to buyer delivery',
    unsold: 'No buyer accepted',
    'auto_responder.sent': 'Auto responder sent',
    'distribution.tier_filtered': 'Tier entry filter',
    'delivery.filter_rejected': 'Delivery filter',
    'distribution.skipped_group': 'Hybrid rule skipped',
};

const formatEvent = (type) => eventLabels[type] ?? type?.replace(/[._]/g, ' ') ?? type;

const jumpToTab = (tab) => {
    if (tab) activeTab.value = tab;
};

const leadStatStrip = computed(() => [
    { label: 'Status', value: props.lead?.status ?? '—', accent: 'indigo' },
    { label: 'Buyer', value: props.lead?.sold_to_buyer?.name ?? '—', accent: 'cyan' },
    { label: 'Revenue', value: formatMoney(props.lead?.financials?.revenue ?? 0), accent: 'emerald' },
    { label: 'Processing', value: props.processingMs ? `${props.processingMs}ms` : '—', accent: 'amber' },
    { label: 'Queue ID', value: props.lead?.queue_id ?? '—' },
]);

const sortedDeliveryLogs = computed(() => {
    const logs = [...(props.lead?.delivery_logs ?? [])];
    return logs.sort((a, b) => {
        const tierA = a.delivery?.tier ?? 9999;
        const tierB = b.delivery?.tier ?? 9999;
        if (tierA !== tierB) return tierA - tierB;
        return new Date(a.created_at) - new Date(b.created_at);
    });
});

const shouldExpandLog = (log) => {
    if (log.ping_request || log.post_request || log.ping_response || log.post_response) {
        return true;
    }
    return ['failed', 'skipped', 'outbid'].includes(log.status);
};

const stageLabel = (stage) => {
    if (stage.state === 'complete') return 'Done';
    if (stage.state === 'current') return 'Active';
    if (stage.state === 'error') {
        if (stage.key === 'outcome' && props.outcomeDetail?.title) return props.outcomeDetail.title;
        return 'Issue';
    }
    return '—';
};
</script>

<template>
    <Head :title="`Lead ${lead.uuid?.slice(0, 8)}`" />
    <AuthenticatedLayout>
        <PageHeader :title="lead.campaign?.name ?? 'Lead'" :description="lead.uuid">
            <template #actions>
                <AppButton
                    v-if="navigation?.prev_id"
                    :href="route('leads.show', navigation.prev_id)"
                    variant="secondary"
                >
                    ← Previous
                </AppButton>
                <AppButton
                    v-if="navigation?.next_id"
                    :href="route('leads.show', navigation.next_id)"
                    variant="secondary"
                >
                    Next →
                </AppButton>
                <AppButton :href="route('leads.index', { campaign_id: lead.campaign_id })" variant="secondary">Pipeline</AppButton>
                <AppButton v-if="lead.campaign" :href="route('campaigns.show', lead.campaign.id)" variant="secondary">Campaign</AppButton>
                <template v-if="lead.status === 'quarantined'">
                    <AppButton :href="route('leads.quarantine.release', lead.id)" method="post">Release & repost</AppButton>
                    <AppButton variant="danger" :href="route('leads.quarantine.reject', lead.id)" method="post">Reject</AppButton>
                </template>
                <AppButton v-if="['unsold', 'quarantined'].includes(lead.status)" :href="route('leads.repost', lead.id)" method="post" variant="secondary">Repost to ping tree</AppButton>
            </template>
        </PageHeader>

        <CampaignWorkflowNav
            v-if="campaignWorkflow"
            :campaign="campaignWorkflow.campaign"
            :distribution-config-id="campaignWorkflow.distributionConfigId"
            current="leads"
            class="mb-6"
        />

        <Panel title="Pipeline progress" class="mb-6">
            <div class="flex flex-wrap items-stretch gap-2 sm:gap-4">
                <template v-for="(stage, i) in pipelineStages" :key="stage.key">
                    <button
                        type="button"
                        :class="[
                            'flex min-w-[6.5rem] flex-col items-center rounded-xl border px-3 py-2 text-center transition',
                            stage.state === 'complete' && 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30',
                            stage.state === 'current' && 'border-indigo-300 bg-indigo-50 ring-2 ring-indigo-200 dark:border-indigo-600 dark:bg-indigo-950/40',
                            stage.state === 'error' && 'border-rose-300 bg-rose-50 dark:border-rose-700 dark:bg-rose-950/30 hover:border-rose-400',
                            stage.state === 'upcoming' && 'border-slate-200 bg-slate-50 opacity-60 dark:border-slate-700 dark:bg-slate-800/50',
                            stage.tab && 'cursor-pointer hover:ring-2 hover:ring-indigo-200',
                        ]"
                        :title="stage.tab ? 'Click to view details' : undefined"
                        @click="jumpToTab(stage.tab)"
                    >
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ stage.label }}</span>
                        <span
                            :class="[
                                'mt-1 text-xs font-semibold',
                                stage.state === 'complete' && 'text-emerald-600',
                                stage.state === 'current' && 'text-indigo-600',
                                stage.state === 'error' && 'text-rose-600',
                            ]"
                        >
                            {{ stageLabel(stage) }}
                        </span>
                        <span v-if="stage.detail" class="mt-1 line-clamp-2 text-[10px] leading-tight text-slate-500">{{ stage.detail }}</span>
                    </button>
                    <span v-if="i < pipelineStages.length - 1" class="hidden self-center text-slate-300 sm:inline">→</span>
                </template>
            </div>

            <div
                v-if="outcomeDetail && ['unsold', 'rejected', 'duplicate', 'quarantined'].includes(lead.status)"
                class="mt-4 rounded-xl border border-rose-200 bg-rose-50/80 p-4 dark:border-rose-800 dark:bg-rose-950/20"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-rose-900 dark:text-rose-200">{{ outcomeDetail.title }} — what happened</p>
                        <p class="mt-1 text-sm text-rose-800 dark:text-rose-300">{{ outcomeDetail.summary }}</p>
                        <p v-if="outcomeDetail.reason" class="mt-2 text-sm font-medium text-rose-900 dark:text-rose-100">Reason: {{ outcomeDetail.reason }}</p>
                        <ul v-if="outcomeDetail.hints?.length" class="mt-2 list-inside list-disc text-sm text-rose-700 dark:text-rose-300">
                            <li v-for="(hint, hi) in outcomeDetail.hints" :key="hi">{{ hint }}</li>
                        </ul>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <AppButton variant="secondary" @click="activeTab = 'deliveries'">View deliveries</AppButton>
                        <AppButton variant="secondary" @click="activeTab = 'events'">View events</AppButton>
                        <AppButton v-if="lead.campaign" :href="route('distribution.index') + '?campaign_id=' + lead.campaign_id" variant="secondary">Ping tree</AppButton>
                    </div>
                </div>
                <div v-if="Object.keys(outcomeDetail.delivery_stats ?? {}).length" class="mt-3 flex flex-wrap gap-2">
                    <span
                        v-for="(count, stat) in outcomeDetail.delivery_stats"
                        :key="stat"
                        class="rounded-lg bg-white/80 px-2 py-1 text-xs font-medium capitalize text-slate-700 dark:bg-slate-900/60 dark:text-slate-300"
                    >
                        {{ stat }}: {{ count }}
                    </span>
                </div>
            </div>
        </Panel>

        <CompactStatStrip :items="leadStatStrip" :columns="5" class="mt-6" />

        <p v-if="processingStatus" class="mt-2 text-sm" :class="processingStatus.class">
            Pipeline target &lt;200ms — {{ processingStatus.label }}
        </p>

        <div class="mt-6 flex gap-1 border-b border-slate-200 dark:border-slate-700">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                :class="[
                    'px-4 py-2.5 text-sm font-semibold transition',
                    activeTab === tab.key
                        ? 'border-b-2 border-indigo-600 text-slate-900 dark:text-white'
                        : 'text-slate-500 hover:text-slate-700',
                ]"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <Panel v-if="activeTab === 'overview'" title="Summary" class="mt-6">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs font-semibold uppercase text-slate-500">Received</dt><dd class="mt-1"><FormattedDate :value="lead.received_at" /></dd></div>
                <div><dt class="text-xs font-semibold uppercase text-slate-500">Distributed</dt><dd class="mt-1"><FormattedDate :value="lead.distributed_at" /></dd></div>
                <div><dt class="text-xs font-semibold uppercase text-slate-500">Tenant</dt><dd class="mt-1">{{ lead.account?.name ?? lead.campaign?.account?.name ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase text-slate-500">Reject reason</dt><dd class="mt-1">{{ lead.reject_reason ?? '—' }}</dd></div>
            </dl>
        </Panel>

        <Panel v-if="activeTab === 'fields'" title="Field data" class="mt-6" :padding="false">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Field</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="row in fieldRows" :key="row.key">
                        <td class="px-6 py-3 font-mono text-xs text-slate-500">{{ row.key }}</td>
                        <td class="px-6 py-3 text-slate-900 dark:text-white">{{ row.value }}</td>
                    </tr>
                </tbody>
            </table>
        </Panel>

        <Panel v-if="activeTab === 'events'" title="Event log" class="mt-6">
            <div v-if="!lead.events?.length" class="py-4 text-sm text-slate-500">No events recorded.</div>
            <div v-for="e in lead.events" :key="e.id" class="flex gap-4 border-b border-slate-100 py-3 last:border-0 dark:border-slate-800">
                <FormattedDate :value="e.created_at" class="shrink-0 text-xs" />
                <div class="min-w-0 flex-1">
                    <span class="font-semibold text-slate-900 dark:text-white">{{ formatEvent(e.event_type) }}</span>
                    <span class="text-slate-600 dark:text-slate-400"> — {{ e.message }}</span>
                    <p v-if="e.payload?.filter_summary?.length" class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                        Filters: {{ e.payload.filter_summary.join(' · ') }}
                    </p>
                    <p v-else-if="e.payload?.filter_rejection?.summary" class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                        {{ e.payload.filter_rejection.summary }}
                    </p>
                    <p v-if="e.payload?.duration_ms" class="mt-1 text-xs text-slate-500">{{ e.payload.duration_ms }}ms</p>
                </div>
            </div>
        </Panel>

        <Panel v-if="activeTab === 'deliveries'" class="mt-6">
            <template #header>
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white">Delivery attempts</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Ping/post request and response payloads for each buyer route. Expand a row to inspect the full exchange.
                    </p>
                </div>
            </template>

            <div v-if="!sortedDeliveryLogs.length" class="py-4 text-sm text-slate-500">
                No delivery attempts — this lead may have been rejected before distribution.
            </div>

            <div v-else class="space-y-3">
                <DeliveryAttemptLog
                    v-for="log in sortedDeliveryLogs"
                    :key="log.id"
                    :log="log"
                    :format-money="formatMoney"
                    :default-expanded="shouldExpandLog(log)"
                />
            </div>
        </Panel>
    </AuthenticatedLayout>
</template>
