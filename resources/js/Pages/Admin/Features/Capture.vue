<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    links: Array,
});
</script>

<template>
    <Head title="Capture" />
    <AuthenticatedLayout>
        <PageHeader
            title="Capture"
            description="Ingest leads in real time via API, webhooks, CSV imports, and integrations."
        >
            <template #actions>
                <Link :href="route('features.index')" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300">
                    ← All features
                </Link>
            </template>
        </PageHeader>

        <div class="grid gap-4 md:grid-cols-2">
            <Link
                v-for="link in links"
                :key="link.route"
                :href="route(link.route)"
                class="group rounded-xl border border-slate-200 bg-white p-5 transition hover:border-indigo-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-700"
            >
                <h3 class="font-semibold text-slate-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400">
                    {{ link.label }} →
                </h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ link.desc }}</p>
            </Link>
        </div>
    </AuthenticatedLayout>
</template>
