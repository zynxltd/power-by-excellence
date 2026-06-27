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
import { useFormSteps } from '@/Composables/useFormSteps';
import { Head, useForm } from '@inertiajs/vue3';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    buyer: Object,
    portalUser: Object,
    currencies: { type: Array, default: () => [] },
    defaultCurrency: { type: String, default: 'GBP' },
    buyerPortalLanguages: { type: Object, default: () => ({ en: 'English' }) },
    defaultBuyerPortalLocale: { type: String, default: 'en' },
});
const { currency, formatMoney } = useMoneyFormat(props.buyer?.currency ?? props.defaultCurrency);
const currencyLabel = currency;

const steps = [
    { id: 'basics', label: 'Basics', num: 1 },
    { id: 'billing', label: 'Credit & caps', num: 2 },
    { id: 'advanced', label: 'Advanced', num: 3 },
    { id: 'portal', label: 'Portal access', num: 4 },
];

const { currentStep, goStep, stepStatus, nextStep, prevStep } = useFormSteps(steps, {
    isEdit: !!props.buyer,
});

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
        sold_webhook_url: props.buyer?.settings?.sold_webhook_url ?? '',
        notify_on_sale: props.buyer?.settings?.notify_on_sale ?? false,
        geo_countries: (props.buyer?.settings?.geo_countries ?? []).join(', '),
        portal_locale: props.buyer?.settings?.portal_locale ?? '',
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

    if (!form.reference?.trim() || !form.name?.trim()) {
        goStep('basics');
        return;
    }

    const options = {
        preserveScroll: true,
        onError: () => {
            const errors = form.errors ?? {};
            if (errors.reference || errors.name || errors.email || errors.status || errors.currency) {
                goStep('basics');
            } else if (Object.keys(errors).some((k) => k.startsWith('caps.'))) {
                goStep('billing');
            } else if (Object.keys(errors).some((k) => k.startsWith('settings.'))) {
                goStep('advanced');
            } else {
                goStep('portal');
            }
        },
    };

    props.buyer ? form.put(route('buyers.update', props.buyer.id), options) : form.post(route('buyers.store'), options);
};
</script>

