<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import RevenueProjections from '@/Components/Billing/RevenueProjections.vue';
import PlatformLockImpactPanel from '@/Components/Billing/PlatformLockImpactPanel.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    currentAccountId: { type: Number, default: null },
    summary: {
        type: Object,
        default: () => ({ total: 0, active: 0, past_due: 0, locked: 0, total_mrr: 0, currency: 'GBP' }),
    },
});

const { formatMoney } = useMoneyFormat();

const statusFilter = ref('all');
const search = ref('');

const statItems = computed(() => [
    { label: 'Platforms', value: props.summary.total ?? 0 },
    { label: 'Active', value: props.summary.active ?? 0, accent: 'emerald' },
    { label: 'Past due', value: props.summary.past_due ?? 0, accent: props.summary.past_due ? 'amber' : undefined },
    { label: 'Locked', value: props.summary.locked ?? 0, accent: props.summary.locked ? 'rose' : undefined },
    {
        label: 'Portfolio MRR',
        value: formatMoney(props.summary.total_mrr ?? 0, { currency: props.summary.currency ?? 'GBP', decimals: 0 }),
        accent: 'indigo',
    },
]);

const filteredAccounts = computed(() => {
    let rows = props.accounts;

    if (statusFilter.value !== 'all') {
        rows = rows.filter((a) => a.status === statusFilter.value);
    }

    const q = search.value.trim().toLowerCase();
    if (q) {
        rows = rows.filter(
            (a) =>
                a.name?.toLowerCase().includes(q)
                || a.slug?.toLowerCase().includes(q)
                || a.domain?.toLowerCase().includes(q)
                || a.contract_reference?.toLowerCase().includes(q),
        );
    }

    return rows;
});

const overdueCount = computed(() => (props.summary.past_due ?? 0) + (props.summary.locked ?? 0));

const statusTabs = [
    { key: 'all', label: 'All' },
    { key: 'active', label: 'Active' },
    { key: 'past_due', label: 'Past due' },
    { key: 'locked', label: 'Locked' },
];

const rowClass = (account) => {
    if (account.status === 'locked') {
        return 'bg-rose-50/60 dark:bg-rose-950/20';
    }
    if (account.status === 'past_due') {
        return 'bg-amber-50/50 dark:bg-amber-950/15';
    }

    return 'hover:bg-slate-50 dark:hover:bg-slate-800/50';
};
</script>

