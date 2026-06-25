<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import TenantContextBanner from '@/Components/UI/TenantContextBanner.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    days: Number,
    summary: Object,
    buyers: Array,
    suppliers: Array,
    accountBilling: Object,
    currency: String,
});

const selectedDays = ref(props.days);
const { formatMoney } = useMoneyFormat(props.currency);
const applyDays = (d) => router.get(route('finance.index'), { days: d }, { preserveState: true, replace: true });
watch(() => props.days, (d) => { selectedDays.value = d; });
</script>

<template>
    <Head title="Finance" />
    <AuthenticatedLayout>
        <PageHeader title="Finance" description="Track buyer credits, supplier payouts, and platform margin. Drill into billing or partner records.">
            <template #actions>
                <AppButton :href="route('billing.index')" variant="secondary">Buyer billing</AppButton>
                <AppButton :href="route('reports.index')" variant="secondary">Reports</AppButton>
                <div class="flex rounded-lg border border-slate-200 p-0.5 dark:border-slate-700">
                    <button v-for="d in [7, 14, 30, 90]" :key="d" type="button" :class="['rounded-md px-3 py-1.5 text-xs font-semibold transition', selectedDays === d ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400']" @click="applyDays(d)">{{ d }}d</button>
                </div>
            </template>
        </PageHeader>

        <TenantContextBanner />

        <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Revenue ({{ days }}d)</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600">{{ formatMoney(summary?.revenue) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Supplier payout</p>
                <p class="mt-1 text-2xl font-bold text-amber-600">{{ formatMoney(summary?.payout) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Margin</p>
                <p class="mt-1 text-2xl font-bold text-cyan-600">{{ formatMoney(summary?.margin) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Buyer credit pool</p>
                <p class="mt-1 text-2xl font-bold">{{ formatMoney(summary?.buyer_credit_total) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Ledger txns</p>
                <p class="mt-1 text-2xl font-bold">{{ summary?.transactions_period ?? 0 }}</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <Panel title="Buyers — revenue & credit" :padding="false">
                <DataTable :empty="!buyers?.length">
                    <template #head>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Buyer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Sold</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Revenue</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Credit</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">Actions</th>
                    </template>
                    <tr v-for="b in buyers" :key="b.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-3">
                            <Link :href="route('buyers.show', b.id)" class="font-medium text-indigo-600 hover:underline">{{ b.name }}</Link>
                            <p class="text-xs text-slate-500">{{ b.reference }}</p>
                        </td>
                        <td class="px-4 py-3">{{ b.leads_sold }}</td>
                        <td class="px-4 py-3 text-emerald-600">{{ formatMoney(b.revenue) }}</td>
                        <td class="px-4 py-3">{{ formatMoney(b.credit_balance) }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="route('billing.show', b.id)" class="text-sm text-indigo-600 hover:underline">Billing</Link>
                        </td>
                    </tr>
                </DataTable>
            </Panel>

            <Panel title="Suppliers — volume & payout" :padding="false">
                <DataTable :empty="!suppliers?.length">
                    <template #head>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Supplier</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Submitted</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Sold</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Payout</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">Portal</th>
                    </template>
                    <tr v-for="s in suppliers" :key="s.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-3">
                            <Link :href="route('suppliers.show', s.id)" class="font-medium text-indigo-600 hover:underline">{{ s.name }}</Link>
                        </td>
                        <td class="px-4 py-3">{{ s.leads_submitted }}</td>
                        <td class="px-4 py-3">{{ s.leads_sold }}</td>
                        <td class="px-4 py-3 text-amber-600">{{ formatMoney(s.payout) }}</td>
                        <td class="px-4 py-3 text-right text-xs text-slate-500">
                            <template v-if="s.portal_user">{{ s.portal_user.email }}</template>
                            <Link v-else :href="route('users.index')" class="text-indigo-600">Create user</Link>
                        </td>
                    </tr>
                </DataTable>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
