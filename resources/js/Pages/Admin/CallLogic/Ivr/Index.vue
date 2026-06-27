<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ flows: Object, campaigns: Array });
</script>

<template>
    <Head title="Call Logic - IVR" />
    <AuthenticatedLayout>
        <PageHeader title="IVR flows" description="Caller journeys before routing.">
            <template #actions>
                <AppButton :href="route('call-logic.ivr.create')">New IVR flow</AppButton>
            </template>
        </PageHeader>
        <Panel>
            <ul class="divide-y divide-slate-200 dark:divide-slate-700">
                <li v-for="flow in flows.data" :key="flow.id" class="flex items-center justify-between py-3">
                    <div>
                        <Link :href="route('call-logic.ivr.edit', flow.id)" class="font-medium text-indigo-600 hover:underline">{{ flow.name }}</Link>
                        <p class="text-sm text-slate-500">{{ flow.campaign?.name || 'No campaign' }} · {{ flow.is_active ? 'Active' : 'Inactive' }}</p>
                    </div>
                    <AppButton variant="secondary" size="sm" :href="route('call-logic.ivr.edit', flow.id)">Edit</AppButton>
                </li>
            </ul>
            <Pagination :links="flows.links" class="mt-4" />
        </Panel>
    </AuthenticatedLayout>
</template>
