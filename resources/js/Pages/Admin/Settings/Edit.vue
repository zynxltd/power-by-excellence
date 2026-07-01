<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    account: Object,
    portalDomain: Object,
    timezones: Array,
    currencies: Array,
    countries: Object,
    buyerPortalLanguages: Object,
    clientIp: { type: String, default: '' },
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
    custom_portal_domain: props.account.custom_portal_domain ?? '',
    require_2fa_for_staff: props.account.require_2fa_for_staff ?? false,
    require_2fa_for_portal: props.account.require_2fa_for_portal ?? false,
    two_factor_grace_days: props.account.two_factor_grace_days ?? 7,
    data_retention: {
        purge_leads: props.account.data_retention?.purge_leads ?? false,
        leads_retention_days: props.account.data_retention?.leads_retention_days ?? 365,
        purge_logs: props.account.data_retention?.purge_logs ?? false,
        logs_retention_days: props.account.data_retention?.logs_retention_days ?? 90,
        purge_message_events: props.account.data_retention?.purge_message_events ?? false,
        message_events_retention_days: props.account.data_retention?.message_events_retention_days ?? 90,
    },
    security: {
        admin_ip_allowlist_enabled: props.account.security?.admin_ip_allowlist_enabled ?? false,
        admin_ip_allowlist_text: props.account.security?.admin_ip_allowlist_text ?? '',
        admin_geo_block_enabled: props.account.security?.admin_geo_block_enabled ?? false,
        blocked_country_codes_text: props.account.security?.blocked_country_codes_text ?? '',
    },
});

const submit = () => {
    form.default_country = String(form.default_country).toUpperCase();
    form.default_currency = String(form.default_currency).toUpperCase();
    form.put(route('settings.update'));
};

