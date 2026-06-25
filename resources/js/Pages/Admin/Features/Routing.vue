<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    links: Array,
    configs: Array,
});

const modeLabel = (mode) => mode?.replace(/_/g, ' ');
</script>

<template>
    <Head title="Routing" />
    <AuthenticatedLayout>
        <PageHeader
            title="Routing"
            description="Ping trees, simulators, and delivery orchestration."
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
                class="group rounded-xl border border-slate-200 bg-white p-5 transition hover:border-violet-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-violet-700"
            >
                <h3 class="font-semibold text-slate-900 group-hover:text-violet-600 dark:text-white dark:group-hover:text-violet-400">
                    {{ link.label }} →
                </h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ link.desc }}</p>
            </Link>
        </div>

        <Panel v-if="configs?.length" title="Recent ping tree configs" :padding="false">
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <Link
                    v-for="c in configs"
                    :key="c.id"
                    :href="route('distribution.show', c.id)"
                    class="flex items-center justify-between px-6 py-4 transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
                >
                    <div>
                        <p class="font-medium text-slate-900 dark:text-white">{{ c.name }}</p>
                        <p class="text-sm text-slate-500">{{ c.campaign?.name }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-slate-500">{{ c.config?.groups?.length ?? 0 }} tiers</span>
                        <StatusBadge :status="c.is_active ? 'active' : 'inactive'" />
                    </div>
                </Link>
            </div>
        </Panel>
    </AuthenticatedLayout>
</template>
