<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import LogFilters from '@/Components/UI/LogFilters.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import ClickableTableRow from '@/Components/UI/ClickableTableRow.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    logs: Object,
    stats: Object,
    filters: Object,
    deliveries: Array,
    buyers: Array,
    statusOptions: Array,
});

const { formatMoney } = useMoneyFormat();

const logStatStrip = computed(() => [
    { label: 'Attempts', value: props.stats?.total ?? 0, href: route('logs.delivery'), accent: 'indigo' },
    { label: 'Success', value: props.stats?.success ?? 0, href: route('logs.delivery', { status: 'success' }), accent: 'emerald' },
    { label: 'Failed', value: props.stats?.failed ?? 0, href: route('logs.delivery', { status: 'failed' }), accent: 'rose' },
    { label: 'Skipped', value: props.stats?.skipped ?? 0, href: route('logs.delivery', { status: 'skipped' }), accent: 'amber' },
    { label: 'Outbid', value: props.stats?.outbid ?? 0, href: route('logs.delivery', { status: 'outbid' }), accent: 'cyan' },
    { label: 'Avg ms', value: props.stats?.avg_ms ?? 0, accent: 'indigo' },
]);
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

        <CompactStatStrip :items="logStatStrip" :columns="6" class="mb-6" />

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
                    <th class="text-left">Time</th>
                    <th class="text-left">Delivery</th>
                    <th class="text-left">Lead</th>
                    <th class="text-left">Method</th>
                    <th class="text-left">Status</th>
                    <th class="text-left">Ms</th>
                    <th class="text-left">Revenue</th>
                </template>
                <ClickableTableRow v-for="log in logs.data" :key="log.id" :href="route('logs.delivery.show', log.id)">
                    <td class=""><FormattedDate :value="log.created_at" /></td>
                    <td class="text-xs text-slate-900 dark:text-white">{{ log.delivery ?? '—' }}</td>
                    <td class="">
                        <span class="font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ log.lead_uuid?.slice(0, 10) }}…</span>
                        <p class="text-xs text-slate-500">{{ log.campaign }}</p>
                    </td>
                    <td class="">
                        <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', log.method === 'ping-post' ? 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800']">{{ log.method }}</span>
                    </td>
                    <td class=""><StatusBadge :status="log.status" /></td>
                    <td class="font-mono text-xs" :class="log.duration_ms > 1500 ? 'text-rose-600' : 'text-slate-600'">{{ log.duration_ms ?? '—' }}</td>
                    <td class="text-sm font-medium">{{ log.revenue ? formatMoney(log.revenue) : '—' }}</td>
                </ClickableTableRow>
            </DataTable>
            <Pagination :links="logs.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
