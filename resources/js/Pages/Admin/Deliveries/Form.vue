<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import CampaignWorkflowNav from '@/Components/UI/CampaignWorkflowNav.vue';
import EligibilityRulesEditor from '@/Components/UI/EligibilityRulesEditor.vue';
import ScheduleEditor from '@/Components/UI/ScheduleEditor.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    delivery: Object,
    campaignContext: Object,
    filterFieldOptions: Array,
    campaigns: Array,
    buyers: Array,
    verticals: Array,
    methodGuides: Object,
    routingModes: Array,
    revenueTypes: Array,
});

const steps = [
    { id: 'basics', label: 'Basics', num: 1 },
    { id: 'method', label: 'Method', num: 2 },
    { id: 'config', label: 'Connection', num: 3 },
    { id: 'routing', label: 'Routing', num: 4 },
    { id: 'filters', label: 'Filters', num: 5 },
    { id: 'pricing', label: 'Pricing', num: 6 },
    { id: 'caps', label: 'Caps', num: 7 },
    { id: 'schedule', label: 'Schedule', num: 8 },
];

const currentStep = ref('basics');

const stepIndex = (id) => steps.findIndex((s) => s.id === id);

const maxStepReached = ref(props.delivery ? steps.length - 1 : 0);

const goStep = (id) => {
    const targetIdx = stepIndex(id);
    const currentIdx = stepIndex(currentStep.value);

    if (targetIdx > currentIdx) {
        maxStepReached.value = Math.max(maxStepReached.value, targetIdx);
    }

    currentStep.value = id;
};

const stepStatus = (id) => {
    const idx = stepIndex(id);
    const currentIdx = stepIndex(currentStep.value);

    if (id === currentStep.value) {
        return 'active';
    }

    if (idx < currentIdx || (props.delivery && idx <= maxStepReached.value)) {
        return 'complete';
    }

    return 'pending';
};

const defaultConfig = () => ({
    url: '',
    http_method: 'POST',
    timeout: 10,
    ping_url: '',
    post_url: '',
    ping_timeout: 5,
    revenue_field: 'Cost',
    bid_hint: '',
    redirect_url: '',
    accept_url: '',
    to: '',
    subject: 'New Lead: [firstname] [lastname]',
    body: 'Lead received:\nEmail: [email]\nPhone: [phone1]\nPostcode: [zipcode]',
    message: 'New lead: [firstname] from [zipcode]',
    custom_post_data: '{}',
});

const form = useForm({
    campaign_id: props.delivery?.campaign_id ?? props.campaigns?.[0]?.id ?? '',
    buyer_id: props.delivery?.buyer_id ?? '',
    name: props.delivery?.name ?? '',
    method: props.delivery?.method ?? 'store_lead',
    trigger_type: props.delivery?.trigger_type ?? 'on_lead_arrival',
    status: props.delivery?.status ?? 'active',
    priority: props.delivery?.priority ?? 100,
    weight: props.delivery?.weight ?? 100,
    tier: props.delivery?.tier ?? 1,
    routing_mode: props.delivery?.routing_mode ?? '',
    revenue_type: props.delivery?.revenue_type ?? 'fixed',
    revenue_amount: props.delivery?.revenue_amount ?? 15,
    revenue_rules: props.delivery?.revenue_rules?.length
        ? props.delivery.revenue_rules
        : [{ field: 'state', value: '', amount: 20 }],
    advanced_distribution_only: props.delivery?.advanced_distribution_only ?? false,
    config: { ...defaultConfig(), ...(props.delivery?.config ?? {}) },
    caps: {
        daily: props.delivery?.caps?.daily ?? '',
        hourly: props.delivery?.caps?.hourly ?? '',
        weekly: props.delivery?.caps?.weekly ?? '',
        monthly: props.delivery?.caps?.monthly ?? '',
        min_bid: props.delivery?.caps?.min_bid ?? '',
        max_bid: props.delivery?.caps?.max_bid ?? '',
        daily_spend_cap: props.delivery?.caps?.daily_spend_cap ?? '',
        monthly_spend_cap: props.delivery?.caps?.monthly_spend_cap ?? '',
    },
    eligibility_rules: props.delivery?.eligibility_rules ?? { operator: 'and', conditions: [] },
    location_filter: {
        states: (props.delivery?.location_filter?.states ?? []).join(', '),
        zip_prefixes: (props.delivery?.location_filter?.zip_prefixes ?? []).join(', '),
        exclude_states: (props.delivery?.location_filter?.exclude_states ?? []).join(', '),
    },
    schedule: props.delivery?.schedule?.windows?.length
        ? props.delivery.schedule
        : { timezone: 'Europe/London', windows: [{ day: 'all', start: '00:00', end: '23:59' }] },
});

