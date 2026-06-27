<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    stats: Object,
});

const features = [
    {
        key: 'capture',
        title: 'Capture',
        description: 'Ingest leads via API, webhooks, CSV imports, and integrations.',
        route: 'features.capture',
        icon: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12',
        accent: 'indigo',
        statKeys: ['campaigns', 'api_keys', 'webhooks', 'imports'],
    },
    {
        key: 'validation',
        title: 'Validation',
        description: 'Filter junk, dedupe, and quarantine leads before distribution.',
        route: 'features.validation',
        icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        accent: 'emerald',
        statKeys: ['campaigns'],
    },
    {
        key: 'auto-responders',
        title: 'Auto Responders',
        description: 'Send automated email or SMS on lead events.',
        route: 'features.auto-responders',
        icon: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
        accent: 'cyan',
        statKeys: ['auto_responders'],
    },
    {
        key: 'routing',
        title: 'Routing',
        description: 'Ping trees, waterfall, auction, round-robin, and hybrid groups.',
        route: 'features.routing',
        icon: 'M13 10V3L4 14h7v7l9-11h-7z',
        accent: 'violet',
        statKeys: ['ping_trees', 'deliveries'],
    },
    {
        key: 'delivery',
        title: 'Delivery',
        description: 'Direct post, ping-post, email, SMS, and store lead methods.',
        route: 'features.delivery',
        icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',
        accent: 'amber',
        statKeys: ['deliveries', 'deliveries_active', 'logs_today', 'success_today'],
    },
    {
        key: 'click-track',
        title: 'Click Track',
        description: 'Affiliate tracking links, clicks, conversions, and Lynx-style reports.',
        route: 'click-track.dashboard',
        icon: 'M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122',
        accent: 'sky',
        statKeys: ['tracking_links', 'clicks_today', 'conversions_pending'],
    },
    {
        key: 'reports',
        title: 'Reports',
        description: 'Revenue, conversion, buyer/supplier performance, and delivery analytics.',
        route: 'reports.index',
        icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        accent: 'rose',
        statKeys: ['leads_period', 'sold_period', 'revenue_period'],
    },
];

const accentClasses = {
    indigo: 'border-indigo-200 hover:border-indigo-400 dark:border-indigo-800 dark:hover:border-indigo-600',
    emerald: 'border-emerald-200 hover:border-emerald-400 dark:border-emerald-800 dark:hover:border-emerald-600',
    cyan: 'border-cyan-200 hover:border-cyan-400 dark:border-cyan-800 dark:hover:border-cyan-600',
    violet: 'border-violet-200 hover:border-violet-400 dark:border-violet-800 dark:hover:border-violet-600',
    amber: 'border-amber-200 hover:border-amber-400 dark:border-amber-800 dark:hover:border-amber-600',
    rose: 'border-rose-200 hover:border-rose-400 dark:border-rose-800 dark:hover:border-rose-600',
    sky: 'border-sky-200 hover:border-sky-400 dark:border-sky-800 dark:hover:border-sky-600',
};

const iconClasses = {
    indigo: 'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-400',
    emerald: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400',
    cyan: 'bg-cyan-100 text-cyan-600 dark:bg-cyan-900/40 dark:text-cyan-400',
    violet: 'bg-violet-100 text-violet-600 dark:bg-violet-900/40 dark:text-violet-400',
    amber: 'bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400',
    rose: 'bg-rose-100 text-rose-600 dark:bg-rose-900/40 dark:text-rose-400',
    sky: 'bg-sky-100 text-sky-600 dark:bg-sky-900/40 dark:text-sky-400',
};

const statLabel = (key) => ({
    campaigns: 'Campaigns',
    api_keys: 'API keys',
    webhooks: 'Webhooks',
    imports: 'Imports',
    auto_responders: 'Active responders',
    tracking_links: 'Tracking links',
    clicks_today: 'Clicks today',
    conversions_pending: 'Pending conversions',
    ping_trees: 'Ping trees',
    deliveries: 'Deliveries',
    deliveries_active: 'Active deliveries',
    logs_today: 'Logs today',
    success_today: 'Successes today',
}[key] ?? key);
</script>

<template>
    <Head title="Features" />
    <AuthenticatedLayout>
        <PageHeader
            title="Platform Features"
            description="Feature hubs - capture, validate, route, deliver, and report."
        />

        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="feature in features"
                :key="feature.key"
                :href="route(feature.route)"
                :class="[
                    'group flex flex-col rounded-2xl border-2 bg-white p-6 transition hover:shadow-lg dark:bg-slate-900',
                    accentClasses[feature.accent],
                ]"
            >
                <div class="flex items-start gap-4">
                    <div :class="['flex h-12 w-12 shrink-0 items-center justify-center rounded-xl', iconClasses[feature.accent]]">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="feature.icon" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-bold text-slate-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400">
                            {{ feature.title }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ feature.description }}</p>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <div
                        v-for="key in feature.statKeys"
                        :key="key"
                        class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800/50"
                    >
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ statLabel(key) }}</p>
                        <p class="text-lg font-bold text-slate-900 dark:text-white">{{ stats?.[key] ?? '-' }}</p>
                    </div>
                </div>

                <p class="mt-4 text-sm font-semibold text-indigo-600 dark:text-indigo-400">
                    Open hub →
                </p>
            </Link>
        </div>
    </AuthenticatedLayout>
</template>
