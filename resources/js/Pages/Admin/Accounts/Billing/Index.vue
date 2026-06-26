<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    currentAccountId: { type: Number, default: null },
});

const { formatMoney } = useMoneyFormat();

const overdueCount = computed(() => props.accounts.filter((a) => a.status === 'past_due' || a.status === 'locked').length);
</script>

<template>
    <Head title="Tenant Billing" />
    <AuthenticatedLayout>
        <PageHeader
            title="Tenant billing"
            description="Manage platform rent, contract references, and due dates for each partner platform. Tenants sign contracts with you — lock access when rent is overdue."
        >
            <template #actions>
                <AppButton :href="route('accounts.index')" variant="secondary">Partner platforms</AppButton>
            </template>
        </PageHeader>

        <div
            v-if="overdueCount"
            class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
        >
            {{ overdueCount }} platform{{ overdueCount === 1 ? '' : 's' }} need attention — past due or locked.
        </div>

        <Panel :padding="false">
            <DataTable :empty="!accounts?.length">
                <template #head>
                    <th class="text-left">Platform</th>
                    <th class="text-left">Status</th>
                    <th class="text-left">Plan</th>
                    <th class="text-left">Fraud</th>
                    <th class="text-left">Monthly rent</th>
                    <th class="text-left">Due date</th>
                    <th class="text-left">Contract</th>
                    <th class="text-right">Actions</th>
                </template>
                <tr
                    v-for="a in accounts"
                    :key="a.id"
                    class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                >
                    <td>
                        <p class="font-medium text-slate-900 dark:text-white">{{ a.name }}</p>
                        <p class="text-xs text-slate-500">{{ a.slug }} · {{ a.domain }}</p>
                    </td>
                    <td>
                        <StatusBadge :status="a.status" />
                        <p v-if="!a.can_accept_leads" class="mt-1 text-xs text-rose-600 dark:text-rose-400">API &amp; ingest suspended</p>
                    </td>
                    <td class="text-slate-600 dark:text-slate-400 capitalize">{{ a.subscription_plan || 'starter' }}</td>
                    <td class="text-slate-600 dark:text-slate-400">
                        <span v-if="a.fraud_protection?.entitled" class="text-emerald-600 dark:text-emerald-400">Active</span>
                        <span v-else class="text-slate-400">Off</span>
                    </td>
                    <td class="text-slate-700 dark:text-slate-300">
                        <span v-if="a.monthly_rent != null && a.monthly_rent !== ''">
                            {{ formatMoney(a.monthly_rent, { currency: a.currency }) }}
                        </span>
                        <span v-else class="text-slate-400">—</span>
                    </td>
                    <td class="text-slate-600 dark:text-slate-400">
                        <FormattedDate v-if="a.due_at" :value="a.due_at" format="date" />
                        <span v-else class="text-slate-400">—</span>
                    </td>
                    <td class="font-mono text-xs text-slate-500">
                        {{ a.contract_reference || '—' }}
                    </td>
                    <td class="text-right">
                        <AppButton :href="route('accounts.billing.edit', a.id)" variant="secondary">Manage</AppButton>
                    </td>
                </tr>
            </DataTable>
        </Panel>
    </AuthenticatedLayout>
</template>
