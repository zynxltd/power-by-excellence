<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import BarChart from '@/Components/UI/BarChart.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    summary: Object,
    summary7d: Object,
    summary30d: Object,
    suppressionCount: { type: Number, default: 0 },
    deliverabilityAlerts: { type: Array, default: () => [] },
    alertThresholds: Object,
    hourlyOpens: Array,
    campaignStats: Array,
    segments: Array,
    templates: Array,
    sendingProfiles: Array,
    recentCampaigns: Array,
    throttle: Object,
    providers: Object,
    mergeTags: Array,
    defaultPreviewData: Object,
});

const metricsPeriod = ref('30d');

const activeSummary = computed(() => (metricsPeriod.value === '7d' ? props.summary7d : props.summary30d) ?? props.summary ?? {});

const periodDays = computed(() => activeSummary.value?.period_days ?? (metricsPeriod.value === '7d' ? 7 : 30));

const periodComparison = computed(() => [
    {
        label: 'Bounce rate',
        short7: `${props.summary7d?.bounce_rate ?? 0}%`,
        short30: `${props.summary30d?.bounce_rate ?? 0}%`,
        count7: props.summary7d?.bounces ?? 0,
        count30: props.summary30d?.bounces ?? 0,
    },
    {
        label: 'Complaint rate',
        short7: `${props.summary7d?.complaint_rate ?? 0}%`,
        short30: `${props.summary30d?.complaint_rate ?? 0}%`,
        count7: props.summary7d?.complaints ?? 0,
        count30: props.summary30d?.complaints ?? 0,
    },
    {
        label: 'Open rate',
        short7: `${props.summary7d?.open_rate ?? 0}%`,
        short30: `${props.summary30d?.open_rate ?? 0}%`,
        count7: props.summary7d?.opens ?? 0,
        count30: props.summary30d?.opens ?? 0,
    },
]);

const statStrip = computed(() => [
    { label: `Sent (${periodDays.value}d)`, value: activeSummary.value?.total_sent ?? 0 },
    { label: 'Bounce rate', value: `${activeSummary.value?.bounce_rate ?? 0}%`, accent: 'rose' },
    { label: 'Complaint rate', value: `${activeSummary.value?.complaint_rate ?? 0}%`, accent: 'amber' },
    { label: 'Open rate', value: `${activeSummary.value?.open_rate ?? 0}%`, accent: 'emerald' },
    { label: 'Suppressions', value: (props.suppressionCount ?? 0).toLocaleString(), accent: 'slate' },
    { label: 'Queue depth', value: props.throttle?.queue_depth ?? props.throttle?.queued_campaigns ?? 0, accent: 'indigo' },
]);

const hasSendData = computed(() => (activeSummary.value?.total_sent ?? 0) > 0);

const healthStatus = computed(() => {
    const bounce = activeSummary.value?.bounce_rate ?? 0;
    const complaints = activeSummary.value?.complaint_rate ?? 0;
    const bounceThreshold = props.alertThresholds?.bounce_rate_alert_pct ?? 5;
    const complaintThreshold = props.alertThresholds?.complaint_rate_alert_pct ?? 0.1;

    if ((props.deliverabilityAlerts ?? []).some((a) => a.level === 'critical')) {
        return { label: 'Critical', tone: 'rose', hint: 'Complaint rates exceeded configured thresholds — pause sends and review list hygiene.' };
    }
    if (bounce >= bounceThreshold || complaints >= complaintThreshold) {
        return { label: 'Needs attention', tone: 'rose', hint: 'Bounce or complaint rates are elevated — review ESP reputation and suppression list.' };
    }
    if (bounce >= bounceThreshold * 0.5 || complaints >= complaintThreshold * 0.5) {
        return { label: 'Monitor', tone: 'amber', hint: 'Rates are acceptable but trending warrants a watch on throttling and suppression.' };
    }
    if (!hasSendData.value) {
        return { label: 'No data yet', tone: 'slate', hint: 'Send a bulk campaign or auto-responder to populate deliverability metrics.' };
    }

    return { label: 'Healthy', tone: 'emerald', hint: 'Deliverability metrics are within normal ranges for the selected period.' };
});

