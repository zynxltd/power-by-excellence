<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import PlatformLockImpactPanel from '@/Components/Billing/PlatformLockImpactPanel.vue';
import BrandLogo from '@/Components/BrandLogo.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    account: Object,
});

const { formatMoney } = useMoneyFormat(props.account.currency ?? 'GBP');

const form = useForm({
    monthly_rent: props.account.monthly_rent ?? '',
    contract_reference: props.account.contract_reference ?? '',
    billing_status: props.account.billing_status ?? 'active',
    billing_lock_reason: props.account.billing_lock_reason ?? '',
    billing_notes: props.account.billing_notes ?? '',
    billing_alert_emails: props.account.billing_alert_emails ?? '',
    subscription_plan: props.account.subscription_plan ?? 'starter',
});

const fraud = computed(() => props.account.fraud_protection ?? {});
const fraudIncludedOnPlan = computed(() => form.subscription_plan !== 'starter');
const isLocked = computed(() => props.account.status === 'locked');

const showLockDialog = ref(false);
const lockReason = ref('Platform rent overdue.');

const projectedMonthly = computed(() => {
    const rent = parseFloat(form.monthly_rent);
    if (Number.isNaN(rent)) return null;

    return rent;
});

const statItems = computed(() => [
    { label: 'Status', value: props.account.status?.replace(/_/g, ' ') ?? '-', accent: props.account.status === 'active' ? 'emerald' : props.account.status === 'past_due' ? 'amber' : 'rose' },
    {
        label: 'Monthly',
        value: projectedMonthly.value != null ? formatMoney(projectedMonthly.value) : '-',
        accent: 'indigo',
    },
    {
        label: 'Processing',
        value: props.account.can_process_leads ? 'On' : 'Off',
        accent: props.account.can_process_leads ? 'emerald' : 'rose',
    },
]);

const submit = () => {
    form.put(route('accounts.billing.update', props.account.id));
};

const confirmLock = () => {
    router.post(route('accounts.billing.lock', props.account.id), { reason: lockReason.value }, {
        onSuccess: () => {
            showLockDialog.value = false;
        },
    });
};

const quickUnlock = () => {
    if (!window.confirm(`Unlock ${props.account.name} and restore full platform access?`)) return;
    router.post(route('accounts.billing.unlock', props.account.id));
};
</script>

