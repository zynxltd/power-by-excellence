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

const { t } = useBuyerPortalI18n();
const { formatMoney } = useMoneyFormat(props.currency);

const ledgerTypeLabel = (type) => t(`ledger.${type}`, {}) === `ledger.${type}` ? type : t(`ledger.${type}`);

const billingStrip = computed(() => [
    { label: t('billing.balance'), value: formatMoney(props.buyer.credit_balance), accent: props.account?.is_low_credit ? 'rose' : 'emerald' },
    { label: t('billing.spend_30d'), value: formatMoney(props.stats?.spend_30d ?? 0), accent: 'indigo' },
    { label: t('billing.prepay'), value: props.requirePrepay ? t('common.required') : t('common.optional'), accent: 'amber' },
]);
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
