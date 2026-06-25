<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    liveFeed: Object,
});
</script>

<template>
    <Head title="Live Feed" />
    <AuthenticatedLayout>
        <PageHeader
            title="Live feed"
            description="Real-time activity across all partner platforms — leads, deliveries, and platform events."
        />

        <Panel title="All tenants" :padding="false">
            <div v-if="!liveFeed?.data?.length" class="p-6 text-sm text-slate-500">No activity yet.</div>
            <div
                v-for="(item, idx) in liveFeed?.data ?? []"
                :key="`${item.type}-${item.created_at}-${idx}`"
                class="border-b border-slate-100 px-4 py-3 last:border-0 dark:border-slate-800"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-sm text-slate-900 dark:text-white">
                            <span class="mr-2 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-400">{{ item.type }}</span>
                            {{ item.message }}
                        </p>
                        <p v-if="item.tenant" class="mt-1 text-xs text-slate-500">{{ item.tenant }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <FormattedDate :value="item.created_at" class="text-xs" />
                        <Link v-if="item.href" :href="item.href" class="mt-1 block text-xs text-indigo-600 hover:underline">View</Link>
                    </div>
                </div>
            </div>
            <Pagination :links="liveFeed?.links ?? []" />
        </Panel>
    </AuthenticatedLayout>
</template>
