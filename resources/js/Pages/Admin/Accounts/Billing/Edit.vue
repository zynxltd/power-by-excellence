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
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    account: Object,
});

const form = useForm({
    monthly_rent: props.account.monthly_rent ?? '',
    contract_reference: props.account.contract_reference ?? '',
    billing_due_at: props.account.billing_due_at ?? '',
    billing_status: props.account.billing_status ?? 'active',
    billing_lock_reason: props.account.billing_lock_reason ?? '',
    billing_notes: props.account.billing_notes ?? '',
    billing_alert_emails: props.account.billing_alert_emails ?? '',
});

const isLocked = computed(() => props.account.status === 'locked');

const submit = () => {
    form.put(route('accounts.billing.update', props.account.id));
};

const quickLock = () => {
    const reason = window.prompt('Lock reason (shown to tenant admin):', 'Platform rent overdue.');
    if (reason === null) return;
    router.post(route('accounts.billing.lock', props.account.id), { reason });
};

const quickUnlock = () => {
    if (!window.confirm(`Unlock ${props.account.name} and restore full platform access?`)) return;
    router.post(route('accounts.billing.unlock', props.account.id));
};
</script>

<template>
    <Head :title="`Tenant Billing — ${account.name}`" />
    <AuthenticatedLayout>
        <PageHeader
            :title="`Tenant billing — ${account.name}`"
            :description="`Platform rent and contract for ${account.domain}. Buyer credit billing is managed separately under Account → Buyer Billing on the tenant portal.`"
        >
            <template #actions>
                <AppButton :href="route('accounts.billing.index')" variant="secondary">All tenants</AppButton>
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
                    @click="quickLock"
                >
                    Lock platform
                </AppButton>
            </template>
        </PageHeader>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <Panel>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Billing status</p>
                <div class="mt-2">
                    <StatusBadge :status="account.status" />
                </div>
            </Panel>
            <Panel>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lead ingest</p>
                <p class="mt-2 text-sm font-medium" :class="account.can_accept_leads ? 'text-emerald-600' : 'text-rose-600'">
                    {{ account.can_accept_leads ? 'Accepting' : 'Suspended' }}
                </p>
            </Panel>
            <Panel>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Processing</p>
                <p class="mt-2 text-sm font-medium" :class="account.can_process_leads ? 'text-emerald-600' : 'text-rose-600'">
                    {{ account.can_process_leads ? 'Operational' : 'Blocked' }}
                </p>
            </Panel>
        </div>

        <Panel class="max-w-3xl">
            <FormErrorSummary :errors="form.errors" />
            <form class="space-y-6" @submit.prevent="submit">
                <div class="rounded-xl border border-indigo-200/80 bg-indigo-50/50 p-4 dark:border-indigo-500/30 dark:bg-indigo-950/20">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Contract &amp; rent</h3>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Internal record of what this tenant pays you monthly under their signed contract.</p>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
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
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Billing cycle</h3>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Next rent due date" />
                            <TextInput v-model="form.billing_due_at" type="date" class="mt-1 w-full" />
                            <p class="mt-1 text-xs text-slate-500">Past this date the platform shows as past due until paid or extended.</p>
                            <InputError class="mt-1" :message="form.errors.billing_due_at" />
                        </div>
                        <div>
                            <InputLabel value="Platform status" />
                            <select v-model="form.billing_status" class="form-select mt-1 w-full">
                                <option value="active">Active — full access</option>
                                <option value="past_due">Past due — warn only</option>
                                <option value="locked">Locked — suspend ingest &amp; admin</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.billing_status" />
                        </div>
                    </div>
                    <div v-if="form.billing_status === 'locked'" class="mt-4">
                        <InputLabel value="Lock reason (visible to tenant)" />
                        <TextInput v-model="form.billing_lock_reason" class="mt-1" placeholder="Platform rent overdue — contact billing@" />
                        <InputError class="mt-1" :message="form.errors.billing_lock_reason" />
                    </div>
                    <div v-if="account.locked_at" class="mt-4 text-sm text-slate-600 dark:text-slate-400">
                        Locked since <FormattedDate :value="account.locked_at" /> —
                        {{ account.lock_reason || 'No reason recorded' }}
                    </div>
                </div>

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

                <PrimaryButton :disabled="form.processing">Save tenant billing</PrimaryButton>
            </form>
        </Panel>
    </AuthenticatedLayout>
</template>
