<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    calls: Object,
    campaigns: Array,
    filters: Object,
    statuses: Array,
});

const localFilters = ref({ ...props.filters });

const applyFilters = () => {
    router.get(route('call-logic.calls.index'), localFilters.value, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Call Logic - Calls" />
    <AuthenticatedLayout>
        <PageHeader title="Calls" description="Inbound call sessions and routing outcomes." />
        <Panel class="mb-4">
            <div class="flex flex-wrap gap-3">
                <select v-model="localFilters.status" class="rounded border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800" @change="applyFilters">
                    <option value="">All statuses</option>
                    <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                </select>
                <select v-model="localFilters.campaign_id" class="rounded border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800" @change="applyFilters">
                    <option value="">All campaigns</option>
                    <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
        </Panel>
        <Panel>
            <DataTable>
                <template #head>
                    <tr>
                        <th>Caller</th>
                        <th>Campaign</th>
                        <th>Buyer</th>
                        <th>Status</th>
                        <th>Duration</th>
                        <th>Revenue</th>
                        <th>Received</th>
                    </tr>
                </template>
                <template #body>
                    <tr v-for="call in calls.data" :key="call.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td>
                            <Link :href="route('call-logic.calls.show', call.uuid)" class="font-medium text-indigo-600 hover:underline">{{ call.caller_number || '—' }}</Link>
                        </td>
                        <td>{{ call.campaign?.name || '—' }}</td>
                        <td>{{ call.sold_to_buyer?.name || '—' }}</td>
                        <td><StatusBadge :status="call.status" /></td>
                        <td>{{ call.duration_seconds }}s</td>
                        <td>{{ call.revenue ?? '—' }}</td>
                        <td><FormattedDate :value="call.created_at" /></td>
                    </tr>
                </template>
            </DataTable>
            <Pagination :links="calls.links" class="mt-4" />
        </Panel>
    </AuthenticatedLayout>
</template>
