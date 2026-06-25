<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    buyer: Object,
    portalUser: Object,
    currencies: { type: Array, default: () => [] },
    defaultCurrency: { type: String, default: 'GBP' },
});
const { currency, formatMoney } = useMoneyFormat(props.buyer?.currency ?? props.defaultCurrency);
const currencyLabel = currency;

const steps = [
    { id: 'basics', label: 'Basics' },
    { id: 'billing', label: 'Credit & caps' },
    { id: 'advanced', label: 'Advanced' },
    { id: 'portal', label: 'Portal access' },
];
const currentStep = ref('basics');

const form = useForm({
    reference: props.buyer?.reference ?? '',
    name: props.buyer?.name ?? '',
    email: props.buyer?.email ?? '',
    status: props.buyer?.status ?? 'active',
    currency: props.buyer?.currency ?? props.defaultCurrency ?? 'GBP',
    credit_balance: props.buyer?.credit_balance ?? 0,
    caps: {
        daily: props.buyer?.caps?.daily ?? '',
        hourly: props.buyer?.caps?.hourly ?? '',
        monthly: props.buyer?.caps?.monthly ?? '',
        daily_spend_cap: props.buyer?.caps?.daily_spend_cap ?? '',
        monthly_spend_cap: props.buyer?.caps?.monthly_spend_cap ?? '',
    },
    schedule: {
        enabled: props.buyer?.schedule?.enabled ?? false,
        timezone: props.buyer?.schedule?.timezone ?? 'Europe/London',
        start: props.buyer?.schedule?.start ?? '09:00',
        end: props.buyer?.schedule?.end ?? '17:00',
    },
    settings: {
        exclusive_only: props.buyer?.settings?.exclusive_only ?? false,
        min_quality_score: props.buyer?.settings?.min_quality_score ?? '',
        duplicate_window_hours: props.buyer?.settings?.duplicate_window_hours ?? '',
        auto_topup_threshold: props.buyer?.settings?.auto_topup_threshold ?? '',
        auto_topup_amount: props.buyer?.settings?.auto_topup_amount ?? '',
        pricing_model: props.buyer?.settings?.pricing_model ?? 'cpl',
        default_cpc_override: props.buyer?.settings?.default_cpc_override ?? '',
        low_credit_alert: props.buyer?.settings?.low_credit_alert ?? '',
        conversion_postback_url: props.buyer?.settings?.conversion_postback_url ?? '',
        notify_on_sale: props.buyer?.settings?.notify_on_sale ?? false,
        geo_countries: (props.buyer?.settings?.geo_countries ?? []).join(', '),
    },
    portal_email: props.portalUser?.email ?? '',
    portal_name: props.portalUser?.name ?? '',
    portal_password: '',
    generate_portal_password: false,
    send_portal_credentials: false,
});

const submit = () => {
    form.reference = String(form.reference).toLowerCase().replace(/[^a-z0-9_-]/g, '');
    form.currency = String(form.currency || props.defaultCurrency).toUpperCase();
    form.settings.geo_countries = String(form.settings.geo_countries || '')
        .split(',')
        .map((c) => c.trim().toUpperCase())
        .filter(Boolean);
    props.buyer ? form.put(route('buyers.update', props.buyer.id)) : form.post(route('buyers.store'));
};
</script>

