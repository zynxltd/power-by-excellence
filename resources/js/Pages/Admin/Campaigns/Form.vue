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
import TenantContextBanner from '@/Components/UI/TenantContextBanner.vue';
import CampaignRowAvatar from '@/Components/Campaign/CampaignRowAvatar.vue';
import { useFormSteps } from '@/Composables/useFormSteps';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    campaign: Object,
    defaults: { type: Object, default: () => ({ country: 'GB', currency: 'GBP' }) },
    verticals: { type: Array, default: () => [] },
    biddingModes: { type: Array, default: () => [] },
    countries: { type: Object, default: () => ({}) },
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
    { id: 'caps', label: 'Caps & budget', num: 4 },
];

const { currentStep, goStep, stepStatus, nextStep, prevStep } = useFormSteps(steps, {
    isEdit: !!props.campaign,
});

const logoPreview = ref(props.campaign?.logo_url ?? null);

const form = useForm({
    name: props.campaign?.name ?? '',
    reference: props.campaign?.reference ?? '',
    type: props.campaign?.type ?? 'standard',
    country: props.campaign?.country ?? props.defaults.country ?? account.value?.default_country ?? 'GB',
    multi_geo: props.campaign?.multi_geo ?? false,
    geo_countries: props.campaign?.geo_countries ?? [],
    currency: props.campaign?.currency ?? props.defaults.currency ?? account.value?.default_currency ?? 'GBP',
    status: props.campaign?.status ?? 'active',
    vertical_id: props.campaign?.vertical_id ?? '',
    payout_amount: props.campaign?.payout_amount ?? 5,
    floor_price: props.campaign?.floor_price ?? 10,
    bidding_mode: props.campaign?.bidding_mode ?? 'real_time_auction',
    sell_mode: props.campaign?.sell_mode ?? 'exclusive',
    use_advanced_distribution: props.campaign?.use_advanced_distribution ?? false,
    logo: null,
    remove_logo: false,
    caps: {
        daily: props.campaign?.caps?.daily ?? '',
        hourly: props.campaign?.caps?.hourly ?? '',
        daily_spend_cap: props.campaign?.caps?.daily_spend_cap ?? '',
        monthly_spend_cap: props.campaign?.caps?.monthly_spend_cap ?? '',
    },
});

const capCurrency = computed(() => String(form.currency || props.campaign?.currency || account.value?.default_currency || 'GBP').toUpperCase());

const currencies = ['GBP', 'USD', 'EUR', 'AUD', 'CAD', 'NZD', 'ZAR', 'INR', 'AED'];

const regionPreview = computed(() => {
    if (form.multi_geo || (form.geo_countries?.length ?? 0) > 1) {
        return { emoji: '🌍', label: 'Multi-geo' };
    }

    const code = form.geo_countries?.[0] || form.country;
    const labels = props.countries;

    return {
        emoji: code && code.length === 2
            ? String.fromCodePoint(...[...code.toUpperCase()].map((char) => 0x1F1E6 + char.charCodeAt(0) - 65))
            : '🌍',
        label: labels[code] ?? code,
    };
});

const onLogoFile = (event) => {
    const file = event.target.files[0];
    if (!file) {
        return;
    }

    form.logo = file;
    form.remove_logo = false;
    logoPreview.value = URL.createObjectURL(file);
};

const removeLogo = () => {
    form.logo = null;
    form.remove_logo = true;
    logoPreview.value = null;
};

const toggleGeoCountry = (code) => {
    const selected = new Set(form.geo_countries ?? []);
    if (selected.has(code)) {
        selected.delete(code);
    } else {
        selected.add(code);
    }
    form.geo_countries = [...selected];
};

const selectedVertical = computed(() => props.verticals.find((v) => String(v.id) === String(form.vertical_id)));
const selectedBiddingMode = computed(() => props.biddingModes.find((m) => m.value === form.bidding_mode));

