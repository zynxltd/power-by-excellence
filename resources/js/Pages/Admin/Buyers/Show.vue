<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import ClickableTableRow from '@/Components/UI/ClickableTableRow.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import ManagementHubNav from '@/Components/UI/ManagementHubNav.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    buyer: Object,
    recentLeads: Array,
    recentTransactions: Array,
    isOperational: Boolean,
    portalUser: Object,
    currency: String,
});

const { formatMoney } = useMoneyFormat(props.currency);
</script>

<template>
    <Head :title="buyer.name" />
    <AuthenticatedLayout>
        <PageHeader :title="buyer.name" :description="`Reference: ${buyer.reference}`">
            <template #actions>
                <AppButton :href="route('billing.show', buyer.id)" variant="secondary">Billing ledger</AppButton>
                <AppButton :href="route('buyers.edit', buyer.id)">Edit buyer</AppButton>
            </template>
        </PageHeader>

        <ManagementHubNav type="buyer" :entity="buyer" />

        <div v-if="!isOperational" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
            This buyer cannot receive leads — check status, credit balance, or account billing.
        </div>

        <Panel v-if="portalUser" title="Buyer portal" class="mb-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Portal login: <strong>{{ portalUser.email }}</strong>
                    <span class="ml-2 text-slate-500">· Buyers access leads at</span>
                    <Link :href="route('portal.buyer.dashboard')" class="text-indigo-600 hover:underline">/portal/buyer</Link>
                </p>
                <AppButton :href="route('impersonate.start', portalUser.id)" method="post" variant="secondary">
                    Log in as buyer
                </AppButton>
            </div>
        </Panel>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <StatCard label="Credit balance" :value="formatMoney(buyer.credit_balance)" accent="emerald" />
            <StatCard label="Leads purchased" :value="buyer.leads_count" accent="indigo" />
            <StatCard label="Deliveries" :value="buyer.deliveries_count" accent="cyan" />
            <StatCard label="Status" :value="buyer.status" accent="amber" />
        </div>

        <Panel title="Linked deliveries" class="mt-6" :padding="false">
            <DataTable :empty="!buyer.deliveries?.length">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Method</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                </template>
                <ClickableTableRow v-for="d in buyer.deliveries" :key="d.id" :href="route('deliveries.show', d.id)">
                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ d.name }}</td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ d.campaign?.name }}</td>
                    <td class="px-6 py-4 capitalize text-slate-600 dark:text-slate-400">{{ d.method?.replace?.(/_/g, ' ') ?? d.method }}</td>
                    <td class="px-6 py-4"><StatusBadge :status="d.status" /></td>
                </ClickableTableRow>
            </DataTable>
        </Panel>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <Panel title="Recent purchased leads" :padding="false">
                <DataTable :empty="!recentLeads?.length">
                    <template #head>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Lead</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Revenue</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                    </template>
                    <ClickableTableRow v-for="lead in recentLeads" :key="lead.id" :href="route('leads.show', lead.id)">
                        <td class="px-6 py-4 font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ lead.uuid?.slice(0, 10) }}…</td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ lead.campaign?.name }}</td>
                        <td class="px-6 py-4 font-medium text-emerald-600 dark:text-emerald-400">{{ formatMoney(lead.financials?.revenue ?? 0) }}</td>
                        <td class="px-6 py-4"><FormattedDate :value="lead.distributed_at" format="relative" /></td>
                    </ClickableTableRow>
                </DataTable>
            </Panel>

            <Panel title="Recent transactions" :padding="false">
                <DataTable :empty="!recentTransactions?.length">
                    <template #head>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Amount</th>
                    </template>
                    <tr v-for="t in recentTransactions" :key="t.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-6 py-4"><FormattedDate :value="t.created_at" /></td>
                        <td class="px-6 py-4 capitalize text-slate-600 dark:text-slate-400">{{ t.type }}</td>
                        <td class="px-6 py-4 font-medium" :class="t.amount < 0 ? 'text-rose-600' : 'text-emerald-600'">{{ formatMoney(t.amount) }}</td>
                    </tr>
                </DataTable>
                <div class="border-t border-slate-100 px-6 py-3 dark:border-slate-800">
                    <Link :href="route('billing.show', buyer.id)" class="text-sm font-medium text-indigo-600 dark:text-indigo-400">Full ledger →</Link>
                </div>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
