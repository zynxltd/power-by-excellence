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
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    buyer: Object,
    account: Object,
    stats: Object,
    requirePrepay: Boolean,
    currency: String,
    transactions: Object,
});

const { formatMoney } = useMoneyFormat(props.currency);

const ledgerTypeLabel = (type) => ({
    credit: 'Credit (top-up)',
    debit: 'Lead purchase',
    goodwill: 'Goodwill credit',
    correction: 'Balance correction',
    refund: 'Refund',
    manual_debit: 'Manual debit',
    chargeback: 'Chargeback',
    adjustment: 'General adjustment',
}[type] ?? type);

const billingStrip = computed(() => [
    { label: 'Balance', value: formatMoney(props.buyer.credit_balance), accent: props.account?.is_low_credit ? 'rose' : 'emerald' },
    { label: 'Spend (30d)', value: formatMoney(props.stats?.spend_30d ?? 0), accent: 'indigo' },
    { label: 'Prepay', value: props.requirePrepay ? 'Required' : 'Optional', accent: 'amber' },
]);
</script>

<template>
    <Head title="Billing" />
    <AuthenticatedLayout>
        <PageHeader
            title="Credits & Billing"
            description="View balance and ledger activity. Credit top-ups are arranged by your platform administrator."
        />

        <CompactStatStrip :items="billingStrip" :columns="3" class="mb-6" />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <Panel v-if="requirePrepay">
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        <strong class="text-slate-900 dark:text-white">Prepay billing</strong> — leads debit your balance when sold.
                        Contact your account manager to top up credit. Buyers cannot self-fund in the portal.
                    </p>
                </Panel>

                <Panel title="Transaction history" :padding="false">
                    <DataTable :empty="!transactions.data?.length">
                        <template #head>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Balance</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Description</th>
                        </template>
                        <tr v-for="t in transactions.data" :key="t.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-6 py-4"><FormattedDate :value="t.created_at" /></td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ ledgerTypeLabel(t.type) }}</td>
                            <td
                                class="px-6 py-4 font-medium"
                                :class="Number(t.amount) < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'"
                            >
                                {{ formatMoney(t.amount) }}
                            </td>
                            <td class="px-6 py-4 text-slate-900 dark:text-white">{{ formatMoney(t.balance_after) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ t.description }}</td>
                        </tr>
                    </DataTable>
                    <Pagination :links="transactions.links" />
                </Panel>
            </div>

            <BuyerAccountPanel :account="account" :currency="currency" />
        </div>
    </AuthenticatedLayout>
</template>
