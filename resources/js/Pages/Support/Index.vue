<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import ClickableTableRow from '@/Components/UI/ClickableTableRow.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ tickets: Object });

const priorityClass = (priority) => ({
    low: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
    normal: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    high: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
}[priority] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300');

const statusClass = (status) => ({
    open: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    resolved: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
    closed: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
}[status] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300');
</script>

<template>
    <Head title="Support" />
    <AuthenticatedLayout>
        <PageHeader title="Support Tickets" description="View and manage your support requests.">
            <template #actions>
                <AppButton :href="route('support.create')">New Ticket</AppButton>
            </template>
        </PageHeader>

        <Panel :padding="false">
            <template #header>
                <span class="text-sm text-slate-500">{{ tickets.total }} ticket{{ tickets.total === 1 ? '' : 's' }}</span>
            </template>
            <DataTable :empty="!tickets.data?.length" empty-message="No support tickets yet.">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Subject</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Priority</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Updated</th>
                </template>
                <ClickableTableRow
                    v-for="ticket in tickets.data"
                    :key="ticket.id"
                    :href="route('support.show', ticket.id)"
                >
                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ ticket.subject }}</td>
                    <td class="px-6 py-4">
                        <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium capitalize', statusClass(ticket.status)]">
                            {{ ticket.status }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium capitalize', priorityClass(ticket.priority)]">
                            {{ ticket.priority }}
                        </span>
                    </td>
                    <td class="px-6 py-4"><FormattedDate :value="ticket.updated_at" /></td>
                </ClickableTableRow>
            </DataTable>
            <div v-if="tickets.links?.length > 3" class="flex flex-wrap justify-center gap-1 border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                <Link
                    v-for="link in tickets.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-sm',
                        link.active ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
                        !link.url && 'pointer-events-none opacity-40',
                    ]"
                    v-html="link.label"
                />
            </div>
        </Panel>
    </AuthenticatedLayout>
</template>