const normalizeCodes = () => {
    form.country = String(form.country).toUpperCase().slice(0, 2);
    form.currency = String(form.currency).toUpperCase().slice(0, 3);
    form.reference = String(form.reference).toLowerCase().replace(/[^a-z0-9_-]/g, '');
};

const cleanCaps = () => {
    form.caps = Object.fromEntries(
        Object.entries(form.caps ?? {}).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    );
};

const validateBeforeSubmit = () => {
    normalizeCodes();

    if (!form.name?.trim() || !form.reference?.trim() || !form.country || !form.currency) {
        goStep('identity');
        return false;
    }

    if (form.payout_amount === '' || form.payout_amount === null || form.floor_price === '' || form.floor_price === null) {
        goStep('pricing');
        return false;
    }

    return true;
};

const jumpToErrorStep = () => {
    const errors = form.errors ?? {};
    const identityFields = ['name', 'reference', 'country', 'currency', 'vertical_id'];
    const pricingFields = ['payout_amount', 'floor_price', 'bidding_mode'];

    if (identityFields.some((field) => errors[field])) {
        goStep('identity');
    } else if (pricingFields.some((field) => errors[field])) {
        goStep('pricing');
    } else if (errors.use_advanced_distribution || errors.sell_mode) {
        goStep('routing');
    } else {
        goStep('caps');
    }
};

const submit = () => {
    if (!validateBeforeSubmit()) {
        return;
    }

    cleanCaps();

    const options = {
        preserveScroll: true,
        onError: jumpToErrorStep,
        forceFormData: Boolean(form.logo),
        onSuccess: () => form.reset('logo'),
    };

    if (props.campaign) {
        form.put(route('campaigns.update', props.campaign.id), options);
    } else {
        form.post(route('campaigns.store'), options);
    }
};
</script>

