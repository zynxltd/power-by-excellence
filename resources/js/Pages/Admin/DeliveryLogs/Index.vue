<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import LogFilters from '@/Components/UI/LogFilters.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import ClickableTableRow from '@/Components/UI/ClickableTableRow.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head } from '@inertiajs/vue3';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

defineProps({
    logs: Object,
    stats: Object,
    filters: Object,
    deliveries: Array,
    buyers: Array,
    statusOptions: Array,
});

const { formatMoney } = useMoneyFormat();
</script>

<template>
    <Head title="Delivery Logs" />
    <AuthenticatedLayout>
        <PageHeader
            title="Delivery Logs"
            description="Ping-post and direct delivery audit trail — filter, drill down, and inspect request/response payloads."
        >
            <template #actions>
                <AppButton :href="route('operations.index')" variant="secondary">Live Operations</AppButton>
            </template>
        </PageHeader>

        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <StatCard label="Attempts" :value="stats.total" accent="indigo" :href="route('logs.delivery')" />
            <StatCard label="Success" :value="stats.success" accent="emerald" :href="route('logs.delivery', { status: 'success' })" />
            <StatCard label="Failed" :value="stats.failed" accent="rose" :href="route('logs.delivery', { status: 'failed' })" />
            <StatCard label="Skipped" :value="stats.skipped" accent="amber" :href="route('logs.delivery', { status: 'skipped' })" />
            <StatCard label="Outbid" :value="stats.outbid" accent="cyan" :href="route('logs.delivery', { status: 'outbid' })" />
            <StatCard label="Avg (ms)" :value="stats.avg_ms" accent="indigo" />
        </div>

        <LogFilters
            class="mb-6"
            route-name="logs.delivery"
            :filters="filters"
            show-days
            show-date-range
            show-status
            show-method
            show-delivery
            show-buyer
            show-tier
            show-search
            :status-options="statusOptions"
            :deliveries="deliveries"
            :buyers="buyers"
        />

        <Panel title="Delivery attempts" :padding="false">
            <DataTable :empty="!logs?.data?.length" empty-message="No delivery logs match your filters.">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Time</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Delivery</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Lead</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Method</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Ms</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Revenue</th>
                </template>
                <ClickableTableRow v-for="log in logs.data" :key="log.id" :href="route('logs.delivery.show', log.id)">
                    <td class="px-6 py-4"><FormattedDate :value="log.created_at" /></td>
                    <td class="px-6 py-4 text-sm text-slate-900 dark:text-white">{{ log.delivery ?? '—' }}</td>
                    <td class="px-6 py-4">
                        <span class="font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ log.lead_uuid?.slice(0, 10) }}…</span>
                        <p class="text-xs text-slate-500">{{ log.campaign }}</p>
                    </td>
                    <td class="px-6 py-4">
                        <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', log.method === 'ping-post' ? 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800']">{{ log.method }}</span>
                    </td>
                    <td class="px-6 py-4"><StatusBadge :status="log.status" /></td>
                    <td class="px-6 py-4 font-mono text-sm" :class="log.duration_ms > 1500 ? 'text-rose-600' : 'text-slate-600'">{{ log.duration_ms ?? '—' }}</td>
                    <td class="px-6 py-4 text-sm font-medium">{{ log.revenue ? formatMoney(log.revenue) : '—' }}</td>
                </ClickableTableRow>
            </DataTable>
            <Pagination :links="logs.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
