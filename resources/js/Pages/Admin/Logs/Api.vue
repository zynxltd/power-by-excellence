<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import LogFilters from '@/Components/UI/LogFilters.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
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

const apiStatStrip = computed(() => [
    { label: 'Requests', value: props.stats?.total ?? 0, accent: 'indigo' },
    { label: 'Errors', value: props.stats?.errors ?? 0, accent: 'rose' },
    { label: 'Avg ms', value: props.stats?.avg_ms ?? 0, accent: 'cyan' },
    { label: 'P95 ms', value: props.stats?.p95_ms ?? 0, accent: 'amber' },
    { label: 'Slowest', value: props.stats?.slowest_ms ?? 0, accent: 'rose' },
]);
</script>

<template>
    <Head title="API Logs" />
    <AuthenticatedLayout>
        <PageHeader
            title="API Request Logs"
            description="Response timings, fault codes, and error messages for every API call."
        />

        <CompactStatStrip :items="apiStatStrip" :columns="5" class="mb-6" />

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
                    <th class="text-left">When</th>
                    <th class="text-left">Method</th>
                    <th class="text-left">Path</th>
                    <th class="text-left">Status</th>
                    <th class="text-left">Time</th>
                    <th class="text-left">Error</th>
                </template>
                <template v-for="log in logs.data" :key="log.id">
                    <tr
                        class="cursor-pointer transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
                        @click="expandedId = expandedId === log.id ? null : log.id"
                    >
                        <td class=""><FormattedDate :value="log.created_at" /></td>
                        <td class="font-mono text-xs">{{ log.method }}</td>
                        <td class="font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ log.path }}</td>
                        <td class="">
                            <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold', statusClass(log.status_code)]">
                                {{ log.status_code }}
                            </span>
                        </td>
                        <td class="font-mono text-xs" :class="timingClass(log.duration_ms)">{{ log.duration_ms }}ms</td>
                        <td class="max-w-xs truncate text-sm text-rose-600 dark:text-rose-400">{{ log.error_message ?? '—' }}</td>
                    </tr>
                    <tr v-if="expandedId === log.id" class="bg-slate-50 dark:bg-slate-900/50">
                        <td colspan="6" class="">
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
