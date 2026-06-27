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

const props = defineProps({
    account: Object,
    timezones: Array,
    currencies: Array,
    countries: Object,
    buyerPortalLanguages: Object,
});

const form = useForm({
    name: props.account.name ?? '',
    timezone: props.account.timezone ?? 'UTC',
    default_country: props.account.default_country ?? 'GB',
    default_currency: props.account.default_currency ?? 'GBP',
    require_buyer_prepay: props.account.require_buyer_prepay ?? false,
    supplier_iframe_embed: props.account.supplier_iframe_embed ?? false,
    billing_alert_emails: props.account.billing_alert_emails ?? '',
    default_low_credit_alert: props.account.default_low_credit_alert ?? '',
    buyer_portal_locale: props.account.buyer_portal_locale ?? 'en',
});

const submit = () => {
    form.default_country = String(form.default_country).toUpperCase();
    form.default_currency = String(form.default_currency).toUpperCase();
    form.put(route('settings.update'));
};
</script>

<template>
    <Head title="Platform Settings" />
    <AuthenticatedLayout>
        <PageHeader
            title="Platform Settings"
            description="Configure your business region, currency and timezone. New campaigns inherit these defaults."
        />

        <Panel class="max-w-2xl">
            <FormErrorSummary :errors="form.errors" />
            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <InputLabel value="Platform name" />
                    <TextInput v-model="form.name" class="mt-1" required />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel value="Default country" />
                        <select v-model="form.default_country" class="form-select">
                            <option v-for="(label, code) in countries" :key="code" :value="code">{{ code }} - {{ label }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.default_country" />
                    </div>
                    <div>
                        <InputLabel value="Default currency" />
                        <select v-model="form.default_currency" class="form-select">
                            <option v-for="c in currencies" :key="c" :value="c">{{ c }}</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Used across admin, reports, billing, and buyer/supplier portals for this tenant.</p>
                        <InputError class="mt-1" :message="form.errors.default_currency" />
                    </div>
                </div>
                <div>
                    <InputLabel value="Timezone" />
                    <select v-model="form.timezone" class="form-select">
                        <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.timezone" />
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Buyer portal language</h4>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Default language for all buyer portal users on this platform. Override per buyer when purchasers operate in different countries.
                    </p>
                    <select v-model="form.buyer_portal_locale" class="form-select mt-3 max-w-md">
                        <option v-for="(label, code) in buyerPortalLanguages" :key="code" :value="code">
                            {{ label }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.buyer_portal_locale" />
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                    <label class="flex items-start gap-3">
                        <input v-model="form.require_buyer_prepay" type="checkbox" class="mt-1 rounded border-slate-300 text-indigo-600" />
                        <span>
                            <span class="block text-sm font-medium text-slate-900 dark:text-white">Require buyer prepay</span>
                            <span class="mt-0.5 block text-sm text-slate-500 dark:text-slate-400">
                                When enabled, buyers must have sufficient credit before a lead can be sold to them. Successful sales debit the buyer ledger automatically.
                            </span>
                        </span>
                    </label>
                    <div v-if="form.require_buyer_prepay" class="mt-4 rounded-lg border border-indigo-200/80 bg-white p-3 text-sm text-slate-600 dark:border-indigo-500/30 dark:bg-slate-900/50 dark:text-slate-300">
                        <p class="font-semibold text-slate-900 dark:text-white">What happens when credit is insufficient?</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li>During routing, the buyer is <strong>skipped</strong> with reason “Insufficient buyer credit” - the lead continues to the next tier/buyer.</li>
                            <li>Buyers with <strong>zero or low balance</strong> never receive pings/posts until an admin tops up their ledger.</li>
                            <li><strong>Inactive buyers</strong> or accounts with a <strong>billing lock</strong> are also blocked from receiving leads.</li>
                            <li>When prepay is <strong>disabled</strong>, credit checks are skipped - leads can sell without a balance (post-paid).</li>
                            <li>Buyer portal users see their balance and prepay status on the Billing page.</li>
                        </ul>
                    </div>
                    <InputError class="mt-1" :message="form.errors.require_buyer_prepay" />
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                    <label class="flex items-start gap-3">
                        <input v-model="form.supplier_iframe_embed" type="checkbox" class="mt-1 rounded border-slate-300 text-indigo-600" />
                        <span>
                            <span class="block text-sm font-medium text-slate-900 dark:text-white">Allow supplier iframe embeds</span>
                            <span class="mt-0.5 block text-sm text-slate-500 dark:text-slate-400">
                                When enabled, suppliers can embed hosted lead forms on any external website via iframe. When disabled, forms can only be opened as direct links on your platform domain.
                            </span>
                        </span>
                    </label>
                    <InputError class="mt-1" :message="form.errors.supplier_iframe_embed" />
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Billing alerts</h4>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Notify admins and buyers when credit runs low.</p>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <InputLabel value="Alert email addresses" />
                            <TextInput v-model="form.billing_alert_emails" class="mt-1" placeholder="billing@agency.com, ops@agency.com" />
                            <p class="mt-1 text-xs text-slate-500">Comma-separated. Receives low-credit alerts for any buyer on this platform.</p>
                        </div>
                        <div>
                            <InputLabel value="Default low-credit threshold" />
                            <TextInput v-model="form.default_low_credit_alert" type="number" step="0.01" min="0" class="mt-1" />
                            <p class="mt-1 text-xs text-slate-500">Used when a buyer has no per-buyer threshold set.</p>
                        </div>
                    </div>
                </div>

                <PrimaryButton :disabled="form.processing">Save Settings</PrimaryButton>
            </form>
        </Panel>
    </AuthenticatedLayout>
</template>