const engagementMetrics = computed(() => [
    { key: 'opens', label: 'Opens', count: activeSummary.value?.opens ?? 0, rate: activeSummary.value?.open_rate ?? 0, color: '#10b981' },
    { key: 'clicks', label: 'Clicks', count: activeSummary.value?.clicks ?? 0, rate: activeSummary.value?.click_rate ?? 0, color: '#6366f1' },
    { key: 'delivered', label: 'Delivered (ESP)', count: activeSummary.value?.delivered ?? 0, rate: activeSummary.value?.delivery_rate ?? 0, color: '#06b6d4' },
    { key: 'bounces', label: 'Bounces', count: activeSummary.value?.bounces ?? 0, rate: activeSummary.value?.bounce_rate ?? 0, color: '#f43f5e' },
    { key: 'complaints', label: 'Complaints', count: activeSummary.value?.complaints ?? 0, rate: activeSummary.value?.complaint_rate ?? 0, color: '#f59e0b' },
]);

const hourlyChart = computed(() => {
    const opensByHour = Object.fromEntries((props.hourlyOpens ?? []).map((row) => [row.hour, row.opens]));
    const labels = Array.from({ length: 24 }, (_, hour) => `${String(hour).padStart(2, '0')}:00`);
    const data = labels.map((_, hour) => opensByHour[hour] ?? 0);

    return { labels, data };
});

const peakHour = computed(() => {
    const rows = props.hourlyOpens ?? [];
    if (!rows.length) {
        return null;
    }

    const peak = [...rows].sort((a, b) => b.opens - a.opens)[0];

    return {
        hour: `${String(peak.hour).padStart(2, '0')}:00`,
        opens: peak.opens,
    };
});

const providerChart = computed(() => {
    const entries = Object.entries(props.summary?.by_provider ?? {});

    return {
        labels: entries.map(([provider]) => provider),
        data: entries.map(([, count]) => count),
    };
});

const topCampaigns = computed(() => {
    const stats = [...(props.campaignStats ?? [])].sort((a, b) => b.sent - a.sent);

    return stats.slice(0, 8);
});

const throttleAlert = computed(() => {
    const t = props.throttle ?? {};

    if (t.paused && t.manual_paused) {
        return {
            tone: 'rose',
            title: 'Sending paused manually',
            message: 'Marketing sends are paused for this platform. Resume when you are ready to send again.',
        };
    }

    if (t.paused) {
        return {
            tone: 'rose',
            title: 'Sending paused',
            message: `Bounce rate exceeded ${t.bounce_threshold_pct}% in the last ${t.window_minutes} minutes. Bulk sends pause for ${t.pause_minutes} minutes automatically.`,
        };
    }

    if ((t.recent_bounces ?? 0) > 0 && (t.bounce_rate_recent ?? 0) >= t.bounce_threshold_pct * 0.7) {
        return {
            tone: 'amber',
            title: 'Approaching throttle limit',
            message: `Recent bounce rate is ${t.bounce_rate_recent}% (limit ${t.bounce_threshold_pct}%). Reduce send volume or clean your list.`,
        };
    }

    if ((t.queued_campaigns ?? 0) > 0) {
        return {
            tone: 'indigo',
            title: 'Queue active',
            message: `${t.queued_campaigns} campaign(s) scheduled or sending · default cap ${t.default_rate_per_minute}/min per campaign.`,
        };
    }

    return null;
});

const throttleToneClass = (tone) => ({
    rose: 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-200',
    amber: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200',
    indigo: 'border-indigo-200 bg-indigo-50 text-indigo-900 dark:border-indigo-900/50 dark:bg-indigo-950/40 dark:text-indigo-200',
}[tone] ?? '');

const segmentForm = useForm({ name: '', rules: { has_email: true } });
const editingTemplateId = ref(null);
const templateForm = useForm({
    name: '',
    channel: 'email',
    subject: '',
    body: '',
    html_body: '',
    preview_data: { ...(props.defaultPreviewData ?? {}) },
});
const profileForm = useForm({ name: '', provider: 'smtp', domain_match: '', from_name: '', from_email: '', is_default: false });

const bulkForm = useForm({
    name: '',
    channel: 'email',
    campaign_id: '',
    message_template_id: '',
    subject: '',
    message: '',
    html_body: '',
    segment_id: '',
    sending_profile_id: '',
    provider: '',
    throttle_per_minute: 100,
    scheduled_at: '',
    ab_enabled: false,
    ab_test: {
        split_percent: 20,
        wait_minutes: 60,
        winner_metric: 'open',
        variant_a: { subject: '', body: '', html_body: '' },
        variant_b: { subject: '', body: '', html_body: '' },
    },
});

