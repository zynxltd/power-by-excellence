<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import CampaignWorkflowNav from '@/Components/UI/CampaignWorkflowNav.vue';
import LeadQualityBadge from '@/Components/UI/LeadQualityBadge.vue';
import DeliveryAttemptLog from '@/Components/Delivery/DeliveryAttemptLog.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    lead: Object,
    pipelineStages: Array,
    outcomeDetail: Object,
    processingMs: Number,
    navigation: Object,
    campaignWorkflow: { type: Object, default: null },
    leadQuality: { type: Object, default: null },
    erasureBlockedReason: { type: String, default: null },
});

const activeTab = ref('overview');
const tabSection = ref(null);
const showErasureDialog = ref(false);

const erasureForm = useForm({
    reason: '',
});

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

const consentArtifact = computed(() => props.lead?.metadata?.consent ?? null);
const erasureMeta = computed(() => props.lead?.metadata?.erasure ?? null);
const isAnonymized = computed(() => !!props.lead?.metadata?.anonymized_at);

const submitErasure = () => {
    if (!window.confirm('Permanently erase all PII for this lead? This cannot be undone.')) {
        return;
    }

    erasureForm.post(route('leads.erasure', props.lead.id), {
        preserveScroll: true,
        onSuccess: () => {
            showErasureDialog.value = false;
            erasureForm.reset();
        },
    });
};

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
    if (!tab) {
        return;
    }

    activeTab.value = tab;

    nextTick(() => {
        tabSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
};

const leadStatStrip = computed(() => {
    const items = [
        { label: 'Status', value: props.lead?.status ?? '-', accent: 'indigo' },
        { label: 'Quality', value: props.leadQuality ? `${props.leadQuality.score} (${props.leadQuality.grade_label})` : '-', accent: 'violet' },
        { label: 'Buyer', value: props.lead?.sold_to_buyer?.name ?? '-', accent: 'cyan' },
        { label: 'Revenue', value: formatMoney(props.lead?.financials?.revenue ?? 0), accent: 'emerald' },
        { label: 'Processing', value: props.processingMs ? `${props.processingMs}ms` : '-', accent: 'amber' },
        { label: 'Queue ID', value: props.lead?.queue_id ?? '-' },
    ];

    const feedback = props.lead?.buyer_feedback?.[0];
    if (feedback) {
        const invalid = ['invalid', 'bad_lead', 'returned'].includes(feedback.status);
        items.splice(1, 0, {
            label: 'Buyer feedback',
            value: feedback.status,
            accent: invalid ? 'rose' : 'emerald',
        });
    }

    if (isAnonymized.value) {
        items.push({ label: 'PII status', value: 'Anonymized', accent: 'slate' });
    }

    return items;
});

const invalidFeedback = computed(() =>
    (props.lead?.buyer_feedback ?? []).filter((f) => ['invalid', 'bad_lead', 'returned'].includes(f.status)),
);

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
    return '-';
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
                <AppButton v-if="!isAnonymized && !erasureBlockedReason" variant="danger" @click="showErasureDialog = true">Request erasure</AppButton>
            </template>
        </PageHeader>

        <div v-if="showErasureDialog" class="mb-6 rounded-xl border border-rose-200 bg-rose-50/60 p-4 dark:border-rose-900/40 dark:bg-rose-950/20">
            <p class="text-sm font-semibold text-rose-900 dark:text-rose-200">Request right-to-erasure</p>
            <p class="mt-1 text-sm text-rose-800 dark:text-rose-300">Permanently redact PII from this lead. Distribution history and non-PII metadata are retained.</p>
            <label class="mt-4 block text-xs font-semibold uppercase tracking-wider text-slate-500">Reason (required)</label>
            <textarea v-model="erasureForm.reason" rows="3" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900" placeholder="e.g. Data subject erasure request" />
            <p v-if="erasureForm.errors.reason" class="mt-1 text-sm text-rose-600">{{ erasureForm.errors.reason }}</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <AppButton variant="danger" :disabled="erasureForm.processing" @click="submitErasure">Confirm erasure</AppButton>
                <AppButton variant="secondary" @click="showErasureDialog = false">Cancel</AppButton>
            </div>
        </div>
        <div v-else-if="erasureBlockedReason && !isAnonymized" class="mb-6 rounded-xl border border-amber-200 bg-amber-50/70 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-200">
            Erasure blocked: {{ erasureBlockedReason }}
        </div>

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
                        <p class="font-semibold text-rose-900 dark:text-rose-200">{{ outcomeDetail.title }} - what happened</p>
                        <p class="mt-1 text-sm text-rose-800 dark:text-rose-300">{{ outcomeDetail.summary }}</p>
                        <p v-if="outcomeDetail.reason" class="mt-2 text-sm font-medium text-rose-900 dark:text-rose-100">Reason: {{ outcomeDetail.reason }}</p>
                        <ul v-if="outcomeDetail.hints?.length" class="mt-2 list-inside list-disc text-sm text-rose-700 dark:text-rose-300">
                            <li v-for="(hint, hi) in outcomeDetail.hints" :key="hi">{{ hint }}</li>
                        </ul>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <AppButton variant="secondary" @click="jumpToTab('deliveries')">View deliveries</AppButton>
                        <AppButton variant="secondary" @click="jumpToTab('events')">View events</AppButton>
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

        <CompactStatStrip :items="leadStatStrip" class="mt-6" />

        <div
            v-if="lead.buyer_feedback?.length"
            class="mt-4 rounded-xl border p-4"
            :class="invalidFeedback.length
                ? 'border-rose-300 bg-rose-50 dark:border-rose-800 dark:bg-rose-950/30'
                : 'border-indigo-200 bg-indigo-50/60 dark:border-indigo-800 dark:bg-indigo-950/20'"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-semibold" :class="invalidFeedback.length ? 'text-rose-900 dark:text-rose-100' : 'text-indigo-900 dark:text-indigo-100'">
                        Buyer feedback recorded
                    </p>
                    <p class="mt-1 text-sm" :class="invalidFeedback.length ? 'text-rose-800 dark:text-rose-200' : 'text-indigo-800 dark:text-indigo-200'">
                        {{ invalidFeedback.length ? 'This lead was flagged as invalid by the buyer.' : 'The buyer reported an outcome on this lead.' }}
                    </p>
                </div>
                <AppButton :href="route('buyer-feedback.index', { search: lead.uuid })" variant="secondary">All feedback</AppButton>
            </div>
            <div class="mt-4 space-y-3">
                <div
                    v-for="fb in lead.buyer_feedback"
                    :key="fb.id"
                    class="rounded-lg border border-white/60 bg-white/80 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/60"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <StatusBadge :status="fb.status" />
                        <span v-if="fb.buyer" class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ fb.buyer.name }}</span>
                        <FormattedDate :value="fb.created_at" class="text-xs text-slate-500" />
                    </div>
                    <p v-if="fb.notes" class="mt-2 text-sm text-slate-700 dark:text-slate-300">{{ fb.notes }}</p>
                    <div class="mt-2 flex flex-wrap gap-3 text-xs text-slate-500">
                        <Link v-if="lead.supplier" :href="route('suppliers.show', lead.supplier.id)" class="text-indigo-600 hover:underline">
                            Supplier: {{ lead.supplier.name }}
                        </Link>
                        <span v-if="lead.sid">SID: {{ lead.sid }}</span>
                        <Link
                            v-if="fb.buyer"
                            :href="route('buyers.show', { buyer: fb.buyer.id, feedback: fb.id })"
                            class="text-indigo-600 hover:underline"
                        >
                            View on buyer
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <p v-if="processingStatus" class="mt-2 text-sm" :class="processingStatus.class">
            Pipeline target &lt;200ms - {{ processingStatus.label }}
        </p>

        <div ref="tabSection" class="mt-6 scroll-mt-24 flex gap-1 border-b border-slate-200 dark:border-slate-700">
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
            <div v-if="erasureMeta || isAnonymized" class="mb-6 rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/40">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Erasure</p>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="isAnonymized ? 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-200' : 'bg-amber-100 text-amber-800'">
                        {{ isAnonymized ? 'PII anonymized' : 'Pending' }}
                    </span>
                </div>
                <dl v-if="erasureMeta" class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Requested</dt><dd class="mt-1"><FormattedDate :value="erasureMeta.requested_at" /></dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Completed</dt><dd class="mt-1"><FormattedDate :value="erasureMeta.completed_at" /></dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase text-slate-500">Reason</dt><dd class="mt-1">{{ erasureMeta.reason }}</dd></div>
                </dl>
            </div>
            <div v-if="leadQuality" class="mb-6 rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/40">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Lead quality score</p>
                        <div class="mt-2 flex items-center gap-3">
                            <LeadQualityBadge :quality="leadQuality" />
                            <span class="text-sm text-slate-600 dark:text-slate-400">0–100 based on validation, fraud &amp; completeness</span>
                        </div>
                    </div>
                    <AppButton :href="route('integrations.validation')" variant="secondary">Validation settings</AppButton>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                        <p class="text-[10px] font-semibold uppercase text-slate-500">Email</p>
                        <p class="mt-1 text-sm font-medium" :class="leadQuality.email?.status === 'passed' ? 'text-emerald-600' : leadQuality.email?.status === 'failed' ? 'text-rose-600' : 'text-slate-600'">
                            {{ leadQuality.email?.label }}
                        </p>
                        <p v-if="leadQuality.email?.fraud_score != null" class="text-xs text-slate-500">Fraud score: {{ leadQuality.email.fraud_score }}</p>
                        <p v-else-if="leadQuality.email?.detail" class="text-xs text-slate-500">{{ leadQuality.email.detail }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                        <p class="text-[10px] font-semibold uppercase text-slate-500">HLR (mobile)</p>
                        <p class="mt-1 text-sm font-medium" :class="leadQuality.hlr?.status === 'passed' ? 'text-emerald-600' : leadQuality.hlr?.status === 'failed' ? 'text-rose-600' : 'text-slate-600'">
                            {{ leadQuality.hlr?.label }}
                        </p>
                        <p v-if="leadQuality.hlr?.fraud_score != null" class="text-xs text-slate-500">Fraud score: {{ leadQuality.hlr.fraud_score }}</p>
                        <p v-else-if="leadQuality.hlr?.detail" class="text-xs text-slate-500">{{ leadQuality.hlr.detail }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                        <p class="text-[10px] font-semibold uppercase text-slate-500">IP fraud</p>
                        <p class="mt-1 text-sm font-medium" :class="leadQuality.ip?.status === 'passed' ? 'text-emerald-600' : leadQuality.ip?.status === 'failed' ? 'text-rose-600' : 'text-slate-600'">
                            {{ leadQuality.ip?.label }}
                        </p>
                        <p v-if="leadQuality.ip?.fraud_score != null" class="text-xs text-slate-500">Fraud score: {{ leadQuality.ip.fraud_score }}</p>
                        <p v-else-if="leadQuality.ip?.detail" class="text-xs text-slate-500">{{ leadQuality.ip.detail }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                        <p class="text-[10px] font-semibold uppercase text-slate-500">Completeness</p>
                        <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-200">{{ leadQuality.completeness?.label }}</p>
                        <p v-if="leadQuality.completeness?.missing?.length" class="text-xs text-amber-600">Missing: {{ leadQuality.completeness.missing.join(', ') }}</p>
                    </div>
                </div>
            </div>
            <div v-if="consentArtifact" class="mb-6 rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-900/40 dark:bg-indigo-950/20">
                <p class="text-xs font-semibold uppercase tracking-wider text-indigo-700 dark:text-indigo-300">Consent artifact</p>
                <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">{{ consentArtifact.consent_text }}</p>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Lawful basis</dt>
                        <dd class="mt-1 capitalize">{{ (consentArtifact.lawful_basis ?? '-').replace(/_/g, ' ') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Accepted</dt>
                        <dd class="mt-1">{{ consentArtifact.accepted ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Opt-in URL</dt>
                        <dd class="mt-1 break-all font-mono text-xs">{{ consentArtifact.optin_url ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Captured</dt>
                        <dd class="mt-1"><FormattedDate :value="consentArtifact.captured_at" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">IP address</dt>
                        <dd class="mt-1 font-mono text-xs">{{ consentArtifact.ip_address ?? lead.ip_address ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">User agent</dt>
                        <dd class="mt-1 break-all text-xs text-slate-600 dark:text-slate-400">{{ consentArtifact.user_agent ?? lead.user_agent ?? '-' }}</dd>
                    </div>
                </dl>
                <div v-if="consentArtifact.channel_consent && Object.keys(consentArtifact.channel_consent).length" class="mt-3 flex flex-wrap gap-2">
                    <span
                        v-for="(enabled, channel) in consentArtifact.channel_consent"
                        :key="channel"
                        class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="enabled ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800'"
                    >
                        {{ channel }}: {{ enabled ? 'yes' : 'no' }}
                    </span>
                </div>
            </div>
            <dl class="grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs font-semibold uppercase text-slate-500">Received</dt><dd class="mt-1"><FormattedDate :value="lead.received_at" /></dd></div>
                <div><dt class="text-xs font-semibold uppercase text-slate-500">Distributed</dt><dd class="mt-1"><FormattedDate :value="lead.distributed_at" /></dd></div>
                <div><dt class="text-xs font-semibold uppercase text-slate-500">Tenant</dt><dd class="mt-1">{{ lead.account?.name ?? lead.campaign?.account?.name ?? '-' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase text-slate-500">Reject reason</dt><dd class="mt-1">{{ lead.reject_reason ?? '-' }}</dd></div>
            </dl>

            <div class="mt-6 border-t border-slate-200 pt-6 dark:border-slate-700">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Source &amp; affiliate</p>
                <dl class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Supplier</dt>
                        <dd class="mt-1">
                            <Link v-if="lead.supplier" :href="route('suppliers.show', lead.supplier.id)" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                                {{ lead.supplier.name }}
                            </Link>
                            <span v-else class="text-slate-500">Direct / unknown</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Source (SID)</dt>
                        <dd class="mt-1 font-mono text-sm text-slate-900 dark:text-white">
                            <template v-if="lead.source">
                                {{ lead.source.name }}
                                <span class="text-slate-500">·</span>
                                {{ lead.source.sid ?? lead.sid }}
                            </template>
                            <template v-else-if="lead.sid">{{ lead.sid }}</template>
                            <span v-else class="text-slate-500">—</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Sub-supplier (SSID)</dt>
                        <dd class="mt-1 font-mono text-sm text-slate-900 dark:text-white">
                            <template v-if="lead.sub_supplier">{{ lead.sub_supplier.name }} · {{ lead.sub_supplier.ssid ?? lead.ssid }}</template>
                            <template v-else-if="lead.ssid">{{ lead.ssid }}</template>
                            <span v-else class="text-slate-500">—</span>
                        </dd>
                    </div>
                    <div v-if="lead.ip_address">
                        <dt class="text-xs font-semibold uppercase text-slate-500">Ingest IP</dt>
                        <dd class="mt-1 font-mono text-sm text-slate-900 dark:text-white">{{ lead.ip_address }}</dd>
                    </div>
                </dl>
            </div>
        </Panel>

        <Panel v-if="activeTab === 'fields'" title="Field data" class="mt-6" :padding="false">
            <div class="overflow-x-auto">
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
            </div>
        </Panel>

        <Panel v-if="activeTab === 'events'" title="Event log" class="mt-6">
            <div v-if="!lead.events?.length" class="py-4 text-sm text-slate-500">No events recorded.</div>
            <div v-for="e in lead.events" :key="e.id" class="flex gap-4 border-b border-slate-100 py-3 last:border-0 dark:border-slate-800">
                <FormattedDate :value="e.created_at" class="shrink-0 text-xs" />
                <div class="min-w-0 flex-1">
                    <span class="font-semibold text-slate-900 dark:text-white">{{ formatEvent(e.event_type) }}</span>
                    <span class="text-slate-600 dark:text-slate-400"> - {{ e.message }}</span>
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
                No delivery attempts - this lead may have been rejected before distribution.
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
