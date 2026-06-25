<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import FormSetupLayout from '@/Components/UI/FormSetupLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import TextInput from '@/Components/TextInput.vue';
import CampaignWorkflowNav from '@/Components/UI/CampaignWorkflowNav.vue';
import { useFormSteps } from '@/Composables/useFormSteps';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    campaign: Object,
    defaults: { type: Object, default: () => ({ country: 'GB', currency: 'GBP' }) },
    verticals: { type: Array, default: () => [] },
    biddingModes: { type: Array, default: () => [] },
    tenantHub: { type: Object, default: null },
    campaignWorkflow: { type: Object, default: null },
    activeDistributionConfigId: { type: [Number, String], default: null },
});

const page = usePage();
const account = computed(() => page.props.auth.account);

const steps = [
    { id: 'identity', label: 'Identity', num: 1 },
    { id: 'pricing', label: 'Pricing', num: 2 },
    { id: 'routing', label: 'Routing', num: 3 },
    { id: 'caps', label: 'Caps & status', num: 4 },
];

const { currentStep, goStep, stepStatus, nextStep, prevStep } = useFormSteps(steps, {
    isEdit: !!props.campaign,
});

const form = useForm({
    name: props.campaign?.name ?? '',
    reference: props.campaign?.reference ?? '',
    type: props.campaign?.type ?? 'standard',
    country: props.campaign?.country ?? props.defaults.country ?? account.value?.default_country ?? 'GB',
    currency: props.campaign?.currency ?? props.defaults.currency ?? account.value?.default_currency ?? 'GBP',
    status: props.campaign?.status ?? 'active',
    vertical_id: props.campaign?.vertical_id ?? '',
    payout_amount: props.campaign?.payout_amount ?? 5,
    floor_price: props.campaign?.floor_price ?? 10,
    bidding_mode: props.campaign?.bidding_mode ?? 'real_time_auction',
    sell_mode: props.campaign?.sell_mode ?? 'exclusive',
    use_advanced_distribution: props.campaign?.use_advanced_distribution ?? false,
    caps: {
        daily: props.campaign?.caps?.daily ?? '',
        hourly: props.campaign?.caps?.hourly ?? '',
    },
});

const currencies = ['GBP', 'USD', 'EUR', 'AUD', 'CAD', 'NZD', 'ZAR', 'INR', 'AED'];
const countries = {
    GB: 'United Kingdom', US: 'United States', CA: 'Canada', AU: 'Australia',
    DE: 'Germany', FR: 'France', IE: 'Ireland', NL: 'Netherlands', ZA: 'South Africa',
};

const selectedVertical = computed(() => props.verticals.find((v) => String(v.id) === String(form.vertical_id)));
const selectedBiddingMode = computed(() => props.biddingModes.find((m) => m.value === form.bidding_mode));

const normalizeCodes = () => {
    form.country = String(form.country).toUpperCase().slice(0, 2);
    form.currency = String(form.currency).toUpperCase().slice(0, 3);
    form.reference = String(form.reference).toLowerCase().replace(/[^a-z0-9_-]/g, '');
};

const submit = () => {
    normalizeCodes();
    if (props.campaign) {
        form.put(route('campaigns.update', props.campaign.id));
    } else {
        form.post(route('campaigns.store'));
    }
};
</script>