<template>
    <Head :title="buyer ? 'Edit Buyer' : 'New Buyer'" />
    <AuthenticatedLayout>
        <PageHeader :title="buyer ? 'Edit Buyer' : 'New Buyer'" description="Step-by-step setup - profile, credit, advanced rules, and portal login.">
            <template v-if="buyer" #actions>
                <AppButton :href="route('buyers.show', buyer.id)" variant="secondary">View buyer</AppButton>
            </template>
        </PageHeader>

        <FormSetupLayout :steps="steps" :current-step="currentStep" :step-status="stepStatus" @go="goStep">
            <template #sidebar>
                <Panel v-if="form.name || form.reference" title="Summary" class="mt-4">
                    <dl class="space-y-2 text-sm">
                        <div v-if="form.name">
                            <dt class="text-slate-500">Name</dt>
                            <dd class="font-medium">{{ form.name }}</dd>
                        </div>
                        <div v-if="form.reference">
                            <dt class="text-slate-500">Reference</dt>
                            <dd class="font-mono text-xs font-medium">{{ form.reference }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Credit</dt>
                            <dd class="font-medium">{{ formatMoney(form.credit_balance) }}</dd>
                        </div>
                    </dl>
                </Panel>
            </template>

            <form class="space-y-6" novalidate @submit.prevent="submit">
                <FormErrorSummary :errors="form.errors" />

                <Panel v-show="currentStep === 'basics'" title="1. Buyer profile">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Reference" />
                            <TextInput v-model="form.reference" class="mt-1 w-full font-mono" required placeholder="hastings-direct" />
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
                    <div class="mt-4">
                        <InputLabel value="Display name" />
                        <TextInput v-model="form.name" class="mt-1 w-full" required />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>
                    <div class="mt-4">
                        <InputLabel value="Contact email" />
                        <TextInput v-model="form.email" type="email" class="mt-1 w-full" placeholder="ops@buyer.com" />
                        <p class="mt-1 text-xs text-slate-500">For notifications - separate from portal login.</p>
                    </div>
                    <div class="mt-4">
                        <InputLabel value="Billing currency" />
                        <select v-model="form.currency" class="form-select mt-1 max-w-xs">
                            <option v-for="c in currencies" :key="c" :value="c">{{ c }}</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Ledger, caps, and alerts use this currency.</p>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <AppButton type="button" @click="nextStep">Next: Credit & caps →</AppButton>
                    </div>
                </Panel>

                <Panel v-show="currentStep === 'billing'" title="2. Credit & volume caps">
                    <div>
                        <InputLabel :value="`Credit balance (${currencyLabel})`" />
                        <TextInput v-model="form.credit_balance" type="number" step="0.01" min="0" class="mt-1 max-w-xs" />
                        <InputError class="mt-1" :message="form.errors.credit_balance" />
                    </div>
                    <div class="mt-4">
                        <InputLabel value="Volume caps" />
                        <div class="mt-2 grid max-w-lg grid-cols-3 gap-3">
                            <div><InputLabel value="Daily" /><TextInput v-model="form.caps.daily" type="number" min="0" class="mt-1 w-full" placeholder="∞" /></div>
                            <div><InputLabel value="Hourly" /><TextInput v-model="form.caps.hourly" type="number" min="0" class="mt-1 w-full" placeholder="∞" /></div>
                            <div><InputLabel value="Monthly" /><TextInput v-model="form.caps.monthly" type="number" min="0" class="mt-1 w-full" placeholder="∞" /></div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <InputLabel value="Spend caps" />
                        <div class="mt-2 grid max-w-lg grid-cols-2 gap-3">
                            <div><InputLabel :value="`Daily spend (${currencyLabel})`" /><TextInput v-model="form.caps.daily_spend_cap" type="number" step="0.01" min="0" class="mt-1 w-full" placeholder="∞" /></div>
                            <div><InputLabel :value="`Monthly spend (${currencyLabel})`" /><TextInput v-model="form.caps.monthly_spend_cap" type="number" step="0.01" min="0" class="mt-1 w-full" placeholder="∞" /></div>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">When exceeded, buyer is excluded from pings until the period resets.</p>
                    </div>
                    <div class="mt-4 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <label class="flex items-center gap-2 text-sm font-medium">
                            <input v-model="form.schedule.enabled" type="checkbox" class="rounded" />
                            Delivery schedule (business hours only)
                        </label>
                        <div v-if="form.schedule.enabled" class="mt-3 grid gap-3 sm:grid-cols-3">
                            <div><InputLabel value="Timezone" /><TextInput v-model="form.schedule.timezone" class="mt-1 w-full" /></div>
                            <div><InputLabel value="Start" /><TextInput v-model="form.schedule.start" type="time" class="mt-1 w-full" /></div>
                            <div><InputLabel value="End" /><TextInput v-model="form.schedule.end" type="time" class="mt-1 w-full" /></div>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-between">
                        <AppButton type="button" variant="secondary" @click="prevStep">← Back</AppButton>
                        <AppButton type="button" @click="nextStep">Next: Advanced →</AppButton>
                    </div>
                </Panel>

                <Panel v-show="currentStep === 'advanced'" title="3. Advanced rules">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Pricing model" />
                            <select v-model="form.settings.pricing_model" class="form-select mt-1 w-full">
                                <option value="cpl">CPL (cost per lead)</option>
                                <option value="cpc">CPC (cost per contact)</option>
                                <option value="cpf">CPF (cost per funded - finance verticals)</option>
                                <option value="rev_share">Revenue share</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel :value="`Default CPC override (${currencyLabel})`" />
                            <TextInput v-model="form.settings.default_cpc_override" type="number" step="0.01" min="0" class="mt-1 w-full" placeholder="Use ping-tree default" />
                        </div>
                    </div>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Min quality score (0–100)" />
                            <TextInput v-model="form.settings.min_quality_score" type="number" min="0" max="100" class="mt-1 w-full" />
                        </div>
                        <div>
                            <InputLabel value="Duplicate window (hours)" />
                            <TextInput v-model="form.settings.duplicate_window_hours" type="number" min="0" class="mt-1 w-full" placeholder="24" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <InputLabel value="Geo countries (ISO codes, comma-separated)" />
                        <TextInput v-model="form.settings.geo_countries" class="mt-1 w-full font-mono" placeholder="GB, IE" />
                    </div>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel :value="`Auto top-up threshold (${currencyLabel})`" />
                            <TextInput v-model="form.settings.auto_topup_threshold" type="number" step="0.01" min="0" class="mt-1 w-full" />
                        </div>
                        <div>
                            <InputLabel :value="`Auto top-up amount (${currencyLabel})`" />
                            <TextInput v-model="form.settings.auto_topup_amount" type="number" step="0.01" min="0" class="mt-1 w-full" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <InputLabel :value="`Low credit alert (${currencyLabel})`" />
                        <TextInput v-model="form.settings.low_credit_alert" type="number" step="0.01" min="0" class="mt-1 w-full" />
                    </div>
                    <div class="mt-4">
                        <InputLabel value="Sold webhook URL (optional)" />
                        <TextInput v-model="form.settings.sold_webhook_url" class="mt-1 w-full font-mono text-sm" placeholder="https://buyer-crm.com/webhooks/sold" />
                        <p class="mt-1 text-xs text-slate-500">JSON POST when this buyer wins a lead. Also appears under Tools → Webhooks. For conversion feedback after sale, use the postback URL below.</p>
                    </div>
                    <div class="mt-4">
                        <InputLabel value="Conversion postback URL (optional)" />
                        <TextInput v-model="form.settings.conversion_postback_url" class="mt-1 w-full font-mono text-sm" placeholder="https://buyer-crm.com/conversion?lead=[lead_uuid]" />
                        <p class="mt-1 text-xs text-slate-500">Fired when you report a conversion back (funded, contacted, etc.) - not on initial sale.</p>
                    </div>
                    <label class="mt-4 flex items-center gap-2 text-sm font-medium">
                        <input v-model="form.settings.exclusive_only" type="checkbox" class="rounded" />
                        Exclusive leads only
                    </label>
                    <label class="mt-2 flex items-center gap-2 text-sm font-medium">
                        <input v-model="form.settings.notify_on_sale" type="checkbox" class="rounded" />
                        Email notification on each lead purchase
                    </label>
                    <div class="mt-4 flex justify-between">
                        <AppButton type="button" variant="secondary" @click="prevStep">← Back</AppButton>
                        <AppButton type="button" @click="nextStep">Next: Portal access →</AppButton>
                    </div>
                </Panel>

                <Panel v-show="currentStep === 'portal'" title="4. Portal access">
                    <p class="text-sm text-slate-600 dark:text-slate-400">Buyers log in at <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">/portal/buyer</code> to view purchased leads and billing.</p>
                    <div class="mt-4">
                        <InputLabel value="Portal login email" />
                        <TextInput v-model="form.portal_email" type="email" class="mt-1 w-full" placeholder="buyer-portal@yourplatform.test" />
                    </div>
                    <div class="mt-4">
                        <InputLabel value="Portal display name" />
                        <TextInput v-model="form.portal_name" class="mt-1 w-full" />
                    </div>
                    <div class="mt-4">
                        <InputLabel value="Portal language" />
                        <select v-model="form.settings.portal_locale" class="form-select mt-1 w-full max-w-md">
                            <option value="">Platform default ({{ buyerPortalLanguages[defaultBuyerPortalLocale] ?? defaultBuyerPortalLocale }})</option>
                            <option v-for="(label, code) in buyerPortalLanguages" :key="code" :value="code">
                                {{ label }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Set when this buyer's team uses a different language than your platform default.</p>
                    </div>
                    <div class="mt-4">
                        <InputLabel :value="`New portal password (${buyer ? 'optional' : 'required if email set'})`" />
                        <TextInput v-model="form.portal_password" type="password" class="mt-1 w-full" />
                        <InputError class="mt-1" :message="form.errors.portal_password" />
                    </div>
                    <label class="mt-4 flex items-center gap-2 text-sm font-medium">
                        <input v-model="form.generate_portal_password" type="checkbox" class="rounded" />
                        Generate secure password
                    </label>
                    <label class="mt-2 flex items-center gap-2 text-sm font-medium">
                        <input v-model="form.send_portal_credentials" type="checkbox" class="rounded" />
                        Email portal credentials to buyer
                    </label>
                    <p class="mt-4 text-xs text-slate-500">After saving, link this buyer to deliveries on the ping tree.</p>
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <AppButton type="button" variant="secondary" @click="prevStep">← Back</AppButton>
                        <PrimaryButton :disabled="form.processing" :loading="form.processing">{{ buyer ? 'Update' : 'Create' }} buyer</PrimaryButton>
                    </div>
                </Panel>
            </form>
        </FormSetupLayout>
    </AuthenticatedLayout>
</template>