const emailTemplates = computed(() => (props.templates ?? []).filter((t) => t.channel === 'email'));
const smsTemplates = computed(() => (props.templates ?? []).filter((t) => t.channel === 'sms'));
const templatesForBulkChannel = computed(() => {
    if (bulkForm.channel === 'sms') return smsTemplates.value;
    if (bulkForm.channel === 'email') return emailTemplates.value;

    return props.templates ?? [];
});

const bulkUsesEmail = computed(() => bulkForm.channel === 'email' || bulkForm.channel === 'both');
const bulkUsesSms = computed(() => bulkForm.channel === 'sms' || bulkForm.channel === 'both');

const applyBulkTemplate = () => {
    const template = (props.templates ?? []).find((t) => t.id === Number(bulkForm.message_template_id));
    if (!template) return;

    if (template.channel === 'email' || bulkUsesEmail.value) {
        bulkForm.subject = template.subject ?? bulkForm.subject;
        bulkForm.html_body = template.html_body ?? bulkForm.html_body;
    }
    bulkForm.message = template.body ?? bulkForm.message;
};

const submitBulkCampaign = () => {
    const payload = {
        ...bulkForm.data(),
        ab_test: bulkForm.ab_enabled ? bulkForm.ab_test : null,
    };

    bulkForm.transform(() => payload).post(route('e-delivery.bulk-campaigns.store'), {
        preserveScroll: true,
        onSuccess: () => {
            bulkForm.reset();
            bulkForm.channel = 'email';
            bulkForm.throttle_per_minute = 100;
            bulkForm.ab_enabled = false;
        },
        onFinish: () => bulkForm.transform((data) => data),
    });
};

const sendBulkCampaignNow = (id) => {
    if (confirm('Send this bulk campaign now?')) {
        router.post(route('e-delivery.bulk-campaigns.send', id), {}, { preserveScroll: true });
    }
};

const renderMergeTags = (text, data) => {
    if (!text) {
        return '';
    }

    return text.replace(/\{\{([a-zA-Z0-9_]+)\}\}/g, (_, key) => data?.[key] ?? '');
};

const templatePreview = computed(() => {
    const data = templateForm.preview_data ?? props.defaultPreviewData ?? {};

    return {
        subject: renderMergeTags(templateForm.subject, data),
        body: renderMergeTags(templateForm.body, data),
        html: renderMergeTags(templateForm.html_body, data),
    };
});

const editTemplate = (template) => {
    editingTemplateId.value = template.id;
    templateForm.name = template.name;
    templateForm.channel = template.channel ?? 'email';
    templateForm.subject = template.subject ?? '';
    templateForm.body = template.body ?? '';
    templateForm.html_body = template.html_body ?? '';
    templateForm.preview_data = { ...(props.defaultPreviewData ?? {}), ...(template.preview_data ?? {}) };
};

const resetTemplateForm = () => {
    editingTemplateId.value = null;
    templateForm.reset();
    templateForm.channel = 'email';
    templateForm.preview_data = { ...(props.defaultPreviewData ?? {}) };
};

const insertMergeTag = (tag) => {
    templateForm.html_body = `${templateForm.html_body ?? ''}{{${tag}}}`;
};

const mergeTagLabel = (tag) => `{{${tag}}}`;

const submitSegment = () => segmentForm.post(route('e-delivery.segments.store'), { preserveScroll: true, onSuccess: () => segmentForm.reset() });
const submitTemplate = () => {
    const options = { preserveScroll: true, onSuccess: () => resetTemplateForm() };

    if (editingTemplateId.value) {
        templateForm.put(route('e-delivery.templates.update', editingTemplateId.value), options);
    } else {
        templateForm.post(route('e-delivery.templates.store'), options);
    }
};
const submitProfile = () => profileForm.post(route('e-delivery.sending-profiles.store'), { preserveScroll: true, onSuccess: () => profileForm.reset() });

const pauseSending = () => router.post(route('e-delivery.throttle.pause'), {}, { preserveScroll: true });
const resumeSending = () => router.post(route('e-delivery.throttle.resume'), {}, { preserveScroll: true });

const alertToneClass = (level) => ({
    critical: 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-200',
    warning: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200',
}[level] ?? 'border-slate-200 bg-slate-50 text-slate-800');

const healthBadgeClass = (tone) => ({
    emerald: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    rose: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
    slate: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
}[tone] ?? 'bg-slate-100 text-slate-700');
</script>

