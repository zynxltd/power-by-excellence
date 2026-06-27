<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import HorizontalSwipeScroll from '@/Components/UI/HorizontalSwipeScroll.vue';
import TenantContextBanner from '@/Components/UI/TenantContextBanner.vue';
import SequenceBuilder from '@/Components/Automation/SequenceBuilder.vue';
import { Head, router, useForm, Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    sequences: Array,
    bulkCampaigns: Array,
    segments: Array,
    sendingProfiles: Array,
    templates: Array,
    eventAlerts: Array,
    campaigns: Array,
    metrics: Array,
    routingOverview: Array,
    providers: Object,
    alertChannels: Array,
    recentAlertFires: Array,
});

const { formatMoney } = useMoneyFormat();

const metricLabel = (value) => props.metrics?.find((m) => m.value === value)?.label ?? value;

const operatorLabel = (operator) => ({
    lt: 'less than',
    lte: 'less than or equal to',
    gt: 'greater than',
    gte: 'greater than or equal to',
    eq: 'equal to',
}[operator] ?? operator);

const tabs = [
    { key: 'routing', label: 'Routing & Tiers' },
    { key: 'sequences', label: 'Sequences' },
    { key: 'bulk-sms', label: 'Bulk messaging' },
    { key: 'alerts', label: 'Event Alerts' },
];

const page = usePage();
const tabFromUrl = new URLSearchParams(page.url.split('?')[1] ?? '').get('tab');
const activeTab = ref(tabs.some((t) => t.key === tabFromUrl) ? tabFromUrl : 'routing');

const bulkForm = useForm({
    name: '',
    campaign_id: '',
    channel: 'sms',
    subject: '',
    provider: '',
    message: '',
    filter: { has_phone: true, has_email: false },
    scheduled_at: '',
    html_body: '',
    segment_id: '',
    sending_profile_id: '',
    throttle_per_minute: '',
});

const alertForm = useForm({
    name: '',
    metric: 'leads_today',
    operator: 'lt',
    threshold: 10,
    channel: 'email',
    config: { email: '', phone: '', webhook_url: '', slack_webhook: '', provider: '', cooldown_minutes: 60 },
});

const submitBulk = () => {
    bulkForm.post(route('automation.bulk-sms.store'), {
        onSuccess: () => bulkForm.reset(),
    });
};

const submitAlert = () => {
    alertForm.post(route('automation.alerts.store'), {
        onSuccess: () => alertForm.reset(),
    });
};

const destroyAlert = (id) => {
    if (confirm('Delete this event alert?')) {
        router.delete(route('automation.alerts.destroy', id));
    }
};

const sendBulk = (id) => {
    if (confirm('Send this bulk SMS campaign now?')) {
        router.post(route('automation.bulk-sms.send', id));
    }
};
</script>