<template>
    <Head :title="`Tenant Billing - ${account.name}`" />
    <AuthenticatedLayout>
        <PageHeader
            :title="account.name"
            description="Platform rent, contract, and access control. Buyer credit billing is managed separately on the tenant portal."
        >
            <template #actions>
                <AppButton :href="route('accounts.billing.index')" variant="secondary">All tenants</AppButton>
                <AppButton
                    :href="route('accounts.visit', account.id)"
                    method="post"
                    variant="secondary"
                >
                    Open portal ↗
                </AppButton>
                <AppButton
                    v-if="isLocked"
                    variant="primary"
                    @click="quickUnlock"
                >
                    Unlock platform
                </AppButton>
                <AppButton
                    v-else
                    variant="secondary"
                    @click="showLockDialog = true"
                >
                    Lock platform
                </AppButton>
            </template>
        </PageHeader>

        <Panel class="mb-6 overflow-hidden p-0">
            <div class="flex flex-col gap-4 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-indigo-50/40 p-5 dark:border-slate-800 dark:from-slate-900 dark:to-indigo-950/20 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                        <img v-if="account.logo_url" :src="account.logo_url" :alt="account.name" class="h-full w-full object-contain p-1.5" />
                        <BrandLogo v-else size="sm" variant="dark" :brand-name="account.name" />
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ account.name }}</h2>
                            <StatusBadge :status="account.status" />
                        </div>
                        <p class="mt-0.5 font-mono text-sm text-slate-500">{{ account.domain }}</p>
                        <p v-if="account.contract_reference" class="mt-1 text-xs text-slate-500">
                            Contract <span class="font-mono">{{ account.contract_reference }}</span>
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 text-sm">
                    <span
                        class="inline-flex items-center rounded-full px-3 py-1 font-medium"
                        :class="account.can_accept_leads ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-400' : 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-400'"
                    >
                        Ingest {{ account.can_accept_leads ? 'accepting' : 'suspended' }}
                    </span>
                    <span
                        class="inline-flex items-center rounded-full px-3 py-1 font-medium"
                        :class="account.can_process_leads ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-400' : 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-400'"
                    >
                        Processing {{ account.can_process_leads ? 'operational' : 'blocked' }}
                    </span>
                </div>
            </div>
            <div class="p-4">
                <CompactStatStrip :items="statItems" />
            </div>
        </Panel>

        <div
            v-if="showLockDialog"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
            @click.self="showLockDialog = false"
        >
            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Lock platform</h3>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Suspends lead ingest, blocks processing, and redirects tenant admins to the billing lock page.
                </p>
                <div class="mt-4">
                    <InputLabel value="Reason shown to tenant admin" />
                    <textarea v-model="lockReason" rows="3" class="form-input mt-1 w-full" />
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <AppButton variant="secondary" @click="showLockDialog = false">Cancel</AppButton>
                    <AppButton variant="primary" @click="confirmLock">Lock platform</AppButton>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-1">
                <Panel>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Quick reference</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Plan</dt>
                            <dd class="font-medium capitalize text-slate-900 dark:text-white">{{ account.subscription_plan }}</dd>
                        </div>
                        <div v-if="account.locked_at" class="rounded-lg border border-rose-200 bg-rose-50 p-3 dark:border-rose-900 dark:bg-rose-950/30">
                            <dt class="text-xs font-semibold uppercase text-rose-700 dark:text-rose-300">Locked</dt>
                            <dd class="mt-1 text-sm text-rose-800 dark:text-rose-200">
                                Since <FormattedDate :value="account.locked_at" />
                                <span v-if="account.lock_reason"> - {{ account.lock_reason }}</span>
                            </dd>
                        </div>
                        <div v-if="fraud.usage_count != null">
                            <dt class="text-slate-500">Fraud usage</dt>
                            <dd class="font-medium text-slate-900 dark:text-white">
                                {{ fraud.usage_count?.toLocaleString() }}
                                <span v-if="fraud.monthly_cap"> / {{ fraud.monthly_cap?.toLocaleString() }}</span>
                                this month
                            </dd>
                        </div>
                    </dl>
                </Panel>

                <PlatformLockImpactPanel compact />
            </div>

            <Panel class="lg:col-span-2">
                <FormErrorSummary :errors="form.errors" />
                <form class="space-y-8" @submit.prevent="submit">
                    <section>
                        <div class="mb-4">
                            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Subscription plan</h3>
                            <p class="mt-1 text-sm text-slate-500">Controls platform tier and fraud protection entitlement.</p>
                        </div>
                        <div class="rounded-xl border border-indigo-200/80 bg-indigo-50/50 p-4 dark:border-indigo-500/30 dark:bg-indigo-950/20">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <InputLabel value="Subscription plan" />
                                    <select v-model="form.subscription_plan" class="form-select mt-1 w-full">
                                        <option value="starter">Starter - core platform only</option>
                                        <option value="growth">Growth - fraud included (25k/mo)</option>
                                        <option value="enterprise">Enterprise - fraud included (custom cap)</option>
                                    </select>
                                </div>
                                <div class="flex items-end">
                                    <p v-if="fraudIncludedOnPlan" class="text-sm text-emerald-700 dark:text-emerald-300">
                                        Fraud protection included on {{ form.subscription_plan }} plan.
                                    </p>
                                    <p v-else class="text-sm text-slate-600 dark:text-slate-400">
                                        Fraud protection not included on Starter. Upgrade to Growth or Enterprise to enable live checks.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section>
                        <div class="mb-4">
                            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Contract &amp; rent</h3>
                            <p class="mt-1 text-sm text-slate-500">What this tenant pays you monthly under their signed contract.</p>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <InputLabel :value="`Monthly rent (${account.currency})`" />
                                <TextInput v-model="form.monthly_rent" type="number" step="0.01" min="0" class="mt-1" placeholder="799" />
                                <InputError class="mt-1" :message="form.errors.monthly_rent" />
                            </div>
                            <div>
                                <InputLabel value="Contract reference" />
                                <TextInput v-model="form.contract_reference" class="mt-1" placeholder="MSA-2026-0042" />
                                <InputError class="mt-1" :message="form.errors.contract_reference" />
                            </div>
                        </div>
                        <p v-if="projectedMonthly != null" class="mt-3 text-sm text-slate-600 dark:text-slate-400">
                            Effective monthly bill:
                            <strong class="text-slate-900 dark:text-white">{{ formatMoney(projectedMonthly) }}</strong>
                        </p>
                    </section>

                    <section>
                        <div class="mb-4">
                            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Platform access</h3>
                            <p class="mt-1 text-sm text-slate-500">Past due shows a warning only. Locked suspends ingest and blocks processing.</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                            <div>
                                <InputLabel value="Platform status" />
                                <select v-model="form.billing_status" class="form-select mt-1 w-full max-w-md">
                                    <option value="active">Active - full access</option>
                                    <option value="past_due">Past due - warn only, processing continues</option>
                                    <option value="locked">Locked - suspend ingest &amp; block processing</option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.billing_status" />
                            </div>
                            <div v-if="form.billing_status === 'locked'" class="mt-4">
                                <InputLabel value="Lock reason (visible to tenant)" />
                                <TextInput v-model="form.billing_lock_reason" class="mt-1" placeholder="Platform rent overdue - contact billing@" />
                                <InputError class="mt-1" :message="form.errors.billing_lock_reason" />
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4">
                        <div>
                            <InputLabel value="Internal notes" />
                            <textarea v-model="form.billing_notes" rows="3" class="form-input mt-1 w-full" placeholder="Contract signed 1 Jan 2026. Invoiced via Xero." />
                            <InputError class="mt-1" :message="form.errors.billing_notes" />
                        </div>
                        <div>
                            <InputLabel value="Billing alert emails" />
                            <TextInput v-model="form.billing_alert_emails" class="mt-1" placeholder="billing@yourcompany.com" />
                            <p class="mt-1 text-xs text-slate-500">Comma-separated. Notified on past-due or lock events (when configured).</p>
                            <InputError class="mt-1" :message="form.errors.billing_alert_emails" />
                        </div>
                    </section>

                    <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-6 dark:border-slate-700">
                        <PrimaryButton :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : 'Save tenant billing' }}
                        </PrimaryButton>
                        <p class="text-xs text-slate-500">Changes apply immediately to platform access rules.</p>
                    </div>
                </form>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
