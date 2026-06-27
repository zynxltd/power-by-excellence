<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import BuyerAccountPanel from '@/Components/Portal/BuyerAccountPanel.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { useBuyerPortalI18n } from '@/Composables/useBuyerPortalI18n';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    buyer: Object,
    account: Object,
    stats: Object,
    requirePrepay: Boolean,
    stripeEnabled: { type: Boolean, default: false },
    stripeTopUp: { type: Object, default: () => ({ min: 1, presets: [50, 100, 250, 500, 1000] }) },
    currency: String,
    transactions: Object,
});

const { t } = useBuyerPortalI18n();
const { formatMoney } = useMoneyFormat(props.currency);

const ledgerTypeLabel = (type) => t(`ledger.${type}`, {}) === `ledger.${type}` ? type : t(`ledger.${type}`);

const billingStrip = computed(() => [
    { label: t('billing.balance'), value: formatMoney(props.buyer.credit_balance), accent: props.account?.is_low_credit ? 'rose' : 'emerald' },
    { label: t('billing.spend_30d'), value: formatMoney(props.stats?.spend_30d ?? 0), accent: 'indigo' },
    { label: t('billing.prepay'), value: props.requirePrepay ? t('common.required') : t('common.optional'), accent: 'amber' },
]);

const presetAmounts = computed(() => props.stripeTopUp?.presets ?? [50, 100, 250, 500, 1000]);
const minTopUp = computed(() => props.stripeTopUp?.min ?? 1);
const topupForm = useForm({ amount: presetAmounts.value[0] ?? 100 });
const customAmount = ref('');

const submitTopup = (amount) => {
    topupForm.amount = amount;
    topupForm.post(route('portal.buyer.stripe.checkout'));
};

const submitCustomTopup = () => {
    const amount = parseFloat(customAmount.value);
    if (!amount || amount < minTopUp.value) return;
    submitTopup(amount);
};
</script>

<template>
    <Head :title="t('nav.billing')" />
    <AuthenticatedLayout>
        <PageHeader
            :title="t('billing.title')"
            :description="t('billing.description')"
        />

        <CompactStatStrip :items="billingStrip" :columns="3" class="mb-6" />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
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
                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ row.description }}</td>
                        </tr>
                    </DataTable>
                    <Pagination :links="transactions.links" />
                </Panel>
            </div>

            <BuyerAccountPanel :account="account" :currency="currency" />
        </div>
    </AuthenticatedLayout>
</template>
