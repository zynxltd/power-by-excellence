<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    supplier: Object,
    currency: String,
    summary: Object,
    recentPayouts: Array,
});

const symbol = ({ GBP: '£', USD: '$', EUR: '€' }[props.currency] ?? props.currency + ' ');
</script>

<template>
    <Head title="Payouts & Revenue" />
    <AuthenticatedLayout>
        <PageHeader
            title="Payouts & Revenue"
            description="Track your lead payouts, sold volume, and revenue earned on the platform."
        />

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
            <StatCard label="Total Payouts" :value="symbol + summary.total_payout" accent="emerald" />
            <StatCard label="This Month" :value="symbol + summary.payout_this_month" accent="indigo" />
            <StatCard label="Sold Leads" :value="summary.sold_count" accent="cyan" />
        </div>

        <Panel class="mt-6">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                Payouts are calculated per lead when sold through the distribution engine. Revenue is tracked in
                <strong class="text-slate-900 dark:text-white">{{ currency }}</strong> based on your campaign payout settings.
            </p>
        </Panel>

        <Panel title="Recent Payouts" class="mt-6" :padding="false">
            <DataTable :empty="!recentPayouts?.length">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Lead</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Payout</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Sold At</th>
                </template>
                <tr v-for="p in recentPayouts" :key="p.uuid" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4 font-mono text-xs text-slate-600 dark:text-slate-400">{{ p.uuid?.slice(0, 12) }}…</td>
                    <td class="px-6 py-4 font-semibold text-emerald-600 dark:text-emerald-400">{{ symbol }}{{ p.payout }}</td>
                    <td class="px-6 py-4"><FormattedDate :value="p.distributed_at" /></td>
                </tr>
            </DataTable>
        </Panel>
    </AuthenticatedLayout>
</template>