<template>
    <Head title="Automation" />
    <AuthenticatedLayout>
        <PageHeader
            title="Automation"
            description="Sequences, bulk SMS campaigns, event alerts, and consumer auto responders."
        >
            <template #actions>
                <AppButton :href="route('features.auto-responders')" variant="secondary">SMS &amp; email responders</AppButton>
            </template>
        </PageHeader>

        <TenantContextBanner />

        <div class="mb-6 flex flex-wrap gap-2 border-b border-slate-200 dark:border-slate-800">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                :class="[
                    'rounded-t-lg px-4 py-2.5 text-sm font-semibold transition',
                    activeTab === tab.key
                        ? 'border-b-2 border-indigo-600 text-indigo-600 dark:text-indigo-400'
                        : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300',
                ]"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Routing drill-down -->
        <div v-show="activeTab === 'routing'" class="space-y-6">
            <div class="rounded-xl border border-indigo-200 bg-indigo-50/60 px-4 py-3 text-sm text-indigo-900 dark:border-indigo-900 dark:bg-indigo-950/30 dark:text-indigo-200">
                <p class="font-semibold">Your platform ping trees only</p>
                <p class="mt-1">Routing configs shown here belong to <strong>this tenant</strong> - campaigns, buyers, and tiers from other platforms are never visible.</p>
                <p class="mt-2 text-xs text-indigo-700 dark:text-indigo-300">
                    <strong>Parallel auction</strong> - all buyers in a tier are pinged at once; highest bid above the floor wins and receives the full post. Other modes: waterfall (first accept wins), weighted, round-robin, sequential ping.
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <Link :href="route('routing.simulator')" class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300">
                    Open Routing Simulator →
                </Link>
                <Link :href="route('distribution.index')" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    Ping Tree Config
                </Link>
                <Link :href="route('deliveries.index')" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    All Deliveries
                </Link>
            </div>

            <div v-if="!routingOverview?.length" class="rounded-xl border border-dashed border-slate-300 py-12 text-center text-sm text-slate-500 dark:border-slate-700">
                No active ping tree configurations. Create one under Distribution.
            </div>

            <div v-for="config in routingOverview" :key="config.config_id" class="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-lg font-bold text-slate-900 dark:text-white">{{ config.config_name }}</p>
                            <p class="text-sm text-slate-500">{{ config.campaign?.name }} · {{ config.tier_count }} tiers</p>
                        </div>
                        <Link
                            :href="route('distribution.show', config.config_id)"
                            class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                        >
                            View flow →
                        </Link>
                    </div>
                </div>
                <div class="p-6">
                    <HorizontalSwipeScroll :scroll-step="280">
                        <div
                            v-for="tier in config.tiers"
                            :key="tier.tier"
                            class="w-56 shrink-0 snap-start rounded-xl border border-violet-200 bg-gradient-to-b from-violet-50 to-white p-4 shadow-sm transition hover:shadow-md dark:border-violet-900/50 dark:from-violet-950/30 dark:to-slate-900"
                        >
                            <div class="mb-3 flex items-center justify-between">
                                <span class="rounded-full bg-violet-600 px-2 py-0.5 text-[10px] font-bold uppercase text-white">Tier {{ tier.tier }}</span>
                                <span class="text-[10px] font-semibold uppercase text-slate-500">{{ tier.mode?.replace(/_/g, ' ') }}</span>
                            </div>
                            <p class="font-semibold text-slate-900 dark:text-white">{{ tier.name }}</p>
                            <p v-if="tier.floor_price" class="mt-1 text-sm font-medium text-emerald-600 dark:text-emerald-400">Floor {{ formatMoney(tier.floor_price, { currency: tier.currency }) }}</p>
                            <ul class="mt-3 space-y-2 border-t border-slate-200 pt-3 dark:border-slate-700">
                                <li v-for="d in tier.deliveries" :key="d.id" class="text-xs">
                                    <Link :href="route('deliveries.show', d.id)" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                                        {{ d.name }}
                                    </Link>
                                    <p class="text-slate-500">{{ d.buyer }} · {{ d.revenue_type }} {{ d.revenue_amount }}</p>
                                </li>
                            </ul>
                        </div>
                    </HorizontalSwipeScroll>
                    <p class="mt-2 text-center text-xs text-slate-400">Drag or scroll horizontally to view all tiers</p>
                </div>
            </div>
        </div>

        <!-- Sequences -->
        <div v-show="activeTab === 'sequences'">
            <SequenceBuilder :sequences="sequences" :campaigns="campaigns" :providers="providers" />
        </div>

        <!-- Bulk messaging -->
        <div v-show="activeTab === 'bulk-sms'" class="space-y-6">
            <p class="text-sm text-slate-500">
                <Link :href="route('e-delivery.index')" class="font-medium text-indigo-600 hover:underline">E-Delivery hub</Link>
                — deliverability reports, segments, templates, and sending profiles.
            </p>
            <div class="grid gap-6 lg:grid-cols-2">
                <Panel title="Create bulk campaign">
                    <form class="space-y-4" @submit.prevent="submitBulk">
                        <div>
                            <InputLabel value="Name" />
                            <TextInput v-model="bulkForm.name" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="bulkForm.errors.name" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Channel" />
                                <select v-model="bulkForm.channel" class="form-select mt-1 w-full">
                                    <option value="sms">SMS</option>
                                    <option value="email">Email</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Provider" />
                                <select v-model="bulkForm.provider" class="form-select mt-1 w-full">
                                    <option value="">Default</option>
                                    <option v-for="p in (providers?.[bulkForm.channel] ?? [])" :key="p" :value="p">{{ p }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <InputLabel value="Campaign (optional)" />
                            <select v-model="bulkForm.campaign_id" class="form-select mt-1 w-full">
                                <option value="">All campaigns</option>
                                <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div v-if="bulkForm.channel === 'email'">
                            <InputLabel value="Subject" />
                            <TextInput v-model="bulkForm.subject" class="mt-1 block w-full" />
                        </div>
                        <div>
                            <InputLabel value="Message" />
                            <textarea v-model="bulkForm.message" rows="4" class="form-input mt-1 w-full" required maxlength="16000" />
                            <InputError class="mt-1" :message="bulkForm.errors.message" />
                        </div>
                        <div v-if="bulkForm.channel === 'email'">
                            <InputLabel value="HTML body (optional)" />
                            <textarea v-model="bulkForm.html_body" rows="3" class="form-input mt-1 w-full font-mono text-xs" placeholder="<p>Hello [firstname]</p>" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Segment" />
                                <select v-model="bulkForm.segment_id" class="form-select mt-1 w-full">
                                    <option value="">Filter only</option>
                                    <option v-for="s in segments" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Sending profile" />
                                <select v-model="bulkForm.sending_profile_id" class="form-select mt-1 w-full">
                                    <option value="">Default</option>
                                    <option v-for="p in sendingProfiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <InputLabel value="Throttle (per minute)" />
                            <TextInput v-model="bulkForm.throttle_per_minute" type="number" min="1" class="mt-1 block w-full" placeholder="100" />
                        </div>
                        <div>
                            <InputLabel value="Schedule (optional)" />
                            <input v-model="bulkForm.scheduled_at" type="datetime-local" class="form-input mt-1 w-full" />
                        </div>
                        <AppButton type="submit" :disabled="bulkForm.processing">Create Campaign</AppButton>
                    </form>
                </Panel>

                <Panel title="Bulk campaigns">
                    <div v-if="!bulkCampaigns?.length" class="py-8 text-center text-sm text-slate-500">No bulk campaigns yet.</div>
                    <div
                        v-for="campaign in bulkCampaigns"
                        :key="campaign.id"
                        class="mb-4 rounded-xl border border-slate-200 p-4 last:mb-0 dark:border-slate-700"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-slate-900 dark:text-white">
                                    {{ campaign.name }}
                                    <span class="ml-2 text-xs uppercase text-slate-500">{{ campaign.channel ?? 'sms' }}</span>
                                </p>
                                <p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ campaign.message }}</p>
                                <p class="mt-2 text-xs text-slate-500">
                                    Sent {{ campaign.sent_count ?? 0 }} · Failed {{ campaign.failed_count ?? 0 }}
                                    <span v-if="campaign.campaign"> · {{ campaign.campaign.name }}</span>
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <StatusBadge :status="campaign.status ?? 'draft'" />
                                <AppButton
                                    v-if="campaign.status === 'draft' || campaign.status === 'scheduled'"
                                    variant="secondary"
                                    @click="sendBulk(campaign.id)"
                                >
                                    Send
                                </AppButton>
                            </div>
                        </div>
                    </div>
                </Panel>
            </div>
        </div>

        <!-- Event Alerts -->
        <div v-show="activeTab === 'alerts'" class="space-y-6">
            <div class="grid gap-6 lg:grid-cols-2">
                <Panel title="Create Event Alert">
                    <form class="space-y-4" @submit.prevent="submitAlert">
                        <div>
                            <InputLabel value="Name" />
                            <TextInput v-model="alertForm.name" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="alertForm.errors.name" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Metric" />
                                <select v-model="alertForm.metric" class="form-select mt-1 w-full">
                                    <option v-for="m in metrics" :key="m.value" :value="m.value">{{ m.label }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Operator" />
                                <select v-model="alertForm.operator" class="form-select mt-1 w-full">
                                    <option value="lt">Less than</option>
                                    <option value="lte">Less than or equal</option>
                                    <option value="gt">Greater than</option>
                                    <option value="gte">Greater than or equal</option>
                                    <option value="eq">Equal to</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Threshold" />
                                <input v-model.number="alertForm.threshold" type="number" step="any" class="form-input mt-1 w-full" required />
                            </div>
                            <div>
                                <InputLabel value="Channel" />
                                <select v-model="alertForm.channel" class="form-select mt-1 w-full">
                                    <option v-for="ch in alertChannels" :key="ch" :value="ch">{{ ch }}</option>
                                </select>
                            </div>
                        </div>
                        <div v-if="alertForm.channel === 'email'">
                            <InputLabel value="Notification email" />
                            <TextInput v-model="alertForm.config.email" type="email" class="mt-1 block w-full" />
                        </div>
                        <div v-if="alertForm.channel === 'sms'">
                            <InputLabel value="Phone number" />
                            <TextInput v-model="alertForm.config.phone" class="mt-1 block w-full" />
                        </div>
                        <div v-if="alertForm.channel === 'webhook'">
                            <InputLabel value="Webhook URL" />
                            <TextInput v-model="alertForm.config.webhook_url" type="url" class="mt-1 block w-full" />
                        </div>
                        <div v-if="alertForm.channel === 'slack'">
                            <InputLabel value="Slack webhook URL" />
                            <TextInput v-model="alertForm.config.slack_webhook" type="url" class="mt-1 block w-full" />
                        </div>
                        <div>
                            <InputLabel value="Cooldown (minutes)" />
                            <input v-model.number="alertForm.config.cooldown_minutes" type="number" min="5" class="form-input mt-1 w-full" />
                        </div>
                        <AppButton type="submit" :disabled="alertForm.processing">Create Alert</AppButton>
                    </form>
                </Panel>

                <Panel title="Event Alerts">
                    <div v-if="!eventAlerts?.length" class="py-8 text-center text-sm text-slate-500">No event alerts configured.</div>
                    <div
                        v-for="alert in eventAlerts"
                        :key="alert.id"
                        class="mb-4 rounded-xl border border-slate-200 p-4 last:mb-0 dark:border-slate-700"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="font-semibold text-slate-900 dark:text-white">{{ alert.name }}</p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ metricLabel(alert.metric) }} {{ operatorLabel(alert.operator) }} {{ alert.threshold }}
                                    via {{ alert.channel }}
                                </p>
                            </div>
                            <AppButton variant="danger" @click="destroyAlert(alert.id)">Delete</AppButton>
                        </div>
                    </div>
                </Panel>

                <Panel title="Alert fire history" :padding="false">
                    <p class="border-b border-slate-100 px-4 py-3 text-xs text-slate-500 dark:border-slate-800">
                        When a threshold is breached, the alert fires here - useful for auditing notifications sent via email, SMS, Slack, or webhook.
                    </p>
                    <div v-if="!recentAlertFires?.length" class="p-6 text-sm text-slate-500">No alerts have fired yet.</div>
                    <div
                        v-for="fire in recentAlertFires"
                        :key="fire.id"
                        class="border-b border-slate-100 px-4 py-3 last:border-0 dark:border-slate-800"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900 dark:text-white">{{ fire.alert?.name ?? 'Alert' }}</p>
                                <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">
                                    {{ metricLabel(fire.metric) }} = {{ fire.value }}
                                    <span class="text-slate-400">(threshold {{ fire.threshold }})</span>
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ fire.account?.name ?? 'Platform' }} · {{ fire.channel }}
                                    <span v-if="fire.message"> · {{ fire.message }}</span>
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <StatusBadge :status="fire.status" />
                                <FormattedDate :value="fire.created_at" class="mt-1 block text-xs text-slate-500" />
                            </div>
                        </div>
                    </div>
                </Panel>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