<template>
    <Head :title="campaign ? 'Edit Campaign' : 'New Campaign'" />
    <AuthenticatedLayout>
        <PageHeader
            :title="campaign ? 'Edit Campaign' : 'New Campaign'"
            :description="campaign ? `Editing ${campaign.reference}` : 'Step-by-step setup — identity, pricing, routing, and volume caps.'"
        >
            <template v-if="campaign" #actions>
                <AppButton :href="route('campaigns.show', campaign.id)" variant="secondary">View campaign</AppButton>
            </template>
        </PageHeader>

        <CampaignWorkflowNav
            v-if="campaignWorkflow"
            :campaign="campaignWorkflow.campaign"
            :distribution-config-id="campaignWorkflow.distributionConfigId"
            :tenant-hub="campaignWorkflow.tenantHub"
            current="edit"
            class="mb-6"
        />

        <FormSetupLayout
            :steps="steps"
            :current-step="currentStep"
            :step-status="stepStatus"
            @go="goStep"
        >
            <template #sidebar>
                <Panel title="Summary" class="mt-4">
                    <dl class="space-y-2 text-sm">
                        <div v-if="form.name">
                            <dt class="text-slate-500">Name</dt>
                            <dd class="font-medium">{{ form.name }}</dd>
                        </div>
                        <div v-if="form.reference">
                            <dt class="text-slate-500">API ref</dt>
                            <dd class="font-mono text-xs font-medium">{{ form.reference }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Market</dt>
                            <dd class="font-medium">{{ form.country }} · {{ form.currency }}</dd>
                        </div>
                        <div v-if="selectedVertical">
                            <dt class="text-slate-500">Vertical</dt>
                            <dd class="font-medium">{{ selectedVertical.label }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Floor / payout</dt>
                            <dd class="font-medium">{{ form.currency }} {{ form.floor_price }} / {{ form.payout_amount }}</dd>
                        </div>
                    </dl>
                </Panel>

                <Panel v-if="!campaign" title="Platform defaults" class="mt-4">
                    <p class="text-xs text-slate-600 dark:text-slate-400">
                        Defaults from your platform: <strong>{{ account?.default_country ?? 'GB' }}</strong> /
                        <strong>{{ account?.default_currency ?? 'GBP' }}</strong>.
                        Change under Settings.
                    </p>
                </Panel>
            </template>

            <form class="space-y-6" @submit.prevent="submit">
                <FormErrorSummary :errors="form.errors" />

                <Panel v-show="currentStep === 'identity'" title="1. Campaign identity">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <InputLabel value="Campaign name" />
                            <TextInput v-model="form.name" class="mt-1 w-full" required placeholder="e.g. Auto Insurance Leads" />
                            <InputError class="mt-1" :message="form.errors.name" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel value="API reference" />
                            <TextInput
                                v-model="form.reference"
                                class="mt-1 w-full font-mono"
                                :disabled="!!campaign?.reference_locked"
                                required
                                placeholder="auto-insurance-leads"
                                maxlength="255"
                            />
                            <p class="mt-1 text-xs text-slate-500">
                                Lowercase letters, numbers, hyphens only. Used in API: <code class="text-indigo-500">campaign_ref</code>
                            </p>
                            <InputError class="mt-1" :message="form.errors.reference" />
                        </div>
                        <div>
                            <InputLabel value="Country (ISO)" />
                            <select v-model="form.country" class="form-select w-full">
                                <option v-for="(label, code) in countries" :key="code" :value="code">{{ code }} — {{ label }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.country" />
                        </div>
                        <div>
                            <InputLabel value="Currency (ISO)" />
                            <select v-model="form.currency" class="form-select w-full">
                                <option v-for="c in currencies" :key="c" :value="c">{{ c }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.currency" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel value="Vertical" />
                            <select v-model="form.vertical_id" class="form-select mt-1 w-full">
                                <option value="">General</option>
                                <option v-for="v in verticals" :key="v.id" :value="v.id">{{ v.label }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.vertical_id" />
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <AppButton type="button" @click="nextStep">Next: Pricing →</AppButton>
                    </div>
                </Panel>

                <Panel v-show="currentStep === 'pricing'" title="2. Pricing">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Payout amount" />
                            <TextInput v-model="form.payout_amount" type="number" step="0.01" min="0" class="mt-1 w-full" />
                            <p class="mt-1 text-xs text-slate-500">Amount paid to suppliers per accepted lead.</p>
                            <InputError class="mt-1" :message="form.errors.payout_amount" />
                        </div>
                        <div>
                            <InputLabel value="Floor price" />
                            <TextInput v-model="form.floor_price" type="number" step="0.01" min="0" class="mt-1 w-full" />
                            <p class="mt-1 text-xs text-slate-500">Minimum buyer bid in auctions; also used in waterfall pricing.</p>
                            <InputError class="mt-1" :message="form.errors.floor_price" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel value="Bidding mode" />
                            <select v-model="form.bidding_mode" class="form-select mt-1 w-full">
                                <option v-for="m in biddingModes" :key="m.value" :value="m.value">{{ m.label }}</option>
                            </select>
                            <p v-if="selectedBiddingMode?.help" class="mt-1 text-xs text-slate-500">{{ selectedBiddingMode.help }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-between">
                        <AppButton type="button" variant="secondary" @click="prevStep">← Back</AppButton>
                        <AppButton type="button" @click="nextStep">Next: Routing →</AppButton>
                    </div>
                </Panel>

                <Panel v-show="currentStep === 'routing'" title="3. Routing & distribution">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Sell mode" />
                            <select v-model="form.sell_mode" class="form-select mt-1 w-full">
                                <option value="exclusive">Exclusive (one buyer)</option>
                                <option value="multi_sell">Multi-sell</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">Whether a lead can be sold to multiple buyers.</p>
                        </div>
                    </div>
                    <label class="mt-4 flex items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <input v-model="form.use_advanced_distribution" type="checkbox" class="mt-1 rounded border-slate-300 text-indigo-600 dark:border-slate-600" />
                        <span class="text-sm text-slate-600 dark:text-slate-400">
                            <strong>Advanced distribution (ping tree / hybrid)</strong>
                            <span class="mt-1 block text-xs text-slate-500">
                                Enable tiered ping trees instead of simple sequential routing. Configure tiers after saving under Routing → Ping Tree.
                            </span>
                        </span>
                    </label>
                    <InputError class="mt-2" :message="form.errors.use_advanced_distribution" />
                    <div class="mt-4 flex justify-between">
                        <AppButton type="button" variant="secondary" @click="prevStep">← Back</AppButton>
                        <AppButton type="button" @click="nextStep">Next: Caps & status →</AppButton>
                    </div>
                </Panel>

                <Panel v-show="currentStep === 'caps'" title="4. Volume caps & status">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Status" />
                            <select v-model="form.status" class="form-select mt-1 w-full">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 grid max-w-lg grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Daily cap" />
                            <TextInput v-model="form.caps.daily" type="number" class="mt-1 w-full" placeholder="Unlimited" />
                        </div>
                        <div>
                            <InputLabel value="Hourly cap" />
                            <TextInput v-model="form.caps.hourly" type="number" class="mt-1 w-full" placeholder="Unlimited" />
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Campaign-level ingest limits. Leave blank for unlimited volume.</p>
                    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                        <AppButton type="button" variant="secondary" @click="prevStep">← Back</AppButton>
                        <PrimaryButton :disabled="form.processing" :loading="form.processing">
                            {{ campaign ? 'Update' : 'Create' }} Campaign
                        </PrimaryButton>
                    </div>
                </Panel>
            </form>
        </FormSetupLayout>
    </AuthenticatedLayout>
</template>
