<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DeliveryMethodBadge from '@/Components/UI/DeliveryMethodBadge.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import CampaignWorkflowNav from '@/Components/UI/CampaignWorkflowNav.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    delivery: Object,
    recentLogs: Object,
    methodGuide: Object,
    health: String,
    healthReason: { type: String, default: null },
    platformName: { type: String, default: null },
    stats: Object,
    pingTreeLinks: Array,
    performance: Object,
    campaignWorkflow: { type: Object, default: null },
    initialTab: { type: String, default: 'overview' },
});

const page = usePage();
const testLeadUuid = computed(() => page.props.flash?.test_lead_uuid ?? null);
const testLeadId = computed(() => page.props.flash?.test_lead_id ?? null);

const { formatMoney } = useMoneyFormat(props.delivery?.campaign?.currency);

const activeTab = ref(props.initialTab === 'logs' ? 'logs' : 'overview');
const expandedLogId = ref(null);

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('tab') === 'logs') {
        activeTab.value = 'logs';
    }
});

const healthStyles = {
    healthy: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    warning: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    critical: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
    inactive: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
};

const tabs = [
    { id: 'overview', label: 'Overview' },
    { id: 'logs', label: 'Logs' },
    { id: 'routing', label: 'Routing' },
];

const testDelivery = () => {
    if (confirm('Run a test delivery using the latest lead for this campaign?')) {
        router.post(route('deliveries.test', props.delivery.id));
    }
};

const methodValue = () => props.delivery?.method?.value ?? props.delivery?.method;

const toggleLogExpand = (id) => {
    expandedLogId.value = expandedLogId.value === id ? null : id;
};

const formatJson = (data) => {
    if (!data) return null;
    try {
        return JSON.stringify(data, null, 2);
    } catch {
        return String(data);
    }
};

const logRows = () => props.recentLogs?.data ?? props.recentLogs ?? [];
</script>