<template>
    <Head title="Tenant Billing" />
    <AuthenticatedLayout>
        <PageHeader
            title="Tenant billing"
            description="Platform rent, contracts, and access control for each partner tenant. Lock suspends ingest and blocks processing; past due warns only."
        >
            <template #actions>
                <AppButton :href="route('accounts.index')" variant="secondary">Partner platforms</AppButton>
            </template>
        </PageHeader>

        <CompactStatStrip :items="statItems" class="mb-6" />

        <div
            v-if="overdueCount"
            class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
        >
            <p>
                <strong>{{ overdueCount }}</strong> platform{{ overdueCount === 1 ? '' : 's' }} need attention - past due or locked.
            </p>
            <button
                type="button"
                class="font-semibold text-amber-800 underline decoration-amber-300 underline-offset-2 hover:text-amber-950 dark:text-amber-200"
                @click="statusFilter = overdueCount === summary.locked ? 'locked' : 'past_due'"
            >
                Show affected →
            </button>
        </div>

        <details class="group mb-6 rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <summary class="cursor-pointer list-none px-4 py-3 text-sm font-semibold text-slate-900 dark:text-white [&::-webkit-details-marker]:hidden">
                <span class="inline-flex items-center gap-2">
                    <span class="text-slate-400 transition group-open:rotate-90">▸</span>
                    Revenue projections (portfolio modelling)
                </span>
            </summary>
            <div class="border-t border-slate-200 p-4 dark:border-slate-800">
                <RevenueProjections />
            </div>
        </details>

        <Panel class="mb-4" :padding="false">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="tab in statusTabs"
                        :key="tab.key"
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                        :class="statusFilter === tab.key
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                        @click="statusFilter = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search platform, domain, contract…"
                    class="form-input w-full sm:max-w-xs"
                />
            </div>

            <DataTable :empty="!filteredAccounts.length">
                <template #head>
                    <th class="text-left">Platform</th>
                    <th class="text-left">Status</th>
                    <th class="text-left">Plan</th>
                    <th class="text-left">Monthly</th>
                    <th class="text-left">Access</th>
                    <th class="text-right">Actions</th>
                </template>
                <tr
                    v-for="a in filteredAccounts"
                    :key="a.id"
                    :class="['transition', rowClass(a)]"
                >
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
                                <img v-if="a.logo_url" :src="a.logo_url" :alt="a.name" class="h-full w-full object-contain p-1" />
                                <span v-else class="text-xs font-bold text-indigo-600">{{ a.name?.charAt(0) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate font-medium text-slate-900 dark:text-white">{{ a.name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ a.slug }} · {{ a.domain }}</p>
                                <p v-if="a.contract_reference" class="truncate font-mono text-[11px] text-slate-400">{{ a.contract_reference }}</p>
                            </div>
                        </div>
                    </td>
                    <td>
                        <StatusBadge :status="a.status" />
                    </td>
                    <td>
                        <p class="capitalize text-slate-700 dark:text-slate-300">{{ a.subscription_plan || 'starter' }}</p>
                        <p class="text-xs text-slate-500">
                            Fraud
                            <span v-if="a.fraud_protection?.plan_entitled" class="text-emerald-600 dark:text-emerald-400">on</span>
                            <span v-else class="text-slate-400">off</span>
                        </p>
                    </td>
                    <td class="text-slate-700 dark:text-slate-300">
                        <p v-if="a.effective_monthly != null" class="font-medium">
                            {{ formatMoney(a.effective_monthly, { currency: a.currency }) }}
                        </p>
                        <p v-else class="text-slate-400">-</p>
                        <p v-if="a.effective_monthly != null && a.monthly_rent != null && a.effective_monthly !== Number(a.monthly_rent)" class="text-[11px] text-slate-500">
                            incl. add-ons
                        </p>
                    </td>
                    <td>
                        <div class="space-y-1 text-xs">
                            <p :class="a.can_accept_leads ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                Ingest {{ a.can_accept_leads ? 'on' : 'off' }}
                            </p>
                            <p :class="a.can_process_leads ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                Processing {{ a.can_process_leads ? 'on' : 'off' }}
                            </p>
                        </div>
                    </td>
                    <td class="text-right">
                        <div class="flex flex-wrap justify-end gap-2">
                            <AppButton :href="route('accounts.billing.edit', a.id)" variant="primary">Manage</AppButton>
                            <AppButton
                                :href="route('accounts.visit', a.id)"
                                method="post"
                                variant="secondary"
                            >
                                Portal ↗
                            </AppButton>
                        </div>
                    </td>
                </tr>
                <template #empty>
                    <p class="py-8 text-center text-sm text-slate-500">No platforms match your filters.</p>
                </template>
            </DataTable>
        </Panel>

        <details class="group mt-6 rounded-xl border border-slate-200 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-900/40">
            <summary class="cursor-pointer list-none px-4 py-3 text-sm font-semibold text-slate-900 dark:text-white [&::-webkit-details-marker]:hidden">
                <span class="inline-flex items-center gap-2">
                    <span class="text-slate-400 transition group-open:rotate-90">▸</span>
                    What each billing status affects
                </span>
            </summary>
            <div class="border-t border-slate-200 p-4 dark:border-slate-700">
                <PlatformLockImpactPanel compact />
            </div>
        </details>
    </AuthenticatedLayout>
</template>
