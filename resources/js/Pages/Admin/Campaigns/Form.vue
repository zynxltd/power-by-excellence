<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import CampaignWorkflowNav from '@/Components/UI/CampaignWorkflowNav.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    campaign: Object,
    defaults: { type: Object, default: () => ({ country: 'GB', currency: 'GBP' }) },
    verticals: { type: Array, default: () => [] },
    biddingModes: { type: Array, default: () => [] },
});

const page = usePage();
const account = computed(() => page.props.auth.account);

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
    status: props.campaign?.status ?? 'active',
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
            :description="campaign ? `Editing ${campaign.reference}` : `Defaults from your platform: ${account?.default_country ?? 'GB'} / ${account?.default_currency ?? 'GBP'}. Change platform defaults in Settings.`"
        />

        <CampaignWorkflowNav
            v-if="campaign"
            :campaign="{ id: campaign.id, name: campaign.name, reference: campaign.reference }"
            current="edit"
        />

        <Panel class="max-w-3xl">
            <FormErrorSummary :errors="form.errors" />
            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <InputLabel value="Campaign name" />
                    <TextInput v-model="form.name" class="mt-1" required placeholder="e.g. Auto Insurance Leads" />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>
                <div>
                    <InputLabel value="API reference" />
                    <TextInput
                        v-model="form.reference"
                        class="mt-1 font-mono"
                        :disabled="!!campaign?.reference_locked"
                        required
                        placeholder="auto-insurance-leads"
                        maxlength="255"
                    />
                    <p class="mt-1 text-xs text-slate-500">Lowercase letters, numbers, hyphens only. Used in API: <code class="text-indigo-500">campaign_ref</code></p>
                    <InputError class="mt-1" :message="form.errors.reference" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Country (ISO)" />
                        <select v-model="form.country" class="form-select">
                            <option v-for="(label, code) in countries" :key="code" :value="code">{{ code }} — {{ label }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.country" />
                    </div>
                    <div>
                        <InputLabel value="Currency (ISO)" />
                        <select v-model="form.currency" class="form-select">
                            <option v-for="c in currencies" :key="c" :value="c">{{ c }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.currency" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Payout amount" />
                        <TextInput v-model="form.payout_amount" type="number" step="0.01" min="0" class="mt-1" />
                        <InputError class="mt-1" :message="form.errors.payout_amount" />
                    </div>
                    <div>
                        <InputLabel value="Floor price" />
                        <TextInput v-model="form.floor_price" type="number" step="0.01" min="0" class="mt-1" />
                        <InputError class="mt-1" :message="form.errors.floor_price" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Vertical" />
                        <select v-model="form.vertical_id" class="form-select mt-1 w-full">
                            <option value="">General</option>
                            <option v-for="v in verticals" :key="v.id" :value="v.id">{{ v.label }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.vertical_id" />
                    </div>
                    <div>
                        <InputLabel value="Bidding mode" />
                        <select v-model="form.bidding_mode" class="form-select mt-1 w-full">
                            <option v-for="m in biddingModes" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                        <p v-if="biddingModes.find((m) => m.value === form.bidding_mode)?.help" class="mt-1 text-xs text-slate-500">
                            {{ biddingModes.find((m) => m.value === form.bidding_mode)?.help }}
                        </p>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                    <input v-model="form.use_advanced_distribution" type="checkbox" class="rounded border-slate-300 text-indigo-600 dark:border-slate-600" />
                    Advanced distribution (ping tree / hybrid)
                </label>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Status" />
                        <select v-model="form.status" class="form-select mt-1 w-full">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Sell mode" />
                        <select v-model="form.sell_mode" class="form-select mt-1 w-full">
                            <option value="exclusive">Exclusive (one buyer)</option>
                            <option value="multi_sell">Multi-sell</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Daily cap" />
                        <TextInput v-model="form.caps.daily" type="number" class="mt-1" placeholder="Unlimited" />
                    </div>
                    <div>
                        <InputLabel value="Hourly cap" />
                        <TextInput v-model="form.caps.hourly" type="number" class="mt-1" placeholder="Unlimited" />
                    </div>
                </div>
                <InputError :message="form.errors.use_advanced_distribution" />
                <PrimaryButton :disabled="form.processing">{{ form.processing ? 'Saving...' : 'Save Campaign' }}</PrimaryButton>
            </form>
        </Panel>
    </AuthenticatedLayout>
</template>
