<script setup>
import BuyerPortalLayout from '@/Layouts/BuyerPortalLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import BuyerAccountPanel from '@/Components/Portal/BuyerAccountPanel.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { useBuyerPortalI18n } from '@/Composables/useBuyerPortalI18n';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    buyer: Object,
    account: Object,
    stats: Object,
    requirePrepay: Boolean,
    stripeEnabled: { type: Boolean, default: false },
    stripeTopUp: { type: Object, default: () => ({ min: 1, presets: [50, 100, 250, 500, 1000] }) },
    stripeSubscriptionsEnabled: { type: Boolean, default: false },
    stripeSubscriptionPlans: { type: Array, default: () => [] },
    stripeSubscription: { type: Object, default: null },
    currency: String,
    transactions: Object,
    pendingCallReturns: { type: Number, default: 0 },
});

const page = usePage();
const { t } = useBuyerPortalI18n();
const { formatMoney } = useMoneyFormat(props.currency);

const ledgerTypeLabel = (type) => t(`ledger.${type}`, {}) === `ledger.${type}` ? type : t(`ledger.${type}`);

const billingStrip = computed(() => [
    { label: t('billing.balance'), value: formatMoney(props.buyer.credit_balance), accent: props.account?.is_low_credit ? 'rose' : 'emerald' },
    { label: t('billing.spend_30d'), value: formatMoney(props.stats?.spend_30d ?? 0), accent: 'indigo' },
    { label: t('billing.prepay'), value: props.requirePrepay ? t('common.required') : t('common.optional'), accent: 'amber' },
    ...(props.pendingCallReturns > 0 ? [{ label: 'Pending call returns', value: String(props.pendingCallReturns), accent: 'amber' }] : []),
]);

const presetAmounts = computed(() => props.stripeTopUp?.presets ?? [50, 100, 250, 500, 1000]);
const minTopUp = computed(() => props.stripeTopUp?.min ?? 1);
const topupForm = useForm({ amount: presetAmounts.value[0] ?? 100 });
const customAmount = ref('');

const subscribeForm = useForm({ price_id: '' });
const cancelForm = useForm({});
const reactivateForm = useForm({});

const subscriptionPeriodEnd = computed(() => {
    const ts = props.stripeSubscription?.current_period_end;
    if (!ts) return null;
    return new Date(ts * 1000).toLocaleDateString(undefined, { dateStyle: 'medium' });
});

