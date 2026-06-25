<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import LogFilters from '@/Components/UI/LogFilters.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    logs: Object,
    stats: Object,
    filters: Object,
    statusOptions: Array,
});

const expandedId = ref(null);

const statusClass = (code) => {
    if (code >= 500) return 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300';
    if (code >= 400) return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
    return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
};

const timingClass = (ms) => {
    if (ms > 1500) return 'text-rose-600 dark:text-rose-400 font-semibold';
    if (ms > 600) return 'text-amber-600 dark:text-amber-400';
    return 'text-slate-600 dark:text-slate-400';
};
</script>

<template>
    <Head title="API Logs" />
    <AuthenticatedLayout>
        <PageHeader
            title="API Request Logs"
            description="Response timings, fault codes, and error messages for every API call."
        />

        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
            <StatCard label="Requests" :value="stats.total" accent="indigo" />
            <StatCard label="Errors" :value="stats.errors" accent="rose" />
            <StatCard label="Avg (ms)" :value="stats.avg_ms" accent="cyan" />
            <StatCard label="P95 (ms)" :value="stats.p95_ms" accent="amber" />
            <StatCard label="Slowest (ms)" :value="stats.slowest_ms" accent="rose" />
        </div>

        <LogFilters
            class="mb-6"
            route-name="logs.api"
            :filters="filters"
            show-days
            show-date-range
            show-status
            show-path
            :status-options="statusOptions"
        />

        <Panel title="Request log" :padding="false">
            <DataTable :empty="!logs?.data?.length" empty-message="No API requests logged yet.">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">When</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Method</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Path</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Time</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Error</th>
                </template>
                <template v-for="log in logs.data" :key="log.id">
                    <tr
                        class="cursor-pointer transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
                        @click="expandedId = expandedId === log.id ? null : log.id"
                    >
                        <td class="px-6 py-4"><FormattedDate :value="log.created_at" /></td>
                        <td class="px-6 py-4 font-mono text-xs">{{ log.method }}</td>
                        <td class="px-6 py-4 font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ log.path }}</td>
                        <td class="px-6 py-4">
                            <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold', statusClass(log.status_code)]">
                                {{ log.status_code }}
                            </span>
                        </td>
                        <td class="px-6 py-4 font-mono text-sm" :class="timingClass(log.duration_ms)">{{ log.duration_ms }}ms</td>
                        <td class="max-w-xs truncate px-6 py-4 text-sm text-rose-600 dark:text-rose-400">{{ log.error_message ?? '—' }}</td>
                    </tr>
                    <tr v-if="expandedId === log.id" class="bg-slate-50 dark:bg-slate-900/50">
                        <td colspan="6" class="px-6 py-4">
                            <div class="grid gap-4 text-sm md:grid-cols-2">
                                <div>
                                    <p class="text-xs font-semibold uppercase text-slate-500">API Key</p>
                                    <p class="text-slate-700 dark:text-slate-300">{{ log.api_key?.name ?? 'Unauthenticated' }} ({{ log.api_key?.key_prefix ?? '—' }})</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase text-slate-500">IP</p>
                                    <p class="font-mono text-slate-700 dark:text-slate-300">{{ log.ip_address }}</p>
                                </div>
                                <div v-if="log.response_summary" class="md:col-span-2">
                                    <p class="mb-1 text-xs font-semibold uppercase text-slate-500">Response summary</p>
                                    <pre class="overflow-auto rounded-lg bg-slate-900 p-3 text-xs text-emerald-300">{{ JSON.stringify(log.response_summary, null, 2) }}</pre>
                                </div>
                            </div>
                        </td>
                    </tr>
                </template>
            </DataTable>
            <Pagination :links="logs.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