<template>
    <Head :title="buyer ? 'Edit Buyer' : 'New Buyer'" />
    <AuthenticatedLayout>
        <PageHeader :title="buyer ? 'Edit Buyer' : 'New Buyer'" description="Buyer profile, credit, volume caps, and portal login.">
            <template v-if="buyer" #actions>
                <a :href="route('buyers.show', buyer.id)" class="text-sm text-indigo-600 hover:underline">← Back to buyer</a>
            </template>
        </PageHeader>

        <div class="mb-6 flex flex-wrap gap-2">
            <button
                v-for="s in steps"
                :key="s.id"
                type="button"
                :class="['rounded-lg px-3 py-1.5 text-sm font-medium', currentStep === s.id ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300']"
                @click="currentStep = s.id"
            >
                {{ s.label }}
            </button>
        </div>

        <Panel class="max-w-2xl">
            <FormErrorSummary :errors="form.errors" />
            <form @submit.prevent="submit" class="space-y-6">
                <div v-show="currentStep === 'basics'" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Reference" />
                            <TextInput v-model="form.reference" class="mt-1 font-mono" required placeholder="hastings-direct" />
                            <InputError class="mt-1" :message="form.errors.reference" />
                        </div>
                        <div>
                            <InputLabel value="Status" />
                            <select v-model="form.status" class="form-select mt-1 w-full">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Display name" />
                        <TextInput v-model="form.name" class="mt-1" required />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Contact email" />
                        <TextInput v-model="form.email" type="email" class="mt-1" placeholder="ops@buyer.com" />
                        <p class="mt-1 text-xs text-slate-500">For notifications — separate from portal login.</p>
                    </div>
                    <div>
                        <InputLabel value="Billing currency" />
                        <select v-model="form.currency" class="form-select mt-1 max-w-xs">
                            <option v-for="c in currencies" :key="c" :value="c">{{ c }}</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Ledger, caps, and alerts use this currency. Defaults to platform currency for new buyers.</p>
                    </div>
                </div>

                <div v-show="currentStep === 'billing'" class="space-y-4">
                    <div>
                        <InputLabel :value="`Credit balance (${currencyLabel})`" />
                        <TextInput v-model="form.credit_balance" type="number" step="0.01" min="0" class="mt-1 max-w-xs" />
                        <InputError class="mt-1" :message="form.errors.credit_balance" />
                    </div>
                    <div>
                        <InputLabel value="Volume caps" />
                        <div class="mt-2 grid max-w-lg grid-cols-3 gap-3">
                            <div><InputLabel value="Daily" /><TextInput v-model="form.caps.daily" type="number" min="0" class="mt-1" placeholder="∞" /></div>
                            <div><InputLabel value="Hourly" /><TextInput v-model="form.caps.hourly" type="number" min="0" class="mt-1" placeholder="∞" /></div>
                            <div><InputLabel value="Monthly" /><TextInput v-model="form.caps.monthly" type="number" min="0" class="mt-1" placeholder="∞" /></div>
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Spend caps" />
                        <div class="mt-2 grid max-w-lg grid-cols-2 gap-3">
                            <div><InputLabel :value="`Daily spend (${currencyLabel})`" /><TextInput v-model="form.caps.daily_spend_cap" type="number" step="0.01" min="0" class="mt-1" placeholder="∞" /></div>
                            <div><InputLabel :value="`Monthly spend (${currencyLabel})`" /><TextInput v-model="form.caps.monthly_spend_cap" type="number" step="0.01" min="0" class="mt-1" placeholder="∞" /></div>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">When exceeded, buyer is excluded from pings until the period resets.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <label class="flex items-center gap-2 text-sm font-medium">
                            <input v-model="form.schedule.enabled" type="checkbox" class="rounded" />
                            Delivery schedule (business hours only)
                        </label>
                        <div v-if="form.schedule.enabled" class="mt-3 grid gap-3 sm:grid-cols-3">
                            <div><InputLabel value="Timezone" /><TextInput v-model="form.schedule.timezone" class="mt-1" /></div>
                            <div><InputLabel value="Start" /><TextInput v-model="form.schedule.start" type="time" class="mt-1" /></div>
                            <div><InputLabel value="End" /><TextInput v-model="form.schedule.end" type="time" class="mt-1" /></div>
                        </div>
                    </div>
                </div>

                <div v-show="currentStep === 'advanced'" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Pricing model" />
                            <select v-model="form.settings.pricing_model" class="form-select mt-1 w-full">
                                <option value="cpl">CPL (cost per lead)</option>
                                <option value="cpc">CPC (cost per contact)</option>
                                <option value="cpf">CPF (cost per funded — finance verticals)</option>
                                <option value="rev_share">Revenue share</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel :value="`Default CPC override (${currencyLabel})`" />
                            <TextInput v-model="form.settings.default_cpc_override" type="number" step="0.01" min="0" class="mt-1" placeholder="Use ping-tree default" />
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Min quality score (0–100)" />
                            <TextInput v-model="form.settings.min_quality_score" type="number" min="0" max="100" class="mt-1" />
                            <p class="mt-1 text-xs text-slate-500">Leads below this score are skipped for this buyer. Score is computed from validation results and field completeness.</p>
                        </div>
                        <div>
                            <InputLabel value="Duplicate window (hours)" />
                            <TextInput v-model="form.settings.duplicate_window_hours" type="number" min="0" class="mt-1" placeholder="24" />
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Geo countries (ISO codes, comma-separated)" />
                        <TextInput v-model="form.settings.geo_countries" class="mt-1 font-mono" placeholder="GB, IE" />
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel :value="`Auto top-up threshold (${currencyLabel})`" />
                            <TextInput v-model="form.settings.auto_topup_threshold" type="number" step="0.01" min="0" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel :value="`Auto top-up amount (${currencyLabel})`" />
                            <TextInput v-model="form.settings.auto_topup_amount" type="number" step="0.01" min="0" class="mt-1" />
                        </div>
                    </div>
                    <div>
                        <InputLabel :value="`Low credit alert (${currencyLabel})`" />
                        <TextInput v-model="form.settings.low_credit_alert" type="number" step="0.01" min="0" class="mt-1" />
                        <p class="mt-1 text-xs text-slate-500">Email alert when balance falls to or below this amount (uses platform default if empty).</p>
                    </div>
                    <div>
                        <InputLabel value="Conversion postback URL (optional)" />
                        <TextInput v-model="form.settings.conversion_postback_url" class="mt-1 font-mono text-sm" placeholder="https://buyer-crm.com/conversion?lead=[lead_uuid]" />
                        <p class="mt-1 text-xs text-slate-500">Fired when buyer reports contacted / converted / funded via portal or API.</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input v-model="form.settings.exclusive_only" type="checkbox" class="rounded" />
                        Exclusive leads only
                    </label>
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input v-model="form.settings.notify_on_sale" type="checkbox" class="rounded" />
                        Email notification on each lead purchase
                    </label>
                </div>

                <div v-show="currentStep === 'portal'" class="space-y-4">
                    <p class="text-sm text-slate-600 dark:text-slate-400">Buyers log in at <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">/portal/buyer</code> to view purchased leads and billing.</p>
                    <div>
                        <InputLabel value="Portal login email" />
                        <TextInput v-model="form.portal_email" type="email" class="mt-1" :placeholder="`buyer-portal@yourplatform.test`" />
                    </div>
                    <div>
                        <InputLabel value="Portal display name" />
                        <TextInput v-model="form.portal_name" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel :value="`New portal password (${buyer ? 'optional' : 'required if email set'})`" />
                        <TextInput v-model="form.portal_password" type="password" class="mt-1" :required="!buyer && !!form.portal_email" />
                        <InputError class="mt-1" :message="form.errors.portal_password" />
                    </div>
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input v-model="form.generate_portal_password" type="checkbox" class="rounded" />
                        Generate secure password
                    </label>
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input v-model="form.send_portal_credentials" type="checkbox" class="rounded" />
                        Email portal credentials to buyer
                    </label>
                </div>

                <div class="flex items-center justify-between border-t border-slate-200 pt-4 dark:border-slate-700">
                    <p class="text-xs text-slate-500">After saving, link this buyer to deliveries on the ping tree.</p>
                    <PrimaryButton :disabled="form.processing">{{ buyer ? 'Update' : 'Create' }} buyer</PrimaryButton>
                </div>
            </form>
        </Panel>
    </AuthenticatedLayout>
</template>
