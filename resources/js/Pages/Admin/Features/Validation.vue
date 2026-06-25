<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    links: Array,
    campaigns: Array,
});
</script>

<template>
    <Head title="Validation" />
    <AuthenticatedLayout>
        <PageHeader
            title="Validation"
            description="Per-campaign validation rules, deduplication, and fraud quarantine."
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
                class="group rounded-xl border border-slate-200 bg-white p-5 transition hover:border-emerald-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-emerald-700"
            >
                <h3 class="font-semibold text-slate-900 group-hover:text-emerald-600 dark:text-white dark:group-hover:text-emerald-400">
                    {{ link.label }} →
                </h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ link.desc }}</p>
            </Link>
        </div>

        <Panel v-if="campaigns?.length" title="Campaign validation configs" :padding="false">
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <Link
                    v-for="c in campaigns"
                    :key="c.id"
                    :href="route('campaigns.show', c.id)"
                    class="flex items-center justify-between px-6 py-4 transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
                >
                    <div>
                        <p class="font-medium text-slate-900 dark:text-white">{{ c.name }}</p>
                        <p class="font-mono text-xs text-slate-500">{{ c.reference }}</p>
                    </div>
                    <span class="text-sm text-indigo-600 dark:text-indigo-400">Configure →</span>
                </Link>
            </div>
        </Panel>
    </AuthenticatedLayout>
</template>
