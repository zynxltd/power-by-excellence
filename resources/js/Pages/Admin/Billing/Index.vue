<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    buyers: Object,
    summary: Object,
    recentTransactions: Object,
});

const tab = ref('credits');
const { formatMoney } = useMoneyFormat(props.summary?.currency);

const billingStrip = computed(() => [
    { label: 'Credit pool', value: formatMoney(props.summary?.total_credit, { decimals: 0 }), accent: 'emerald' },
    { label: 'Active buyers', value: props.summary?.buyer_count ?? 0, accent: 'indigo' },
    { label: 'Txns today', value: props.summary?.transactions_today ?? 0, accent: 'cyan' },
    { label: 'Prepay', value: props.summary?.require_prepay ? 'Required' : 'Optional', accent: 'amber' },
]);

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
</script>

<template>
    <Head title="Billing" />
    <AuthenticatedLayout>
        <PageHeader
            title="Billing"
            description="Buyer credits, prepay settings, and platform usage."
        >
            <template #actions>
                <AppButton :href="route('billing.export-all')" variant="secondary" external>Export ledger CSV</AppButton>
                <AppButton :href="route('finance.index')" variant="secondary">Finance</AppButton>
                <AppButton :href="route('settings.edit')" variant="secondary">Billing Settings</AppButton>
            </template>
        </PageHeader>

        <CompactStatStrip :items="billingStrip" :columns="4" class="mb-6" />

        <div class="mt-6 flex gap-1 border-b border-slate-200 dark:border-slate-700">
            <button
                type="button"
                :class="[
                    'px-5 py-2.5 text-sm font-semibold uppercase tracking-wide transition',
                    tab === 'credits'
                        ? 'border-b-2 border-[var(--accent-bg)] text-slate-900 dark:text-white'
                        : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300',
                ]"
                @click="tab = 'credits'"
            >
                Credits & Ledger
            </button>
            <button
                type="button"
                :class="[
                    'px-5 py-2.5 text-sm font-semibold uppercase tracking-wide transition',
                    tab === 'usage'
                        ? 'border-b-2 border-[var(--accent-bg)] text-slate-900 dark:text-white'
                        : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300',
                ]"
                @click="tab = 'usage'"
            >
                Usage
            </button>
        </div>

        <template v-if="tab === 'credits'">
        <Panel title="Buyer Credit Balances" class="mt-6" :padding="false">
            <DataTable :empty="!buyers?.data?.length">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Buyer</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Reference</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Balance</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Transactions</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                </template>
                <tr v-for="b in buyers.data" :key="b.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ b.name }}</td>
                    <td class="px-6 py-4 font-mono text-xs text-slate-500">{{ b.reference }}</td>
                    <td class="px-6 py-4 font-semibold text-emerald-600 dark:text-emerald-400">
                        {{ formatMoney(b.credit_balance) }}
                    </td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ b.transaction_count }}</td>
                    <td class="px-6 py-4"><StatusBadge :status="b.status" /></td>
                    <td class="px-6 py-4 text-right">
                        <AppButton :href="route('billing.show', b.id)" variant="ghost">Manage ledger</AppButton>
                    </td>
                </tr>
            </DataTable>
            <Pagination v-if="buyers?.links" :links="buyers.links" class="border-t border-slate-200 px-4 py-3 dark:border-slate-700" />
        </Panel>

        <Panel title="Recent Transactions" class="mt-6" :padding="false">
            <DataTable :empty="!recentTransactions?.data?.length">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Buyer</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Description</th>
                </template>
                <tr v-for="t in recentTransactions.data" :key="t.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4"><FormattedDate :value="t.created_at" /></td>
                    <td class="px-6 py-4 text-slate-900 dark:text-white">{{ t.buyer?.name }}</td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ ledgerTypeLabel(t.type) }}</td>
                    <td
                        class="px-6 py-4 font-medium"
                        :class="t.amount < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'"
                    >
                        {{ formatMoney(t.amount) }}
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ t.description }}</td>
                </tr>
            </DataTable>
            <Pagination v-if="recentTransactions?.links" :links="recentTransactions.links" class="border-t border-slate-200 px-4 py-3 dark:border-slate-700" />
        </Panel>
        </template>

        <Panel v-else title="Platform Usage" class="mt-6">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <p class="text-sm font-medium text-slate-900 dark:text-white">Prepay enforcement</p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ summary.require_prepay ? 'Buyers must have credit before leads are sold.' : 'Credit checks are optional - buyers can receive leads without prepay.' }}
                    </p>
                    <AppButton :href="route('settings.edit')" variant="secondary" class="mt-3">Change in Settings</AppButton>
                </div>
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <p class="text-sm font-medium text-slate-900 dark:text-white">Currency</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ summary.currency }}</p>
                    <p class="mt-1 text-sm text-slate-500">Default for campaigns, billing, and reports. Change in <Link :href="route('settings.edit')" class="text-indigo-600 hover:underline">Settings</Link>.</p>
                </div>
            </div>
        </Panel>
    </AuthenticatedLayout>
</template>