const selectedGuide = computed(() => props.methodGuides?.[form.method]);
const selectedRevenueHelp = computed(() => props.revenueTypes?.find((r) => r.value === form.revenue_type)?.help);
const selectedRoutingHelp = computed(() => props.routingModes?.find((r) => r.value === form.routing_mode)?.help);
const selectedCampaign = computed(() => props.campaigns?.find((c) => c.id === form.campaign_id));
const workflowCampaign = computed(() => {
    if (props.campaignContext) {
        return {
            id: props.campaignContext.id,
            name: props.campaignContext.name,
            reference: props.campaignContext.reference,
        };
    }
    const c = selectedCampaign.value;
    return c ? { id: c.id, name: c.name, reference: c.reference } : null;
});
const selectedBuyer = computed(() => props.buyers?.find((b) => b.id === form.buyer_id));

const methods = ['store_lead', 'direct_post', 'ping_post', 'email_ping_post', 'email', 'sms'];

const configPreview = computed(() => {
    if (form.method === 'ping_post') {
        return {
            ping_url: form.config.ping_url,
            post_url: form.config.post_url,
            revenue_field: form.config.revenue_field,
            redirect_url: form.config.redirect_url,
            accept_url: form.config.accept_url,
        };
    }
    if (form.method === 'direct_post') {
        return { url: form.config.url, method: form.config.http_method, timeout: form.config.timeout };
    }
    return form.config;
});

const addRule = () => form.revenue_rules.push({ field: '', value: '', amount: 15 });
const removeRule = (i) => form.revenue_rules.splice(i, 1);

const submit = () => {
    const payload = {
        ...form.data(),
        location_filter: {
            states: form.location_filter.states.split(',').map((s) => s.trim()).filter(Boolean),
            zip_prefixes: form.location_filter.zip_prefixes.split(',').map((s) => s.trim()).filter(Boolean),
            exclude_states: form.location_filter.exclude_states.split(',').map((s) => s.trim()).filter(Boolean),
        },
    };

    if (props.delivery) {
        form.transform(() => payload).put(route('deliveries.update', props.delivery.id));
    } else {
        form.transform(() => payload).post(route('deliveries.store'));
    }
};
</script>

