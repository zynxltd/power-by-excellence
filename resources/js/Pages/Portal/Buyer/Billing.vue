<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    buyer: Object,
    requirePrepay: Boolean,
    currency: String,
    transactions: Object,
});

const { formatMoney } = useMoneyFormat(props.currency);
</script>

<template>
    <Head title="Billing" />
    <AuthenticatedLayout>
        <PageHeader
            title="Credits & Billing"
            description="View your credit balance, prepay status, and transaction history."
        />

        <CompactStatStrip
            :items="[
                { label: 'Balance', value: formatMoney(buyer.credit_balance), accent: 'emerald' },
                { label: 'Prepay', value: requirePrepay ? 'Required' : 'Optional', accent: 'amber' },
                { label: 'Currency', value: currency, accent: 'indigo' },
            ]"
            :columns="3"
            class="mb-6"
        />

        <Panel v-if="requirePrepay" class="mt-6">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                Your account uses <strong class="text-slate-900 dark:text-white">prepay billing</strong>. Leads are charged against your credit balance when sold.
                Contact your platform administrator to top up credit.
            </p>
        </Panel>

        <Panel title="Transaction History" class="mt-6" :padding="false">
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
                    <td class="px-6 py-4 capitalize text-slate-600 dark:text-slate-400">{{ t.type }}</td>
                    <td
                        class="px-6 py-4 font-medium"
                        :class="t.amount < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'"
                    >
                        {{ formatMoney(t.amount) }}
                    </td>
                    <td class="px-6 py-4 text-slate-900 dark:text-white">{{ formatMoney(t.balance_after) }}</td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ t.description }}</td>
                </tr>
            </DataTable>
        </Panel>
    </AuthenticatedLayout>
</template>