<template>
    <Head title="E-Delivery" />
    <AuthenticatedLayout>
        <PageHeader
            title="E-Delivery"
            description="Email & SMS marketing — deliverability, segments, templates, and multi-channel campaigns."
        >
            <template #actions>
                <AppButton :href="route('integrations.messaging')" variant="secondary">ESP settings</AppButton>
                <AppButton :href="route('features.auto-responders')" variant="secondary">Auto-responders</AppButton>
            </template>
        </PageHeader>

        <section class="mb-6">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Deliverability ops center</h2>
                    <p class="text-xs text-slate-500">ESP feedback, suppressions, throttling, and send queue health</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex rounded-lg border border-slate-200 p-0.5 dark:border-slate-700">
                        <button
                            type="button"
                            :class="['rounded-md px-3 py-1 text-xs font-semibold transition', metricsPeriod === '7d' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800']"
                            @click="metricsPeriod = '7d'"
                        >
                            7 days
                        </button>
                        <button
                            type="button"
                            :class="['rounded-md px-3 py-1 text-xs font-semibold transition', metricsPeriod === '30d' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800']"
                            @click="metricsPeriod = '30d'"
                        >
                            30 days
                        </button>
                    </div>
                    <span
                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                        :class="healthBadgeClass(healthStatus.tone)"
                    >
                        {{ healthStatus.label }}
                    </span>
                </div>
            </div>

            <CompactStatStrip :items="statStrip" :columns="6" class="mb-4" />

            <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">{{ healthStatus.hint }}</p>

            <div v-if="deliverabilityAlerts?.length" class="mb-4 space-y-2">
                <div
                    v-for="(alert, index) in deliverabilityAlerts"
                    :key="`${alert.metric}-${index}`"
                    class="rounded-xl border px-4 py-3 text-sm"
                    :class="alertToneClass(alert.level)"
                >
                    <p class="font-semibold capitalize">{{ alert.level }} — {{ alert.metric.replace(/_/g, ' ') }}</p>
                    <p class="mt-1 opacity-90">{{ alert.message }}</p>
                </div>
            </div>

            <div
                v-if="throttleAlert"
                class="mb-4 rounded-xl border px-4 py-3 text-sm"
                :class="throttleToneClass(throttleAlert.tone)"
            >
                <p class="font-semibold">{{ throttleAlert.title }}</p>
                <p class="mt-1 opacity-90">{{ throttleAlert.message }}</p>
            </div>

            <Panel title="Send throttling & queue" class="mb-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        {{ throttle?.paused ? 'Sending is paused' : 'Sending is active' }}
                        <span v-if="throttle?.paused_reason" class="text-xs text-slate-500">({{ throttle.paused_reason === 'manual' ? 'manual' : 'auto bounce guard' }})</span>
                    </p>
                    <div class="flex gap-2">
                        <AppButton v-if="!throttle?.paused" type="button" variant="secondary" size="sm" @click="pauseSending">Pause sending</AppButton>
                        <AppButton v-else type="button" size="sm" @click="resumeSending">Resume sending</AppButton>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Status</p>
                        <p class="mt-1 text-lg font-semibold" :class="throttle?.paused ? 'text-rose-600' : 'text-emerald-600'">
                            {{ throttle?.paused ? 'Paused' : 'Active' }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Send rate cap</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900 dark:text-white">
                            {{ throttle?.active_rate_per_minute ?? throttle?.default_rate_per_minute }}/min
                        </p>
                        <p class="text-xs text-slate-500">~{{ throttle?.chunk_delay_seconds }}s delay per 10 sends</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Queue depth</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900 dark:text-white">{{ throttle?.queue_depth ?? 0 }}</p>
                        <p class="text-xs text-slate-500">{{ throttle?.queued_campaigns ?? 0 }} campaigns · {{ throttle?.pending_sends ?? 0 }} pending</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Suppressions</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900 dark:text-white">{{ (suppressionCount ?? 0).toLocaleString() }}</p>
                        <p class="text-xs text-slate-500">Opt-outs & ESP blocks</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Recent bounces</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums" :class="(throttle?.bounce_rate_recent ?? 0) >= (throttle?.bounce_threshold_pct ?? 15) ? 'text-rose-600' : 'text-slate-900 dark:text-white'">
                            {{ throttle?.bounce_rate_recent ?? 0 }}%
                        </p>
                        <p class="text-xs text-slate-500">{{ throttle?.recent_bounces ?? 0 }} / {{ throttle?.recent_sent ?? 0 }} sends ({{ throttle?.window_minutes }}m)</p>
                    </div>
                </div>
                <ul v-if="throttle?.queued_campaign_list?.length" class="mt-4 space-y-2 border-t border-slate-100 pt-4 text-sm dark:border-slate-800">
                    <li
                        v-for="c in throttle.queued_campaign_list"
                        :key="c.id"
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <span class="font-medium">{{ c.name }}</span>
                        <span class="text-xs text-slate-500">
                            {{ c.status }}
                            <span v-if="c.throttle_per_minute"> · {{ c.throttle_per_minute }}/min</span>
                        </span>
                    </li>
                </ul>
            </Panel>

            <Panel title="7d vs 30d comparison" class="mb-6">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div
                        v-for="row in periodComparison"
                        :key="row.label"
                        class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ row.label }}</p>
                        <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-xs text-slate-500">7 days</p>
                                <p class="text-lg font-semibold tabular-nums text-slate-900 dark:text-white">{{ row.short7 }}</p>
                                <p class="text-xs text-slate-400">{{ row.count7 }} events</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">30 days</p>
                                <p class="text-lg font-semibold tabular-nums text-slate-900 dark:text-white">{{ row.short30 }}</p>
                                <p class="text-xs text-slate-400">{{ row.count30 }} events</p>
                            </div>
                        </div>
                    </div>
                </div>
            </Panel>

            <div class="grid gap-6 lg:grid-cols-3">
                <Panel title="Engagement breakdown" class="lg:col-span-1">
                    <ul v-if="hasSendData" class="space-y-4">
                        <li v-for="metric in engagementMetrics" :key="metric.key">
                            <div class="mb-1 flex items-center justify-between text-sm">
                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ metric.label }}</span>
                                <span class="tabular-nums text-slate-500">
                                    {{ metric.count.toLocaleString() }}
                                    <span class="text-slate-400">({{ metric.rate }}%)</span>
                                </span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :style="{ width: `${Math.min(100, metric.rate)}%`, backgroundColor: metric.color }"
                                />
                            </div>
                        </li>
                    </ul>
                    <p v-else class="py-6 text-center text-sm text-slate-500">
                        No sends in this period. Launch a bulk campaign to see engagement metrics.
                    </p>
                    <p v-if="hasSendData && (activeSummary?.click_to_open_rate ?? 0) > 0" class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500 dark:border-slate-800">
                        Click-to-open rate:
                        <span class="font-semibold text-slate-700 dark:text-slate-300">{{ activeSummary.click_to_open_rate }}%</span>
                    </p>
                </Panel>

                <Panel title="Hourly opens" class="lg:col-span-2">
                    <BarChart
                        v-if="hasSendData && hourlyChart.data.some((v) => v > 0)"
                        :labels="hourlyChart.labels"
                        :datasets="[{ label: 'Opens', data: hourlyChart.data, color: '#6366f1', colorTo: '#818cf8' }]"
                        :height="220"
                        :show-legend="false"
                    />
                    <p v-else class="py-10 text-center text-sm text-slate-500">Open events will appear here once recipients engage with your emails.</p>
                    <p v-if="peakHour" class="mt-3 text-xs text-slate-500">
                        Peak window:
                        <span class="font-semibold text-slate-700 dark:text-slate-300">{{ peakHour.hour }}</span>
                        ({{ peakHour.opens }} opens) — schedule campaigns near this hour for better inbox placement.
                    </p>
                </Panel>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <Panel title="Volume by ESP">
                    <BarChart
                        v-if="providerChart.labels.length"
                        :labels="providerChart.labels"
                        :datasets="[{ label: 'Sends', data: providerChart.data, color: '#0ea5e9', colorTo: '#38bdf8' }]"
                        :height="200"
                        :show-legend="false"
                    />
                    <p v-else class="py-8 text-center text-sm text-slate-500">Provider split appears after your first tracked sends.</p>
                </Panel>

                <Panel title="Campaign performance">
                    <div v-if="topCampaigns.length" class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                    <th class="pb-2 pr-3">Campaign</th>
                                    <th class="pb-2 pr-3 text-right">Sent</th>
                                    <th class="pb-2 pr-3 text-right">Opens</th>
                                    <th class="pb-2 pr-3 text-right">Clicks</th>
                                    <th class="pb-2 text-right">Open %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in topCampaigns"
                                    :key="row.bulk_sms_campaign_id"
                                    class="border-t border-slate-100 dark:border-slate-800"
                                >
                                    <td class="py-2.5 pr-3">
                                        <p class="font-medium text-slate-900 dark:text-white">{{ row.name }}</p>
                                        <p class="text-xs capitalize text-slate-500">{{ row.channel ?? 'email' }}</p>
                                    </td>
                                    <td class="py-2.5 pr-3 text-right tabular-nums">{{ row.sent }}</td>
                                    <td class="py-2.5 pr-3 text-right tabular-nums">{{ row.opens }}</td>
                                    <td class="py-2.5 pr-3 text-right tabular-nums">{{ row.clicks ?? 0 }}</td>
                                    <td class="py-2.5 text-right">
                                        <span
                                            class="inline-flex min-w-[3rem] justify-end rounded-md px-2 py-0.5 text-xs font-semibold tabular-nums"
                                            :class="row.open_rate >= 20 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : row.open_rate >= 10 ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                                        >
                                            {{ row.open_rate }}%
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="py-8 text-center text-sm text-slate-500">Bulk campaign stats appear after sends are logged.</p>
                </Panel>
            </div>
        </section>

        <section class="mt-8">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-500">Lists & sending</h2>
            <div class="grid gap-6 lg:grid-cols-3">
                <Panel title="Segments">
                    <ul class="mb-4 space-y-1 text-sm">
                        <li v-for="s in segments" :key="s.id" class="flex items-center justify-between">
                            <span>{{ s.name }}</span>
                            <button type="button" class="text-xs text-rose-600" @click="router.delete(route('e-delivery.segments.destroy', s.id))">Remove</button>
                        </li>
                        <li v-if="!segments?.length" class="text-slate-500">No segments yet.</li>
                    </ul>
                    <form class="space-y-2 border-t border-slate-200 pt-4 dark:border-slate-700" @submit.prevent="submitSegment">
                        <InputLabel value="New segment" />
                        <TextInput v-model="segmentForm.name" class="w-full" placeholder="Engaged — opened 7d" required />
                        <AppButton type="submit" size="sm" :disabled="segmentForm.processing">Add</AppButton>
                    </form>
                </Panel>

                <Panel title="Templates" class="lg:col-span-2">
                    <ul class="mb-4 space-y-1 text-sm">
                        <li v-for="t in templates" :key="t.id" class="flex items-center justify-between gap-2">
                            <button type="button" class="text-left hover:text-indigo-600" @click="editTemplate(t)">
                                {{ t.name }} <span class="text-slate-400">({{ t.channel }})</span>
                            </button>
                            <button type="button" class="shrink-0 text-xs text-rose-600" @click="router.delete(route('e-delivery.templates.destroy', t.id))">Remove</button>
                        </li>
                        <li v-if="!templates?.length" class="text-slate-500">No templates yet.</li>
                    </ul>

                    <form class="space-y-3 border-t border-slate-200 pt-4 dark:border-slate-700" @submit.prevent="submitTemplate">
                        <div class="flex items-center justify-between gap-2">
                            <InputLabel :value="editingTemplateId ? 'Edit template' : 'New template'" />
                            <button v-if="editingTemplateId" type="button" class="text-xs text-slate-500 hover:text-slate-700" @click="resetTemplateForm">Cancel edit</button>
                        </div>
                        <TextInput v-model="templateForm.name" class="w-full" placeholder="Template name" required />
                        <TextInput v-model="templateForm.subject" class="w-full" placeholder="Subject — Hello {{first_name}}" />
                        <textarea v-model="templateForm.body" rows="2" class="form-input w-full" placeholder="Plain text — Hi {{first_name}}, …" />

                        <div>
                            <InputLabel value="HTML body" />
                            <textarea v-model="templateForm.html_body" rows="6" class="form-input mt-1 w-full font-mono text-sm" placeholder="<p>Hi {{first_name}},</p>" />
                        </div>

                        <div>
                            <InputLabel value="Merge tags" />
                            <div class="mt-1 flex flex-wrap gap-2">
                                <button
                                    v-for="tag in mergeTags"
                                    :key="tag.tag"
                                    type="button"
                                    class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-medium text-slate-700 hover:border-indigo-300 hover:bg-indigo-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                    @click="insertMergeTag(tag.tag)"
                                >
                                    {{ mergeTagLabel(tag.tag) }}
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Live preview</p>
                                <p class="text-sm font-medium text-slate-900 dark:text-white">{{ templatePreview.subject || '—' }}</p>
                                <pre class="mt-2 whitespace-pre-wrap text-xs text-slate-600 dark:text-slate-300">{{ templatePreview.body }}</pre>
                            </div>
                            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                                <p class="border-b border-slate-200 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-700">HTML preview</p>
                                <iframe
                                    v-if="templatePreview.html"
                                    class="h-48 w-full border-0 bg-white"
                                    sandbox=""
                                    :srcdoc="templatePreview.html"
                                    title="Template HTML preview"
                                />
                                <p v-else class="p-3 text-xs text-slate-500">Add HTML to see a rendered preview.</p>
                            </div>
                        </div>

                        <AppButton type="submit" size="sm" :disabled="templateForm.processing">
                            {{ editingTemplateId ? 'Update template' : 'Save template' }}
                        </AppButton>
                    </form>
                </Panel>

                <Panel title="Sending profiles">
                    <ul class="mb-4 space-y-1 text-sm">
                        <li v-for="p in sendingProfiles" :key="p.id" class="flex items-center justify-between">
                            <span>{{ p.name }} <span v-if="p.is_default" class="text-emerald-600">default</span></span>
                            <button type="button" class="text-xs text-rose-600" @click="router.delete(route('e-delivery.sending-profiles.destroy', p.id))">Remove</button>
                        </li>
                        <li v-if="!sendingProfiles?.length" class="text-slate-500">No sending profiles yet.</li>
                    </ul>
                    <form class="space-y-2 border-t border-slate-200 pt-4 dark:border-slate-700" @submit.prevent="submitProfile">
                        <TextInput v-model="profileForm.name" class="w-full" placeholder="Profile name" required />
                        <TextInput v-model="profileForm.domain_match" class="w-full" placeholder="Domain match e.g. gmail.com" />
                        <TextInput v-model="profileForm.from_email" class="w-full" placeholder="From email" />
                        <AppButton type="submit" size="sm" :disabled="profileForm.processing">Add profile</AppButton>
                    </form>
                </Panel>
            </div>
        </section>

        <section class="mt-8">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-500">Multi-channel bulk campaigns</h2>
            <div class="grid gap-6 lg:grid-cols-2">
                <Panel title="Create bulk campaign">
                    <form class="space-y-4" @submit.prevent="submitBulkCampaign">
                        <div>
                            <InputLabel value="Name" />
                            <TextInput v-model="bulkForm.name" class="mt-1 w-full" required />
                            <InputError class="mt-1" :message="bulkForm.errors.name" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Channel" />
                                <select v-model="bulkForm.channel" class="form-select mt-1 w-full">
                                    <option value="email">Email</option>
                                    <option value="sms">SMS</option>
                                    <option value="both">Email + SMS</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Provider" />
                                <select v-model="bulkForm.provider" class="form-select mt-1 w-full">
                                    <option value="">Platform default</option>
                                    <option v-for="p in (providers?.[bulkUsesEmail ? 'email' : 'sms'] ?? [])" :key="p" :value="p">{{ p }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <InputLabel value="Message template (optional)" />
                            <select v-model="bulkForm.message_template_id" class="form-select mt-1 w-full" @change="applyBulkTemplate">
                                <option value="">Custom content</option>
                                <option v-for="t in templatesForBulkChannel" :key="t.id" :value="t.id">{{ t.name }} ({{ t.channel }})</option>
                            </select>
                        </div>
                        <div v-if="bulkUsesEmail">
                            <InputLabel value="Email subject" />
                            <TextInput v-model="bulkForm.subject" class="mt-1 w-full" />
                        </div>
                        <div>
                            <InputLabel :value="bulkUsesSms && !bulkUsesEmail ? 'SMS message' : 'Message body'" />
                            <textarea v-model="bulkForm.message" rows="4" class="form-input mt-1 w-full" required maxlength="16000" />
                            <InputError class="mt-1" :message="bulkForm.errors.message" />
                        </div>
                        <div v-if="bulkUsesEmail">
                            <InputLabel value="HTML body (optional)" />
                            <textarea v-model="bulkForm.html_body" rows="3" class="form-input mt-1 w-full font-mono text-xs" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Segment" />
                                <select v-model="bulkForm.segment_id" class="form-select mt-1 w-full">
                                    <option value="">All leads</option>
                                    <option v-for="s in segments" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                            </div>
                            <div v-if="bulkUsesEmail">
                                <InputLabel value="Sending profile" />
                                <select v-model="bulkForm.sending_profile_id" class="form-select mt-1 w-full">
                                    <option value="">Default</option>
                                    <option v-for="p in sendingProfiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Throttle (per minute)" />
                                <TextInput v-model="bulkForm.throttle_per_minute" type="number" min="1" class="mt-1 w-full" />
                            </div>
                            <div>
                                <InputLabel value="Schedule (optional)" />
                                <input v-model="bulkForm.scheduled_at" type="datetime-local" class="form-input mt-1 w-full" />
                            </div>
                        </div>
                        <div class="rounded-lg border border-violet-200 bg-violet-50/50 p-3 dark:border-violet-900 dark:bg-violet-950/20">
                            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                                <input v-model="bulkForm.ab_enabled" type="checkbox" class="rounded border-slate-300 text-indigo-600" />
                                Enable A/B test
                            </label>
                            <div v-if="bulkForm.ab_enabled" class="mt-3 space-y-3 text-sm">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="text-xs text-slate-500">Sample %</label>
                                        <input v-model.number="bulkForm.ab_test.split_percent" type="number" min="5" max="50" class="form-input mt-1 w-full" />
                                    </div>
                                    <div>
                                        <label class="text-xs text-slate-500">Wait before winner (min)</label>
                                        <input v-model.number="bulkForm.ab_test.wait_minutes" type="number" min="5" class="form-input mt-1 w-full" />
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-500">Winner metric</label>
                                    <select v-model="bulkForm.ab_test.winner_metric" class="form-select mt-1 w-full">
                                        <option value="open">Open rate</option>
                                        <option value="click">Click rate</option>
                                    </select>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <p class="mb-1 text-xs font-semibold text-slate-600">Variant A</p>
                                        <input v-if="bulkUsesEmail" v-model="bulkForm.ab_test.variant_a.subject" type="text" class="form-input mb-2 w-full text-xs" placeholder="Subject A" />
                                        <textarea v-model="bulkForm.ab_test.variant_a.body" rows="2" class="form-input w-full text-xs" placeholder="Body A" />
                                    </div>
                                    <div>
                                        <p class="mb-1 text-xs font-semibold text-slate-600">Variant B</p>
                                        <input v-if="bulkUsesEmail" v-model="bulkForm.ab_test.variant_b.subject" type="text" class="form-input mb-2 w-full text-xs" placeholder="Subject B" />
                                        <textarea v-model="bulkForm.ab_test.variant_b.body" rows="2" class="form-input w-full text-xs" placeholder="Body B" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <AppButton type="submit" :disabled="bulkForm.processing" :loading="bulkForm.processing">Create campaign</AppButton>
                    </form>
                </Panel>

                <Panel title="Recent bulk campaigns">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                    <th class="pb-2 pr-4">Name</th>
                                    <th class="pb-2 pr-4">Channel</th>
                                    <th class="pb-2 pr-4">Status</th>
                                    <th class="pb-2 pr-4 text-right">Sent</th>
                                    <th class="pb-2 text-right">Failed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in recentCampaigns" :key="c.id" class="border-t border-slate-100 dark:border-slate-800">
                                    <td class="py-2 pr-4">
                                        <p class="font-medium">{{ c.name }}</p>
                                        <button
                                            v-if="['draft', 'scheduled'].includes(c.status)"
                                            type="button"
                                            class="mt-1 text-xs text-indigo-600 hover:underline"
                                            @click="sendBulkCampaignNow(c.id)"
                                        >
                                            Send now
                                        </button>
                                    </td>
                                    <td class="py-2 pr-4 capitalize">{{ c.channel ?? 'sms' }}</td>
                                    <td class="py-2 pr-4"><StatusBadge :status="c.status ?? 'draft'" /></td>
                                    <td class="py-2 pr-4 text-right tabular-nums">{{ c.sent_count ?? 0 }}</td>
                                    <td class="py-2 text-right tabular-nums text-rose-600">{{ c.failed_count ?? 0 }}</td>
                                </tr>
                                <tr v-if="!recentCampaigns?.length">
                                    <td colspan="5" class="py-6 text-center text-slate-500">No campaigns yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Panel>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
