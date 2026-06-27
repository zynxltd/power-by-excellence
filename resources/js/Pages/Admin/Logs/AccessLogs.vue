<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import LogFilters from '@/Components/UI/LogFilters.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    logs: Object,
    filters: Object,
    actionOptions: Array,
});
</script>

<template>
    <Head title="Access Logs" />
    <AuthenticatedLayout>
        <PageHeader
            title="Access Logs"
            description="Sign-in and sign-out activity for users on this platform."
        />

        <LogFilters
            class="mb-6"
            route-name="logs.access"
            :filters="filters"
            show-days
            show-date-range
            show-action
            show-search
            :action-options="actionOptions"
        />

        <Panel :padding="false">
            <DataTable :empty="!logs?.data?.length">
                <template #head>
                    <th class="text-left">When</th>
                    <th class="text-left">User</th>
                    <th class="text-left">Action</th>
                    <th class="text-left">IP</th>
                    <th class="text-left">Path</th>
                </template>
                <tr v-for="log in logs.data" :key="log.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class=""><FormattedDate :value="log.created_at" /></td>
                    <td class="">
                        <p class="font-medium text-slate-900 dark:text-white">{{ log.user?.name ?? 'Unknown' }}</p>
                        <p class="text-xs text-slate-500">{{ log.user?.email }}</p>
                    </td>
                    <td class="">
                        <span
                            :class="[
                                'rounded-full px-2.5 py-0.5 text-xs font-medium capitalize',
                                log.action === 'login'
                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                    : 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
                            ]"
                        >
                            {{ log.action }}
                        </span>
                    </td>
                    <td class="font-mono text-xs text-slate-500">{{ log.ip_address ?? '-' }}</td>
                    <td class="text-xs text-slate-500">{{ log.path ?? '-' }}</td>
                </tr>
            </DataTable>
            <Pagination :links="logs.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