<template>
    <Head :title="delivery ? 'Edit Delivery' : 'New Delivery'" />
    <AuthenticatedLayout>
        <PageHeader :title="delivery ? 'Edit Delivery' : 'New Delivery'" description="Step-by-step setup — choose method, connect buyer, set pricing.">
            <template #actions>
                <AppButton v-if="delivery" :href="route('deliveries.show', delivery.id)" variant="secondary">View stats</AppButton>
                <AppButton v-if="delivery" :href="route('deliveries.test', delivery.id)" method="post" variant="secondary">Test delivery</AppButton>
            </template>
        </PageHeader>

        <CampaignWorkflowNav
            v-if="workflowCampaign"
            :campaign="workflowCampaign"
            current="deliveries"
            :distribution-config-id="campaignContext?.active_distribution_config_id"
        />

        <Panel v-if="campaignContext" title="Where this delivery fits" class="mb-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-slate-500">Campaign</dt>
                        <dd class="font-medium">
                            <Link :href="route('campaigns.show', campaignContext.id)" class="text-indigo-600 hover:underline">{{ campaignContext.name }}</Link>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Ping tree</dt>
                        <dd class="font-medium">
                            <template v-if="campaignContext.active_distribution_config_id">
                                <Link :href="route('distribution.show', campaignContext.active_distribution_config_id)" class="text-indigo-600 hover:underline">
                                    {{ campaignContext.active_distribution_config_name }}
                                </Link>
                                <span v-if="campaignContext.tier_in_config" class="ml-1 text-slate-500">· Tier {{ campaignContext.tier_in_config }}</span>
                                <span v-else class="ml-1 text-amber-600">· Not assigned to a tier yet</span>
                            </template>
                            <Link v-else :href="route('distribution.create') + '?campaign_id=' + campaignContext.id" class="text-amber-600 hover:underline">Create ping tree →</Link>
                        </dd>
                    </div>
                </dl>
                <div class="flex flex-wrap gap-2">
                    <AppButton v-if="campaignContext.active_distribution_config_id" :href="route('distribution.edit', campaignContext.active_distribution_config_id)" variant="secondary">Edit ping tree</AppButton>
                    <AppButton :href="route('campaigns.api-spec', campaignContext.id)" variant="secondary">Lead ingest API</AppButton>
                    <AppButton :href="route('deliveries.index', { campaign_id: campaignContext.id })" variant="secondary">All campaign deliveries</AppButton>
                </div>
            </div>
            <p class="mt-3 text-xs text-slate-500">
                This page configures the <strong>buyer ping/post API</strong> for one delivery. Add it to a tier on the ping tree, or set per-delivery filters below.
            </p>
        </Panel>

        <div class="grid gap-6 lg:grid-cols-12">
            <!-- Step sidebar -->
            <aside class="lg:col-span-3">
                <Panel title="Setup steps">
                    <ol class="space-y-1">
                        <li v-for="s in steps" :key="s.id">
                            <button
                                type="button"
                                :class="[
                                    'flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm transition',
                                    stepStatus(s.id) === 'active'
                                        ? 'bg-indigo-100 font-semibold text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200'
                                        : stepStatus(s.id) === 'complete'
                                            ? 'text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950/30'
                                            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
                                ]"
                                @click="goStep(s.id)"
                            >
                                <span
                                    :class="[
                                        'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                        stepStatus(s.id) === 'active'
                                            ? 'bg-indigo-600 text-white'
                                            : stepStatus(s.id) === 'complete'
                                                ? 'bg-emerald-500 text-white'
                                                : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                                    ]"
                                >
                                    <svg v-if="stepStatus(s.id) === 'complete'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span v-else>{{ s.num }}</span>
                                </span>
                                {{ s.label }}
                            </button>
                        </li>
                    </ol>
                </Panel>

                <Panel v-if="selectedBuyer || selectedCampaign" title="Summary" class="mt-4">
                    <dl class="space-y-2 text-sm">
                        <div v-if="selectedCampaign"><dt class="text-slate-500">Campaign</dt><dd class="font-medium">{{ selectedCampaign.name }}</dd></div>
                        <div v-if="selectedBuyer"><dt class="text-slate-500">Buyer</dt><dd class="font-medium">{{ selectedBuyer.name }}</dd></div>
                        <div><dt class="text-slate-500">Method</dt><dd class="font-medium">{{ selectedGuide?.title ?? form.method }}</dd></div>
                        <div><dt class="text-slate-500">Tier</dt><dd class="font-medium">{{ form.tier }}</dd></div>
                    </dl>
                </Panel>

                <Panel v-if="form.method === 'ping_post'" title="Ping → Post flow" class="mt-4">
                    <div class="space-y-2 text-xs text-slate-600 dark:text-slate-400">
                        <div class="flex items-center gap-2 rounded-lg bg-violet-50 p-2 dark:bg-violet-950/30">
                            <span class="font-bold text-violet-600">1</span> Ping partial fields
                        </div>
                        <div class="text-center text-slate-400">↓</div>
                        <div class="flex items-center gap-2 rounded-lg bg-indigo-50 p-2 dark:bg-indigo-950/30">
                            <span class="font-bold text-indigo-600">2</span> Buyer returns bid
                        </div>
                        <div class="text-center text-slate-400">↓</div>
                        <div class="flex items-center gap-2 rounded-lg bg-emerald-50 p-2 dark:bg-emerald-950/30">
                            <span class="font-bold text-emerald-600">3</span> Post full lead to winner
                        </div>
                    </div>
                </Panel>
            </aside>

            <form class="space-y-6 lg:col-span-9" @submit.prevent="submit">
                <FormErrorSummary :errors="form.errors" />

                <Panel v-show="currentStep === 'basics'" title="1. Basics">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <InputLabel value="Delivery name" />
                            <TextInput v-model="form.name" class="mt-1 w-full" placeholder="e.g. Tier 3 — Hastings Direct" required />
                            <InputError class="mt-1" :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel value="Campaign" />
                            <select v-model="form.campaign_id" class="form-select w-full" required>
                                <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.vertical_label }} — {{ c.name }}</option>
                            </select>
                            <p v-if="selectedCampaign" class="mt-1 text-xs text-slate-500">Floor £{{ selectedCampaign.floor_price }} · {{ selectedCampaign.bidding_mode?.replace(/_/g, ' ') }}</p>
                        </div>
                        <div>
                            <InputLabel value="Buyer" />
                            <select v-model="form.buyer_id" class="form-select w-full">
                                <option value="">None</option>
                                <option v-for="b in buyers" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Status" />
                            <select v-model="form.status" class="form-select w-full">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="saved">Saved (draft)</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Trigger" />
                            <select v-model="form.trigger_type" class="form-select w-full">
                                <option value="on_lead_arrival">On lead arrival</option>
                                <option value="manual_via_api">Manual via API</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <AppButton type="button" @click="goStep('method')">Next: Method →</AppButton>
                    </div>
                </Panel>

                <Panel v-show="currentStep === 'method'" title="2. Delivery method">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <button
                            v-for="m in methods"
                            :key="m"
                            type="button"
                            :class="['rounded-xl border p-4 text-left transition', form.method === m ? 'border-indigo-400 bg-indigo-50 ring-2 ring-indigo-200 dark:bg-indigo-950/40' : 'border-slate-200 hover:border-slate-300 dark:border-slate-700']"
                            @click="form.method = m"
                        >
                            <p class="font-semibold text-slate-900 dark:text-white">{{ methodGuides[m]?.title }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ methodGuides[m]?.summary }}</p>
                        </button>
                    </div>
                    <div v-if="selectedGuide" class="mt-4 rounded-xl border border-cyan-500/30 bg-cyan-500/5 p-4 text-sm text-slate-600 dark:text-slate-400">{{ selectedGuide.when }}</div>
                    <div class="mt-4 flex justify-between">
                        <AppButton type="button" variant="secondary" @click="goStep('basics')">← Back</AppButton>
                        <AppButton type="button" @click="goStep('config')">Next: Connection →</AppButton>
                    </div>
                </Panel>

                <Panel v-show="currentStep === 'config'" :title="'3. ' + (selectedGuide?.title ?? 'Connection') + ' settings'">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="space-y-4">
                            <div v-if="form.method === 'direct_post'">
                                <InputLabel value="Post URL" />
                                <TextInput v-model="form.config.url" class="mt-1 w-full" placeholder="https://buyer-crm.com/leads" />
                                <div class="mt-3 grid grid-cols-2 gap-3">
                                    <div><InputLabel value="HTTP method" /><select v-model="form.config.http_method" class="form-select mt-1 w-full"><option value="POST">POST</option><option value="PUT">PUT</option></select></div>
                                    <div><InputLabel value="Timeout (sec)" /><TextInput v-model="form.config.timeout" type="number" class="mt-1 w-full" /></div>
                                </div>
                            </div>
                            <div v-else-if="form.method === 'ping_post'" class="space-y-3">
                                <div><InputLabel value="Ping URL" /><TextInput v-model="form.config.ping_url" class="mt-1 w-full" /></div>
                                <div><InputLabel value="Post URL" /><TextInput v-model="form.config.post_url" class="mt-1 w-full" /></div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div><InputLabel value="Ping timeout" /><TextInput v-model="form.config.ping_timeout" type="number" class="mt-1 w-full" /></div>
                                    <div><InputLabel value="Post timeout" /><TextInput v-model="form.config.timeout" type="number" class="mt-1 w-full" /></div>
                                    <div><InputLabel value="Revenue field" /><TextInput v-model="form.config.revenue_field" class="mt-1 w-full" placeholder="Cost" /></div>
                                    <div><InputLabel value="Bid hint (demo)" /><TextInput v-model="form.config.bid_hint" type="number" step="0.01" class="mt-1 w-full" /></div>
                                </div>
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div>
                                        <InputLabel value="Redirect URL (on accept)" />
                                        <TextInput v-model="form.config.redirect_url" class="mt-1 w-full" placeholder="https://buyer.com/thank-you" />
                                        <p class="mt-1 text-xs text-slate-500">Returned to supplier API when lead is sold via this delivery.</p>
                                    </div>
                                    <div>
                                        <InputLabel value="Accept URL (fallback)" />
                                        <TextInput v-model="form.config.accept_url" class="mt-1 w-full" placeholder="https://buyer.com/accept" />
                                        <p class="mt-1 text-xs text-slate-500">Used if redirect URL is empty.</p>
                                    </div>
                                </div>
                            </div>
                            <div v-else-if="form.method === 'store_lead'" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600 dark:bg-slate-800/50">No URL needed — lead appears in buyer portal.</div>
                            <div v-else-if="form.method === 'email'" class="space-y-3">
                                <div><InputLabel value="Email to" /><TextInput v-model="form.config.to" type="email" class="mt-1 w-full" /></div>
                                <div><InputLabel value="Subject" /><TextInput v-model="form.config.subject" class="mt-1 w-full" /></div>
                                <div><InputLabel value="Body" /><textarea v-model="form.config.body" rows="4" class="form-input mt-1 w-full font-mono text-sm" /></div>
                            </div>
                        </div>
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase text-slate-500">Live config preview</p>
                            <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-emerald-300">{{ JSON.stringify(configPreview, null, 2) }}</pre>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-between">
                        <AppButton type="button" variant="secondary" @click="goStep('method')">← Back</AppButton>
                        <AppButton type="button" @click="goStep('routing')">Next: Routing →</AppButton>
                    </div>
                </Panel>

                <Panel v-show="currentStep === 'routing'" title="4. Routing & priority">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Routing mode" />
                            <select v-model="form.routing_mode" class="form-select w-full">
                                <option value="">Standard waterfall</option>
                                <option v-for="r in routingModes" :key="r.value" :value="r.value">{{ r.label }}</option>
                            </select>
                            <p v-if="selectedRoutingHelp" class="mt-1 text-xs text-slate-500">{{ selectedRoutingHelp }}</p>
                        </div>
                        <div><InputLabel value="Priority" /><TextInput v-model="form.priority" type="number" class="mt-1 w-full" /></div>
                        <div><InputLabel value="Weight" /><TextInput v-model="form.weight" type="number" class="mt-1 w-full" /></div>
                        <div><InputLabel value="Ping-tree tier" /><TextInput v-model="form.tier" type="number" min="1" class="mt-1 w-full" /></div>
                    </div>
                    <label class="mt-4 flex items-start gap-3">
                        <input v-model="form.advanced_distribution_only" type="checkbox" class="mt-1 rounded" />
                        <span class="text-sm text-slate-600 dark:text-slate-400"><strong>Advanced distribution only</strong> — use inside Ping Tree tiers only.</span>
                    </label>
                    <div class="mt-4 flex justify-between">
                        <AppButton type="button" variant="secondary" @click="goStep('config')">← Back</AppButton>
                        <AppButton type="button" @click="goStep('filters')">Next: Filters →</AppButton>
                    </div>
                </Panel>

                <Panel v-show="currentStep === 'filters'" title="5. Eligibility filters">
                    <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                        Optional field rules — only leads matching these conditions will be pinged/posted to this buyer API.
                        Tier-level filters can also be set on the <Link :href="campaignContext?.active_distribution_config_id ? route('distribution.edit', campaignContext.active_distribution_config_id) : route('distribution.index')" class="text-indigo-600 hover:underline">ping tree</Link>.
                    </p>
                    <EligibilityRulesEditor v-model="form.eligibility_rules" :field-options="filterFieldOptions ?? []" />
                    <div class="mt-6 grid gap-4 md:grid-cols-3">
                        <div>
                            <InputLabel value="Include states (comma-separated)" />
                            <TextInput v-model="form.location_filter.states" class="mt-1 w-full" placeholder="CA, TX, FL" />
                            <p class="mt-1 text-xs text-slate-500">Only distribute when lead state matches.</p>
                        </div>
                        <div>
                            <InputLabel value="ZIP / postcode prefixes" />
                            <TextInput v-model="form.location_filter.zip_prefixes" class="mt-1 w-full" placeholder="902, SW1" />
                            <p class="mt-1 text-xs text-slate-500">Match leads by postcode prefix.</p>
                        </div>
                        <div>
                            <InputLabel value="Exclude states" />
                            <TextInput v-model="form.location_filter.exclude_states" class="mt-1 w-full" placeholder="NY, AK" />
                            <p class="mt-1 text-xs text-slate-500">Block leads from these states.</p>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-between">
                        <AppButton type="button" variant="secondary" @click="goStep('routing')">← Back</AppButton>
                        <AppButton type="button" @click="goStep('pricing')">Next: Pricing →</AppButton>
                    </div>
                </Panel>

                <Panel v-show="currentStep === 'pricing'" title="6. Pricing">
                    <div class="grid gap-2 sm:grid-cols-3">
                        <button v-for="rt in revenueTypes" :key="rt.value" type="button" :class="['rounded-xl border p-3 text-left text-sm', form.revenue_type === rt.value ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-950/40' : 'border-slate-200 dark:border-slate-700']" @click="form.revenue_type = rt.value">
                            <span class="font-medium">{{ rt.label }}</span>
                        </button>
                    </div>
                    <p v-if="selectedRevenueHelp" class="mt-2 text-xs text-slate-500">{{ selectedRevenueHelp }}</p>
                    <div v-if="form.revenue_type === 'fixed'" class="mt-4 max-w-xs">
                        <InputLabel value="Fixed amount" /><TextInput v-model="form.revenue_amount" type="number" step="0.01" class="mt-1 w-full" />
                    </div>
                    <div v-if="form.revenue_type === 'rule_based'" class="mt-4 space-y-3">
                        <div v-for="(rule, i) in form.revenue_rules" :key="i" class="flex flex-wrap gap-3 rounded-xl border p-3 dark:border-slate-700">
                            <TextInput v-model="rule.field" placeholder="field" />
                            <TextInput v-model="rule.value" placeholder="value" />
                            <TextInput v-model="rule.amount" type="number" placeholder="price" />
                            <button type="button" class="text-rose-500 text-sm" @click="removeRule(i)">Remove</button>
                        </div>
                        <button type="button" class="text-sm text-indigo-600" @click="addRule">+ Add rule</button>
                    </div>
                    <div class="mt-4 flex justify-between">
                        <AppButton type="button" variant="secondary" @click="goStep('filters')">← Back</AppButton>
                        <AppButton type="button" @click="goStep('caps')">Next: Caps →</AppButton>
                    </div>
                </Panel>

                <Panel v-show="currentStep === 'caps'" title="7. Volume & pricing caps">
                    <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">Limit lead volume and buyer spend. Pricing caps apply to ping/post auctions.</p>
                    <div class="grid max-w-3xl grid-cols-2 gap-4 md:grid-cols-4">
                        <div><InputLabel value="Daily cap" /><TextInput v-model="form.caps.daily" type="number" class="mt-1 w-full" placeholder="Unlimited" /></div>
                        <div><InputLabel value="Hourly cap" /><TextInput v-model="form.caps.hourly" type="number" class="mt-1 w-full" placeholder="Unlimited" /></div>
                        <div><InputLabel value="Weekly cap" /><TextInput v-model="form.caps.weekly" type="number" class="mt-1 w-full" placeholder="Unlimited" /></div>
                        <div><InputLabel value="Monthly cap" /><TextInput v-model="form.caps.monthly" type="number" class="mt-1 w-full" placeholder="Unlimited" /></div>
                    </div>
                    <div class="mt-6 grid max-w-3xl grid-cols-2 gap-4 md:grid-cols-4">
                        <div><InputLabel value="Min bid (£)" /><TextInput v-model="form.caps.min_bid" type="number" step="0.01" class="mt-1 w-full" placeholder="Floor" /></div>
                        <div><InputLabel value="Max bid (£)" /><TextInput v-model="form.caps.max_bid" type="number" step="0.01" class="mt-1 w-full" placeholder="Ceiling" /></div>
                        <div><InputLabel value="Daily spend cap" /><TextInput v-model="form.caps.daily_spend_cap" type="number" step="0.01" class="mt-1 w-full" placeholder="Unlimited" /></div>
                        <div><InputLabel value="Monthly spend cap" /><TextInput v-model="form.caps.monthly_spend_cap" type="number" step="0.01" class="mt-1 w-full" placeholder="Unlimited" /></div>
                    </div>
                    <div class="mt-6 flex justify-between">
                        <AppButton type="button" variant="secondary" @click="goStep('pricing')">← Back</AppButton>
                        <AppButton type="button" @click="goStep('schedule')">Next: Schedule →</AppButton>
                    </div>
                </Panel>

                <Panel v-show="currentStep === 'schedule'" title="8. Delivery schedule">
                    <ScheduleEditor v-model="form.schedule" />
                    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                        <AppButton type="button" variant="secondary" @click="goStep('caps')">← Back</AppButton>
                        <PrimaryButton :disabled="form.processing" :loading="form.processing">{{ delivery ? 'Update' : 'Create' }} Delivery</PrimaryButton>
                    </div>
                </Panel>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
