<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DeliveryMethodBadge from '@/Components/UI/DeliveryMethodBadge.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    links: Array,
    deliveries: Array,
});

const methodValue = (d) => d.method?.value ?? d.method;
</script>

<template>
    <Head title="Delivery" />
    <AuthenticatedLayout>
        <PageHeader
            title="Delivery"
            description="Configure buyer endpoints — API, ping-post, email, SMS, and store lead."
        >
            <template #actions>
                <Link :href="route('features.index')" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300">
                    ← All features
                </Link>
            </template>
        </PageHeader>

        <div class="mb-6 grid gap-4 md:grid-cols-2">
            <Link
                v-for="link in links"
                :key="link.route"
                :href="route(link.route)"
                class="group rounded-xl border border-slate-200 bg-white p-5 transition hover:border-amber-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-amber-700"
            >
                <h3 class="font-semibold text-slate-900 group-hover:text-amber-600 dark:text-white dark:group-hover:text-amber-400">
                    {{ link.label }} →
                </h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ link.desc }}</p>
            </Link>
        </div>

        <Panel v-if="deliveries?.length" title="Recent deliveries" :padding="false">
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <Link
                    v-for="d in deliveries"
                    :key="d.id"
                    :href="route('deliveries.show', d.id)"
                    class="flex items-center justify-between px-6 py-4 transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
                >
                    <div>
                        <p class="font-medium text-slate-900 dark:text-white">{{ d.name }}</p>
                        <p class="text-sm text-slate-500">{{ d.campaign?.name }} · {{ d.buyer?.name ?? 'No buyer' }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <DeliveryMethodBadge :method="methodValue(d)" />
                        <StatusBadge :status="d.status" />
                    </div>
                </Link>
            </div>
        </Panel>
    </AuthenticatedLayout>
</template>
