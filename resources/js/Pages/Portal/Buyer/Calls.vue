<script setup>
import BuyerPortalLayout from '@/Layouts/BuyerPortalLayout.vue';
import Panel from '@/Components/UI/Panel.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    calls: Object,
    filters: Object,
    buyer: Object,
});

const localFilters = ref({ ...props.filters });

const applyFilters = () => {
    router.get(route('portal.buyer.calls'), localFilters.value, { preserveState: true });
};
</script>

<template>
    <Head title="Calls" />
    <BuyerPortalLayout>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-slate-900 dark:text-white">Your calls</h1>
            <AppButton variant="secondary" :href="route('portal.buyer.calls.export', localFilters)">Export CSV</AppButton>
        </div>

        <Panel class="mb-4">
            <select v-model="localFilters.status" class="rounded border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800" @change="applyFilters">
                <option value="">All statuses</option>
                <option value="completed">Completed</option>
                <option value="connected">Connected</option>
                <option value="unsold">Unsold</option>
            </select>
        </Panel>

        <Panel>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500">
                        <th class="pb-2">Caller</th>
                        <th class="pb-2">Campaign</th>
                        <th class="pb-2">Status</th>
                        <th class="pb-2">Duration</th>
                        <th class="pb-2">Received</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="call in calls.data" :key="call.id" class="border-t border-slate-100 dark:border-slate-800">
                        <td class="py-2">
                            <Link :href="route('portal.buyer.calls.show', call.uuid)" class="text-indigo-600 hover:underline">{{ call.caller_number || '—' }}</Link>
                        </td>
                        <td class="py-2">{{ call.campaign?.name || '—' }}</td>
                        <td class="py-2"><StatusBadge :status="call.status" /></td>
                        <td class="py-2">{{ call.duration_seconds }}s</td>
                        <td class="py-2"><FormattedDate :value="call.created_at" /></td>
                    </tr>
                </tbody>
            </table>
            <Pagination :links="calls.links" class="mt-4" />
        </Panel>
    </BuyerPortalLayout>
</template>
