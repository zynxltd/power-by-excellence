<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ events: Object });
</script>

<template>
    <Head title="Change Logs" />
    <AuthenticatedLayout>
        <PageHeader
            title="Change Logs"
            description="Lead processing events — status changes, deliveries, and pipeline activity."
        />

        <Panel :padding="false">
            <DataTable :empty="!events?.data?.length">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">When</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Lead</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Event</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Message</th>
                </template>
                <tr v-for="event in events.data" :key="event.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4"><FormattedDate :value="event.created_at" /></td>
                    <td class="px-6 py-4">
                        <Link
                            v-if="event.lead"
                            :href="route('leads.show', event.lead.id)"
                            class="font-mono text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                        >
                            {{ event.lead.uuid?.slice(0, 12) }}…
                        </Link>
                        <span v-else class="text-slate-400">—</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ event.lead?.campaign?.name ?? '—' }}</td>
                    <td class="px-6 py-4">
                        <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                            {{ event.event_type }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ event.message ?? '—' }}</td>
                </tr>
            </DataTable>
            <Pagination :links="events.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
