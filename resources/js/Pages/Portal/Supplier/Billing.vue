<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import SupplierAccountPanel from '@/Components/Portal/SupplierAccountPanel.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    supplier: Object,
    account: Object,
    stats: Object,
    currency: String,
    summary: Object,
    payouts: Object,
});

const { formatMoney } = useMoneyFormat(props.currency);
</script>

<template>
    <Head title="Payouts & Revenue" />
    <AuthenticatedLayout>
        <PageHeader
            title="Payouts & Revenue"
            description="Track your lead payouts, sold volume, and revenue earned on the platform."
        />

        <CompactStatStrip
            :items="[
                { label: 'Total payouts', value: formatMoney(summary.total_payout), accent: 'emerald' },
                { label: 'This month', value: formatMoney(summary.payout_this_month), accent: 'indigo' },
                { label: 'Sold leads', value: summary.sold_count, accent: 'cyan' },
                { label: 'Payout (30d)', value: formatMoney(stats.payout_30d), accent: 'violet' },
            ]"
            :columns="4"
            class="mb-6"
        />

        <div class="grid gap-6 lg:grid-cols-4">
            <div class="space-y-6 lg:col-span-3">
                <Panel>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Payouts are calculated per lead when sold through the distribution engine. Revenue is tracked in
                        <strong class="text-slate-900 dark:text-white">{{ currency }}</strong> based on your campaign payout settings.
                    </p>
                </Panel>

                <Panel title="Payout history" :padding="false">
                    <DataTable :empty="!payouts.data?.length">
                        <template #head>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Lead</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">SID</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Payout</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Sold at</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500" />
                        </template>
                        <tr v-for="p in payouts.data" :key="p.uuid" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-6 py-4 font-mono text-xs text-slate-600 dark:text-slate-400">{{ p.uuid?.slice(0, 12) }}…</td>
                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ p.campaign || '—' }}</td>
                            <td class="px-6 py-4 font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ p.sid || '—' }}</td>
                            <td class="px-6 py-4 font-semibold text-emerald-600 dark:text-emerald-400">{{ formatMoney(p.payout) }}</td>
                            <td class="px-6 py-4"><FormattedDate :value="p.distributed_at" /></td>
                            <td class="px-6 py-4 text-right">
                                <Link :href="route('portal.supplier.leads.show', p.uuid)" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">View</Link>
                            </td>
                        </tr>
                    </DataTable>
                    <Pagination :links="payouts.links" />
                </Panel>
            </div>

            <SupplierAccountPanel :account="account" :currency="currency" />
        </div>
    </AuthenticatedLayout>
</template>