<template>
    <Head :title="delivery.name" />
    <AuthenticatedLayout>
        <PageHeader :title="delivery.name" description="Delivery configuration, performance, and activity.">
            <template #actions>
                <div class="flex max-w-md flex-col items-end gap-1 text-right">
                    <span :class="['rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide', healthStyles[health] ?? healthStyles.inactive]">
                        {{ health }}
                    </span>
                    <p v-if="healthReason" class="text-xs leading-snug text-amber-800 dark:text-amber-300">
                        <span v-if="platformName" class="font-semibold">{{ platformName }} · </span>{{ healthReason }}
                    </p>
                </div>
                <AppButton variant="secondary" @click="testDelivery">Test delivery</AppButton>
                <AppButton :href="route('deliveries.edit', delivery.id)">Edit</AppButton>
            </template>
        </PageHeader>

        <CampaignWorkflowNav
            v-if="campaignWorkflow"
            :campaign="campaignWorkflow.campaign"
            :distribution-config-id="campaignWorkflow.distributionConfigId"
            current="deliveries"
            class="mb-6"
        />

        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <Panel class="!p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Method</p>
                <div class="mt-2"><DeliveryMethodBadge :method="methodValue()" /></div>
            </Panel>
            <Panel class="!p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Status</p>
                <div class="mt-2"><StatusBadge :status="delivery.status" /></div>
            </Panel>
            <Panel class="!p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">24h success rate</p>
                <p class="mt-2 text-xl font-bold text-slate-900 dark:text-white">
                    {{ stats?.success_rate != null ? `${stats.success_rate}%` : '—' }}
                </p>
            </Panel>
            <Panel class="!p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Today revenue</p>
                <p class="mt-2 text-xl font-bold text-emerald-600 dark:text-emerald-400">
                    {{ formatMoney(performance?.today?.revenue ?? 0) }}
                </p>
            </Panel>
            <Panel class="!p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">7-day attempts</p>
                <p class="mt-2 text-xl font-bold text-slate-900 dark:text-white">{{ performance?.last_7_days?.attempts ?? 0 }}</p>
            </Panel>
        </div>

        <div class="mb-6 border-b border-slate-200 dark:border-slate-800">
            <nav class="-mb-px flex gap-6">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    :class="[
                        'border-b-2 pb-3 text-sm font-semibold transition',
                        activeTab === tab.id
                            ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                            : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300',
                    ]"
                    @click="activeTab = tab.id"
                >
                    {{ tab.label }}
                </button>
            </nav>
        </div>

        <!-- Overview tab -->
        <div v-show="activeTab === 'overview'" class="space-y-6">
            <div class="grid gap-6 lg:grid-cols-3">
                <Panel title="Configuration" class="lg:col-span-2">
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold uppercase text-slate-500">Campaign</dt>
                            <dd class="mt-1">
                                <Link
                                    v-if="delivery.campaign"
                                    :href="route('campaigns.show', delivery.campaign.id)"
                                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                >
                                    {{ delivery.campaign.name }} →
                                </Link>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase text-slate-500">Buyer</dt>
                            <dd class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ delivery.buyer?.name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase text-slate-500">Routing</dt>
                            <dd class="mt-1 text-sm capitalize text-slate-700 dark:text-slate-300">
                                Priority {{ delivery.priority }}
                                <span v-if="delivery.routing_mode"> · {{ delivery.routing_mode.replace(/_/g, ' ') }}</span>
                                <span v-if="delivery.weight && delivery.weight !== 100"> · Weight {{ delivery.weight }}</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase text-slate-500">Trigger</dt>
                            <dd class="mt-1 text-sm capitalize text-slate-700 dark:text-slate-300">{{ delivery.trigger_type?.replace(/_/g, ' ') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase text-slate-500">Pricing</dt>
                            <dd class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                                <span class="capitalize">{{ delivery.revenue_type?.replace(/_/g, ' ') }}</span>
                                <span v-if="delivery.revenue_type === 'fixed'" class="ml-1 font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{ formatMoney(delivery.revenue_amount) }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase text-slate-500">Last updated</dt>
                            <dd class="mt-1"><FormattedDate :value="delivery.updated_at" /></dd>
                        </div>
                    </dl>
                </Panel>

                <Panel v-if="methodGuide" title="How it works">
                    <p class="text-sm font-medium text-slate-900 dark:text-white">{{ methodGuide.title }}</p>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ methodGuide.summary }}</p>
                    <p class="mt-3 text-xs text-slate-500">{{ methodGuide.when }}</p>
                </Panel>
            </div>

            <Panel title="Performance stats (24h)">
                <dl class="grid gap-4 sm:grid-cols-4">
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Attempts</dt>
                        <dd class="mt-1 text-lg font-bold text-slate-900 dark:text-white">{{ stats?.last_24h_total ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Successes</dt>
                        <dd class="mt-1 text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ stats?.last_24h_success ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Avg revenue</dt>
                        <dd class="mt-1 text-lg font-bold text-slate-900 dark:text-white">{{ formatMoney(stats?.avg_revenue ?? 0) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Avg duration</dt>
                        <dd class="mt-1 text-lg font-bold text-slate-900 dark:text-white">{{ stats?.avg_duration_ms ? `${stats.avg_duration_ms}ms` : '—' }}</dd>
                    </div>
                </dl>
            </Panel>
        </div>

        <!-- Logs tab -->
        <div v-show="activeTab === 'logs'">
            <div
                v-if="testLeadUuid"
                class="mb-4 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-100"
            >
                Test used lead <code class="rounded bg-white/80 px-1 font-mono text-xs dark:bg-slate-900">{{ testLeadUuid }}</code>.
                Expand the newest log row below for ping/post request and response payloads.
                <Link v-if="testLeadId" :href="route('leads.show', testLeadId)" class="ml-1 font-semibold underline">View lead →</Link>
                ·
                <Link :href="route('logs.delivery', { delivery_id: delivery.id })" class="font-semibold underline">All delivery logs →</Link>
            </div>
            <Panel title="Delivery logs" :padding="false">
                <DataTable :empty="!logRows().length" empty-message="No delivery logs yet.">
                    <template #head>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 w-8" />
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">When</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Lead</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Revenue</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Duration</th>
                    </template>
                    <template v-for="log in logRows()" :key="log.id">
                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-6 py-4">
                                <button
                                    v-if="log.ping_request || log.ping_response || log.post_request || log.post_response"
                                    type="button"
                                    class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400"
                                    @click="toggleLogExpand(log.id)"
                                >
                                    {{ expandedLogId === log.id ? '▼' : '▶' }}
                                </button>
                            </td>
                            <td class="px-6 py-4"><FormattedDate :value="log.created_at" /></td>
                            <td class="px-6 py-4">
                                <Link
                                    v-if="log.lead"
                                    :href="route('leads.show', log.lead.id)"
                                    class="font-mono text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                >
                                    {{ log.lead.uuid?.slice(0, 12) }}…
                                </Link>
                            </td>
                            <td class="px-6 py-4"><StatusBadge :status="log.status" /></td>
                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ log.revenue ? formatMoney(log.revenue) : '—' }}</td>
                            <td class="px-6 py-4 text-xs text-slate-500">{{ log.duration_ms ? `${log.duration_ms}ms` : '—' }}</td>
                        </tr>
                        <tr v-if="expandedLogId === log.id" class="bg-slate-50 dark:bg-slate-800/30">
                            <td colspan="6" class="px-6 py-4">
                                <div class="grid gap-4 lg:grid-cols-2">
                                    <div v-if="log.ping_request">
                                        <p class="mb-1 text-xs font-semibold uppercase text-cyan-600 dark:text-cyan-400">Ping request</p>
                                        <pre class="max-h-48 overflow-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-300">{{ formatJson(log.ping_request) }}</pre>
                                    </div>
                                    <div v-if="log.ping_response">
                                        <p class="mb-1 text-xs font-semibold uppercase text-cyan-600 dark:text-cyan-400">Ping response</p>
                                        <pre class="max-h-48 overflow-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-300">{{ formatJson(log.ping_response) }}</pre>
                                    </div>
                                    <div v-if="log.post_request">
                                        <p class="mb-1 text-xs font-semibold uppercase text-indigo-600 dark:text-indigo-400">Post request</p>
                                        <pre class="max-h-48 overflow-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-300">{{ formatJson(log.post_request) }}</pre>
                                    </div>
                                    <div v-if="log.post_response">
                                        <p class="mb-1 text-xs font-semibold uppercase text-indigo-600 dark:text-indigo-400">Post response</p>
                                        <pre class="max-h-48 overflow-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-300">{{ formatJson(log.post_response) }}</pre>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </DataTable>
                <div v-if="recentLogs?.links?.length > 3" class="flex justify-center gap-1 border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                    <Link
                        v-for="link in recentLogs.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        :class="[
                            'rounded-lg px-3 py-1.5 text-sm',
                            link.active ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
                            !link.url && 'pointer-events-none opacity-40',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </Panel>
        </div>

        <!-- Routing tab -->
        <div v-show="activeTab === 'routing'" class="space-y-6">
            <Panel title="Ping tree placement">
                <div v-if="pingTreeLinks?.length" class="space-y-3">
                    <div
                        v-for="link in pingTreeLinks"
                        :key="`${link.config_id}-${link.tier}`"
                        class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50"
                    >
                        <div>
                            <p class="font-medium text-slate-900 dark:text-white">{{ link.config_name }}</p>
                            <p class="text-sm text-slate-500">
                                Tier {{ link.tier }} · {{ link.group_name }}
                                <span class="capitalize"> · {{ link.mode?.replace(/_/g, ' ') }}</span>
                            </p>
                        </div>
                        <Link
                            :href="route('distribution.show', link.config_id)"
                            class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                        >
                            View ping tree →
                        </Link>
                    </div>
                </div>
                <p v-else class="text-sm text-slate-600 dark:text-slate-400">
                    This delivery is not assigned to any ping tree tier.
                    <span v-if="delivery.advanced_distribution_only" class="font-medium text-amber-600 dark:text-amber-400">
                        Warning: advanced distribution only is enabled.
                    </span>
                </p>
            </Panel>

            <Panel title="Routing settings">
                <dl class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Priority</dt>
                        <dd class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ delivery.priority }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Routing mode</dt>
                        <dd class="mt-1 text-sm capitalize text-slate-700 dark:text-slate-300">{{ delivery.routing_mode?.replace(/_/g, ' ') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Weight</dt>
                        <dd class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ delivery.weight ?? 100 }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Tier</dt>
                        <dd class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ delivery.tier ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Advanced only</dt>
                        <dd class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ delivery.advanced_distribution_only ? 'Yes' : 'No' }}</dd>
                    </div>
                </dl>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