<template>
    <Head :title="campaign ? 'Edit Campaign' : 'New Campaign'" />
    <AuthenticatedLayout>
        <PageHeader
            :title="campaign ? 'Edit Campaign' : 'New Campaign'"
            :description="campaign ? `Editing ${campaign.reference}` : 'Step-by-step setup - identity, pricing, routing, and volume caps.'"
        >
            <template v-if="campaign" #actions>
                <AppButton :href="route('campaigns.show', campaign.id)" variant="secondary">View campaign</AppButton>
            </template>
        </PageHeader>

        <TenantContextBanner class="mb-6" />

        <CampaignWorkflowNav
            v-if="campaignWorkflow"
            :campaign="campaignWorkflow.campaign"
            :distribution-config-id="campaignWorkflow.distributionConfigId"
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
                        <div v-if="form.name || logoPreview" class="pt-2">
                            <dt class="text-slate-500">Preview</dt>
                            <dd class="mt-2">
                                <CampaignRowAvatar
                                    :name="form.name || 'Campaign name'"
                                    :logo-url="logoPreview"
                                    :region="regionPreview"
                                />
                            </dd>
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

            <form class="space-y-6" novalidate @submit.prevent="submit">
                <FormErrorSummary :errors="form.errors" />

                <Panel v-show="currentStep === 'identity'" title="1. Campaign identity">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <InputLabel value="Campaign name" />
                            <TextInput v-model="form.name" class="mt-1 w-full" placeholder="e.g. Auto Insurance Leads" />
                            <InputError class="mt-1" :message="form.errors.name" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel value="API reference" />
                            <TextInput
                                v-model="form.reference"
                                class="mt-1 w-full font-mono"
                                :disabled="!!campaign?.reference_locked"
                                placeholder="auto-insurance-leads"
                                maxlength="255"
                            />
                            <p class="mt-1 text-xs text-slate-500">
                                Lowercase letters, numbers, hyphens only. Used in API: <code class="text-indigo-500">campaign_ref</code>
                            </p>
                            <InputError class="mt-1" :message="form.errors.reference" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel value="Campaign logo" />
                            <div
                                v-if="logoPreview"
                                class="mt-3 flex items-center gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800"
                            >
                                <img :src="logoPreview" alt="Campaign logo preview" class="h-12 w-12 rounded-lg object-cover" />
                                <button type="button" class="text-sm text-rose-600 hover:text-rose-500" @click="removeLogo">Remove logo</button>
                            </div>
                            <input
                                type="file"
                                accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                class="mt-3 block w-full text-sm text-slate-500 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 dark:file:bg-indigo-500/15 dark:file:text-indigo-400"
                                @change="onLogoFile"
                            />
                            <p class="mt-1 text-xs text-slate-500">PNG, JPG, SVG or WebP. Max 2MB. Shown as a thumbnail in the campaigns list.</p>
                            <InputError class="mt-1" :message="form.errors.logo" />
                        </div>
                        <div>
                            <InputLabel value="Primary country (ISO)" />
                            <select v-model="form.country" class="form-select w-full" :disabled="form.multi_geo">
                                <option v-for="(label, code) in countries" :key="code" :value="code">{{ code }} - {{ label }}</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">Billing and default market. Used for the flag when not multi-geo.</p>
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
                            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <input v-model="form.multi_geo" type="checkbox" class="mt-1 rounded border-slate-300 text-indigo-600 dark:border-slate-600" />
                                <span class="text-sm text-slate-600 dark:text-slate-400">
                                    <strong>Multi-geo campaign</strong>
                                    <span class="mt-1 block text-xs text-slate-500">
                                        Shows a world flag. Select multiple countries below, or leave empty for worldwide.
                                    </span>
                                </span>
                            </label>
                            <InputError class="mt-1" :message="form.errors.multi_geo" />
                        </div>
                        <div v-if="form.multi_geo" class="md:col-span-2">
                            <InputLabel value="Target countries (optional)" />
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="(label, code) in countries"
                                    :key="code"
                                    type="button"
                                    class="rounded-full border px-3 py-1 text-xs transition"
                                    :class="form.geo_countries?.includes(code)
                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-500/15 dark:text-indigo-300'
                                        : 'border-slate-200 text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-400'"
                                    @click="toggleGeoCountry(code)"
                                >
                                    {{ String.fromCodePoint(...[...code].map((char) => 0x1F1E6 + char.charCodeAt(0) - 65)) }}
                                    {{ code }}
                                </button>
                            </div>
                            <InputError class="mt-1" :message="form.errors.geo_countries" />
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

                <Panel v-show="currentStep === 'caps'" title="4. Caps, budget & status">
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

                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Volume caps</h3>
                        <p class="mt-1 text-xs text-slate-500">Max leads ingested per period. Leave blank for unlimited.</p>
                        <div class="mt-3 grid max-w-lg grid-cols-2 gap-4">
                            <div>
                                <InputLabel value="Daily cap" />
                                <TextInput v-model="form.caps.daily" type="number" min="0" class="mt-1 w-full" placeholder="Unlimited" />
                            </div>
                            <div>
                                <InputLabel value="Hourly cap" />
                                <TextInput v-model="form.caps.hourly" type="number" min="0" class="mt-1 w-full" placeholder="Unlimited" />
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 border-t border-slate-100 pt-6 dark:border-slate-800">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Revenue budget</h3>
                        <p class="mt-1 text-xs text-slate-500">
                            Max buyer revenue sold through this campaign ({{ capCurrency }}). Stops distribution when reached - separate from buyer credit limits.
                        </p>
                        <div class="mt-3 grid max-w-lg grid-cols-2 gap-4">
                            <div>
                                <InputLabel :value="`Daily budget (${capCurrency})`" />
                                <TextInput v-model="form.caps.daily_spend_cap" type="number" step="0.01" min="0" class="mt-1 w-full" placeholder="Unlimited" />
                            </div>
                            <div>
                                <InputLabel :value="`Monthly budget (${capCurrency})`" />
                                <TextInput v-model="form.caps.monthly_spend_cap" type="number" step="0.01" min="0" class="mt-1 w-full" placeholder="Unlimited" />
                            </div>
                        </div>
                    </div>
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
