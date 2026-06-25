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
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    supplier: Object,
    recentLeads: Array,
    leadStats: Object,
    portalUser: Object,
});
</script>

<template>
    <Head :title="supplier.name" />
    <AuthenticatedLayout>
        <PageHeader
            :title="supplier.name"
            description="Affiliate / publisher profile — sources, attribution, and submitted leads."
        >
            <template #actions>
                <AppButton :href="route('api-keys.index')" variant="secondary">API Keys</AppButton>
                <AppButton :href="route('suppliers.edit', supplier.id)">Edit supplier</AppButton>
            </template>
        </PageHeader>

        <ManagementHubNav type="supplier" :entity="supplier" />

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <StatCard label="Reference" :value="supplier.reference" accent="indigo" />
            <StatCard label="Total leads" :value="leadStats.total" accent="cyan" />
            <StatCard label="Sold" :value="leadStats.sold" accent="emerald" />
            <StatCard label="Status" :value="supplier.status" accent="amber" />
        </div>

        <Panel v-if="portalUser" title="Supplier portal" class="mt-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Portal login: <strong>{{ portalUser.email }}</strong>
                    <span class="ml-2 text-slate-500">·</span>
                    <Link :href="route('portal.supplier.dashboard')" class="text-indigo-600 hover:underline">/portal/supplier</Link>
                </p>
                <AppButton :href="route('impersonate.start', portalUser.id)" method="post" variant="secondary">
                    Log in as supplier
                </AppButton>
            </div>
        </Panel>

        <Panel title="Traffic sources (SIDs)" class="mt-6">
            <div v-if="!supplier.sources?.length" class="text-sm text-slate-500">No sources configured. Add SIDs when editing this supplier.</div>
            <div v-else class="flex flex-wrap gap-2">
                <div
                    v-for="source in supplier.sources"
                    :key="source.id"
                    class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50"
                >
                    <p class="font-mono text-sm font-semibold text-indigo-600 dark:text-indigo-400">{{ source.sid }}</p>
                    <p class="text-xs text-slate-500">{{ source.name }}</p>
                    <p v-if="source.sub_suppliers?.length" class="mt-1 text-xs text-slate-400">
                        SSIDs: {{ source.sub_suppliers.map((s) => s.ssid).join(', ') }}
                    </p>
                </div>
            </div>
        </Panel>

        <Panel title="Recent submitted leads" class="mt-6" :padding="false">
            <DataTable :empty="!recentLeads?.length">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Lead</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Payout</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Received</th>
                </template>
                <ClickableTableRow v-for="lead in recentLeads" :key="lead.id" :href="route('leads.show', lead.id)">
                    <td class="px-6 py-4 font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ lead.uuid?.slice(0, 10) }}…</td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ lead.campaign?.name }}</td>
                    <td class="px-6 py-4"><StatusBadge :status="lead.status" /></td>
                    <td class="px-6 py-4 font-medium text-emerald-600 dark:text-emerald-400">£{{ lead.financials?.payout ?? 0 }}</td>
                    <td class="px-6 py-4"><FormattedDate :value="lead.received_at" format="relative" /></td>
                </ClickableTableRow>
            </DataTable>
        </Panel>
    </AuthenticatedLayout>
</template>