const portalDomainStatus = computed(() => {
    if (!form.custom_portal_domain) {
        return { label: 'Not configured', class: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' };
    }

    if (props.portalDomain?.verified) {
        return { label: 'Verified', class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-400' };
    }

    return { label: 'Pending DNS', class: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-400' };
});

const verifyPortalDomain = () => {
    router.post(route('settings.portal-domain.verify'));
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
                    <div class="flex flex-wrap items-center gap-3">
                        <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Custom portal domain</h4>
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="portalDomainStatus.class"
                        >
                            {{ portalDomainStatus.label }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Optional branded hostname for buyer and supplier portals (e.g. <code class="text-xs">leads.yourbrand.com</code>). DNS must point to this platform before the domain goes live.
                    </p>
                    <TextInput v-model="form.custom_portal_domain" class="mt-3 max-w-md font-mono text-sm" placeholder="leads.example.com" />
                    <InputError class="mt-1" :message="form.errors.custom_portal_domain" />

                    <div v-if="form.custom_portal_domain" class="mt-4 space-y-3 rounded-lg border border-slate-200 bg-white p-3 text-sm text-slate-600 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-300">
                        <p class="font-semibold text-slate-900 dark:text-white">DNS setup</p>
                        <div v-if="portalDomain?.cname_target" class="font-mono text-xs">
                            <p><span class="font-semibold">CNAME</span> {{ form.custom_portal_domain }} → {{ portalDomain.cname_target }}</p>
                        </div>
                        <div v-if="portalDomain?.txt_host && portalDomain?.txt_value" class="font-mono text-xs">
                            <p class="mt-2"><span class="font-semibold">TXT</span> {{ portalDomain.txt_host }} → {{ portalDomain.txt_value }}</p>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Add either record, save your domain, then click verify. Unverified domains keep using your default tenant hostname.
                        </p>
                        <SecondaryButton type="button" @click="verifyPortalDomain">Verify DNS</SecondaryButton>
                    </div>
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

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Two-factor authentication policy</h4>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Require users to enroll in 2FA before accessing admin or portal areas. A grace period gives existing users time to set up after the policy is enabled.
                    </p>

                    <div class="mt-4 space-y-4">
                        <label class="flex items-start gap-3">
                            <input v-model="form.require_2fa_for_staff" type="checkbox" class="mt-1 rounded border-slate-300 text-indigo-600" />
                            <span>
                                <span class="block text-sm font-medium text-slate-900 dark:text-white">Require 2FA for staff</span>
                                <span class="mt-0.5 block text-sm text-slate-500 dark:text-slate-400">
                                    Applies to account admins and staff on this platform. Users without 2FA are redirected to their profile to enroll.
                                </span>
                            </span>
                        </label>
                        <InputError class="mt-1" :message="form.errors.require_2fa_for_staff" />

                        <label class="flex items-start gap-3">
                            <input v-model="form.require_2fa_for_portal" type="checkbox" class="mt-1 rounded border-slate-300 text-indigo-600" />
                            <span>
                                <span class="block text-sm font-medium text-slate-900 dark:text-white">Require 2FA for portal users</span>
                                <span class="mt-0.5 block text-sm text-slate-500 dark:text-slate-400">
                                    Applies to buyer and supplier portal logins on this platform.
                                </span>
                            </span>
                        </label>
                        <InputError class="mt-1" :message="form.errors.require_2fa_for_portal" />

                        <div class="max-w-xs">
                            <InputLabel value="Grace period (days)" />
                            <input v-model="form.two_factor_grace_days" type="number" min="0" max="90" step="1" class="form-input mt-1 w-full" />
                            <p class="mt-1 text-xs text-slate-500">Days after enabling a policy before access is blocked. Set to 0 for immediate enforcement.</p>
                            <InputError class="mt-1" :message="form.errors.two_factor_grace_days" />
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Admin IP allowlist</h4>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Restrict staff admin access to approved office or VPN IP addresses. When enabled, requests from other IPs receive HTTP 403 and are logged under Security.
                    </p>

                    <div class="mt-4 space-y-4">
                        <label class="flex items-start gap-3">
                            <input v-model="form.security.admin_ip_allowlist_enabled" type="checkbox" class="mt-1 rounded border-slate-300 text-indigo-600" />
                            <span>
                                <span class="block text-sm font-medium text-slate-900 dark:text-white">Enforce admin IP allowlist</span>
                                <span class="mt-0.5 block text-sm text-slate-500 dark:text-slate-400">
                                    Applies to authenticated admin routes on this platform. Add your current IP before enabling.
                                </span>
                            </span>
                        </label>
                        <InputError class="mt-1" :message="form.errors['security.admin_ip_allowlist_enabled']" />

                        <div>
                            <InputLabel value="Allowed IPs and CIDR ranges" />
                            <textarea
                                v-model="form.security.admin_ip_allowlist_text"
                                rows="5"
                                class="form-input mt-1 w-full font-mono text-sm"
                                placeholder="203.0.113.10&#10;198.51.100.0/24"
                            />
                            <p v-if="clientIp" class="mt-2 text-xs text-slate-500">
                                Your current IP:
                                <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">{{ clientIp }}</code>
                                <button
                                    type="button"
                                    class="ml-2 font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                    @click="form.security.admin_ip_allowlist_text = [form.security.admin_ip_allowlist_text, clientIp].filter(Boolean).join('\n')"
                                >
                                    Add my IP
                                </button>
                            </p>
                            <p class="mt-1 text-xs text-slate-500">One IP or CIDR per line. IPv4 only in v1.</p>
                            <InputError class="mt-1" :message="form.errors['security.admin_ip_allowlist_text']" />
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Data retention</h4>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Automated nightly purge anonymizes expired lead PII and deletes old operational logs. Leads with open buyer return disputes are always skipped.
                    </p>

                    <div class="mt-4 space-y-4">
                        <div class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-600 dark:bg-slate-900/50">
                            <label class="flex items-start gap-3">
                                <input v-model="form.data_retention.purge_leads" type="checkbox" class="mt-1 rounded border-slate-300 text-indigo-600" />
                                <span>
                                    <span class="block text-sm font-medium text-slate-900 dark:text-white">Anonymize expired leads</span>
                                    <span class="mt-0.5 block text-sm text-slate-500 dark:text-slate-400">Redact PII from leads older than the retention window. Records remain for reporting.</span>
                                </span>
                            </label>
                            <div v-if="form.data_retention.purge_leads" class="mt-3 max-w-xs">
                                <InputLabel value="Lead retention (days)" />
                                <input v-model.number="form.data_retention.leads_retention_days" type="number" min="30" max="3650" class="form-input mt-1 w-full" />
                                <InputError class="mt-1" :message="form.errors['data_retention.leads_retention_days']" />
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-600 dark:bg-slate-900/50">
                            <label class="flex items-start gap-3">
                                <input v-model="form.data_retention.purge_logs" type="checkbox" class="mt-1 rounded border-slate-300 text-indigo-600" />
                                <span>
                                    <span class="block text-sm font-medium text-slate-900 dark:text-white">Trim operational logs</span>
                                    <span class="mt-0.5 block text-sm text-slate-500 dark:text-slate-400">Deletes access, API, audit, delivery, and system error logs past the retention window.</span>
                                </span>
                            </label>
                            <div v-if="form.data_retention.purge_logs" class="mt-3 max-w-xs">
                                <InputLabel value="Log retention (days)" />
                                <input v-model.number="form.data_retention.logs_retention_days" type="number" min="7" max="3650" class="form-input mt-1 w-full" />
                                <InputError class="mt-1" :message="form.errors['data_retention.logs_retention_days']" />
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-600 dark:bg-slate-900/50">
                            <label class="flex items-start gap-3">
                                <input v-model="form.data_retention.purge_message_events" type="checkbox" class="mt-1 rounded border-slate-300 text-indigo-600" />
                                <span>
                                    <span class="block text-sm font-medium text-slate-900 dark:text-white">Trim message events</span>
                                    <span class="mt-0.5 block text-sm text-slate-500 dark:text-slate-400">Deletes email open, click, bounce, and complaint events past the retention window.</span>
                                </span>
                            </label>
                            <div v-if="form.data_retention.purge_message_events" class="mt-3 max-w-xs">
                                <InputLabel value="Message event retention (days)" />
                                <input v-model.number="form.data_retention.message_events_retention_days" type="number" min="7" max="3650" class="form-input mt-1 w-full" />
                                <InputError class="mt-1" :message="form.errors['data_retention.message_events_retention_days']" />
                            </div>
                        </div>
                    </div>
                </div>

                <PrimaryButton :disabled="form.processing">Save Settings</PrimaryButton>
            </form>
        </Panel>
    </AuthenticatedLayout>
</template>
