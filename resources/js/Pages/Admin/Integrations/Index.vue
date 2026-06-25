<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    integrations: Array,
    stats: Object,
});

const statusStyles = {
    connected: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    available: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    coming_soon: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
};

const statusLabel = {
    connected: 'Connected',
    available: 'Available',
    coming_soon: 'Coming soon',
};

const grouped = (items) => {
    const groups = {};
    for (const item of items ?? []) {
        groups[item.category] ??= [];
        groups[item.category].push(item);
    }
    return groups;
};
</script>

<template>
    <Head title="Integrations" />
    <AuthenticatedLayout>
        <PageHeader
            title="Third-Party Integrations"
            description="Connect webhooks, APIs, payment providers, and lead sources to your platform."
        />

        <div class="mb-6 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Connected</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.connected }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Available</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ stats.available }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Coming soon</p>
                <p class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stats.coming_soon }}</p>
            </div>
        </div>

        <div class="space-y-8">
            <section v-for="(items, category) in grouped(integrations)" :key="category">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">{{ category }}</h2>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="item in items"
                        :key="item.id"
                        class="flex flex-col rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="font-semibold text-slate-900 dark:text-white">{{ item.name }}</h3>
                            <span :class="['shrink-0 rounded-full px-2 py-0.5 text-xs font-medium', statusStyles[item.status]]">
                                {{ statusLabel[item.status] }}
                            </span>
                        </div>
                        <p class="mt-2 flex-1 text-sm text-slate-600 dark:text-slate-400">{{ item.description }}</p>
                        <div class="mt-4">
                            <Link
                                v-if="item.route"
                                :href="item.route_params ? route(item.route, item.route_params) : route(item.route)"
                                class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                            >
                                {{ item.status === 'connected' ? 'Manage →' : 'Set up →' }}
                            </Link>
                            <span v-else class="text-xs text-slate-400">Available in a future release</span>
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <Panel class="mt-8" title="Affiliate / supplier ingest">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                Suppliers are <strong>affiliates/publishers</strong> who send leads via API.
                Create supplier API keys and link them to campaigns — see
                <Link :href="route('suppliers.index')" class="font-medium text-indigo-600 dark:text-indigo-400">Suppliers (Affiliates)</Link>
                and <Link :href="route('api-keys.index')" class="font-medium text-indigo-600 dark:text-indigo-400">API Keys</Link>.
            </p>
        </Panel>
    </AuthenticatedLayout>
</template>