const subscriptionStatusLabel = computed(() => {
    const sub = props.stripeSubscription;
    if (!sub) return '';
    if (sub.cancel_at_period_end && sub.is_active) {
        return `Cancels ${subscriptionPeriodEnd.value ?? 'at period end'}`;
    }
    return sub.status;
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);

const submitTopup = (amount) => {
    topupForm.amount = amount;
    topupForm.post(route('portal.buyer.stripe.checkout'));
};

const submitCustomTopup = () => {
    const amount = parseFloat(customAmount.value);
    if (!amount || amount < minTopUp.value) return;
    submitTopup(amount);
};

const subscribeToPlan = (priceId) => {
    subscribeForm.price_id = priceId;
    subscribeForm.post(route('portal.buyer.stripe.subscribe'));
};

const cancelSubscription = () => {
    cancelForm.post(route('portal.buyer.stripe.subscription.cancel'));
};

const reactivateSubscription = () => {
    reactivateForm.post(route('portal.buyer.stripe.subscription.reactivate'));
};
</script>

<template>
    <Head :title="t('nav.billing')" />
    <BuyerPortalLayout>
        <PageHeader
            :title="t('billing.title')"
            :description="t('billing.description')"
        />

        <CompactStatStrip :items="billingStrip" :columns="3" class="mb-6" />

        <p v-if="flashSuccess" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ flashSuccess }}
        </p>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <Panel v-if="stripeSubscriptionsEnabled" title="Subscription">
                    <template v-if="stripeSubscription?.is_active">
                        <div class="space-y-3 text-sm text-slate-600 dark:text-slate-400">
                            <p>
                                <span class="font-semibold text-slate-900 dark:text-white">{{ stripeSubscription.label ?? 'Active plan' }}</span>
                                — {{ subscriptionStatusLabel }}
                            </p>
                            <p v-if="subscriptionPeriodEnd && !stripeSubscription.cancel_at_period_end">
                                Renews {{ subscriptionPeriodEnd }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-if="!stripeSubscription.cancel_at_period_end"
                                    type="button"
                                    class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/30"
                                    :disabled="cancelForm.processing"
                                    @click="cancelSubscription"
                                >
                                    Cancel at period end
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
                                    :disabled="reactivateForm.processing"
                                    @click="reactivateSubscription"
                                >
                                    Reactivate subscription
                                </button>
                            </div>
                            <p v-if="cancelForm.errors.subscription || reactivateForm.errors.subscription" class="text-rose-600 dark:text-rose-400">
                                {{ cancelForm.errors.subscription || reactivateForm.errors.subscription }}
                            </p>
                        </div>
                    </template>
                    <template v-else>
                        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                            Subscribe to a recurring credit plan. Your balance is credited when each invoice is paid.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="plan in stripeSubscriptionPlans"
                                :key="plan.price_id"
                                type="button"
                                class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-indigo-300 hover:bg-indigo-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-indigo-950/30"
                                :disabled="subscribeForm.processing"
                                @click="subscribeToPlan(plan.price_id)"
                            >
                                {{ plan.label }}
                                <span v-if="plan.credit_amount" class="ml-1 text-slate-500 dark:text-slate-400">({{ formatMoney(plan.credit_amount) }})</span>
                            </button>
                        </div>
                        <p v-if="subscribeForm.errors.price_id" class="mt-2 text-sm text-rose-600 dark:text-rose-400">
                            {{ subscribeForm.errors.price_id }}
                        </p>
                    </template>
                </Panel>

                <Panel v-if="stripeEnabled" title="Top up credit">
                    <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                        Add credit instantly via Stripe Checkout. Your balance updates when payment completes.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="amount in presetAmounts"
                            :key="amount"
                            type="button"
                            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-indigo-300 hover:bg-indigo-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-indigo-950/30"
                            :disabled="topupForm.processing"
                            @click="submitTopup(amount)"
                        >
                            {{ formatMoney(amount) }}
                        </button>
                    </div>
                    <div class="mt-4 flex flex-wrap items-end gap-2">
                        <div class="min-w-[8rem]">
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400">Custom amount</label>
                            <input v-model="customAmount" type="number" min="1" step="0.01" class="form-input mt-1 w-full" :placeholder="currency" />
                        </div>
                        <button
                            type="button"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
                            :disabled="topupForm.processing"
                            @click="submitCustomTopup"
                        >
                            Pay with Stripe
                        </button>
                    </div>
                </Panel>

                <Panel v-if="requirePrepay">
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        <strong class="text-slate-900 dark:text-white">{{ t('billing.prepay_notice_title') }}</strong> —
                        {{ t('billing.prepay_notice') }}
                    </p>
                </Panel>

                <Panel :title="t('billing.transactions')" :padding="false">
                    <DataTable :empty="!transactions.data?.length">
                        <template #head>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('common.date') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('common.type') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('common.amount') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('common.balance') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('common.description') }}</th>
                        </template>
                        <tr v-for="row in transactions.data" :key="row.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-6 py-4"><FormattedDate :value="row.created_at" /></td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ ledgerTypeLabel(row.type) }}</td>
                            <td
                                class="px-6 py-4 font-medium"
                                :class="Number(row.amount) < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'"
                            >
                                {{ formatMoney(row.amount) }}
                            </td>
                            <td class="px-6 py-4 text-slate-900 dark:text-white">{{ formatMoney(row.balance_after) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                {{ row.description }}
                                <span v-if="row.meta?.call_session_uuid" class="block text-xs text-slate-400">Call {{ row.meta.call_session_uuid }}</span>
                            </td>
                        </tr>
                    </DataTable>
                    <Pagination :links="transactions.links" />
                </Panel>
            </div>

            <BuyerAccountPanel :account="account" :currency="currency" />
        </div>
    </BuyerPortalLayout>
</template>
