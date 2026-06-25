<script setup>
import MarketingNav from '@/Components/Marketing/MarketingNav.vue';
import MarketingFooter from '@/Components/Marketing/MarketingFooter.vue';
import SeoHead from '@/Components/SeoHead.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { useMarketingTheme } from '@/Composables/useMarketingTheme';

defineProps({
    canLogin: Boolean,
    status: Object,
    seo: { type: Object, default: () => ({}) },
});

const { marketingTheme } = useMarketingTheme();

const statusStyles = {
    operational: {
        badge: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400',
        dot: 'bg-emerald-400',
        title: 'All systems operational',
    },
    degraded: {
        badge: 'border-amber-500/30 bg-amber-500/10 text-amber-400',
        dot: 'bg-amber-400',
        title: 'Degraded performance',
    },
    outage: {
        badge: 'border-rose-500/30 bg-rose-500/10 text-rose-400',
        dot: 'bg-rose-500',
        title: 'Service disruption',
    },
};

const componentStatusClass = (s) => ({
    ok: 'text-emerald-400',
    warning: 'text-amber-400',
    critical: 'text-rose-400',
}[s] ?? 'text-slate-400');

const componentStatusLabel = (s) => ({
    ok: 'Operational',
    warning: 'Degraded',
    critical: 'Outage',
}[s] ?? s);
</script>

<template>
    <SeoHead
        :title="seo?.title || 'System Status — PowerByExcellence'"
        :description="seo?.description || 'Live platform health for lead distribution and API availability.'"
    />

    <div class="min-h-screen">
        <MarketingNav :can-login="canLogin" active="status" />

        <div :class="['marketing-content pt-24', marketingTheme === 'dark' && 'marketing-dark']">
            <section class="mx-auto max-w-4xl px-6 py-12">
                <div class="text-center">
                    <p class="brand-kicker">Platform health</p>
                    <h1 class="mt-3 text-4xl font-bold tracking-tight text-slate-900 marketing-dark:text-white">System status</h1>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-600 marketing-dark:text-slate-400">
                        Automated checks run every 15 minutes. A full daily audit is stored at 06:00 UTC for uptime reporting.
                    </p>
                </div>

                <div
                    class="mt-10 rounded-2xl border p-8 text-center shadow-lg"
                    :class="statusStyles[status?.status]?.badge ?? statusStyles.operational.badge"
                >
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-current/20 bg-black/20">
                        <span class="h-4 w-4 rounded-full" :class="statusStyles[status?.status]?.dot ?? statusStyles.operational.dot" />
                    </div>
                    <h2 class="text-2xl font-bold">{{ status?.label ?? 'All systems operational' }}</h2>
                    <p v-if="status?.checked_at" class="mt-2 text-sm opacity-80">
                        Last checked <FormattedDate :value="status.checked_at" />
                    </p>
                    <p v-if="status?.uptime_30d != null" class="mt-4 text-sm font-semibold">
                        {{ status.uptime_30d }}% uptime (30-day daily checks)
                    </p>
                </div>

                <div class="mt-10 grid gap-4 sm:grid-cols-3">
                    <div class="brand-card-sm text-center">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Avg processing</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900 marketing-dark:text-white">
                            {{ status?.metrics?.avg_processing_ms ?? '—' }}<span class="text-sm font-normal text-slate-500">ms</span>
                        </p>
                        <p class="mt-1 text-xs text-slate-500">Target &lt;{{ status?.metrics?.processing_target_ms ?? 200 }}ms</p>
                    </div>
                    <div class="brand-card-sm text-center">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Post success</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900 marketing-dark:text-white">
                            {{ status?.metrics?.post_success_rate != null ? status.metrics.post_success_rate + '%' : '—' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">Today · delivery posts</p>
                    </div>
                    <div class="brand-card-sm text-center">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Queue</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900 marketing-dark:text-white">{{ status?.metrics?.pending_queue ?? 0 }}</p>
                        <p class="mt-1 text-xs text-slate-500">Pending leads</p>
                    </div>
                </div>

                <div class="mt-12">
                    <h3 class="text-lg font-semibold text-slate-900 marketing-dark:text-white">Infrastructure components</h3>
                    <div class="mt-4 space-y-3">
                        <div
                            v-for="component in status?.components ?? []"
                            :key="component.key"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200/80 bg-white/70 px-5 py-4 marketing-dark:border-white/10 marketing-dark:bg-slate-900/40"
                        >
                            <div>
                                <p class="font-medium text-slate-900 marketing-dark:text-white">{{ component.name }}</p>
                                <p class="mt-1 text-sm text-slate-600 marketing-dark:text-slate-400">{{ component.message }}</p>
                            </div>
                            <span class="text-sm font-semibold" :class="componentStatusClass(component.status)">
                                {{ componentStatusLabel(component.status) }}
                            </span>
                        </div>
                    </div>
                </div>

                <p class="mt-10 text-center text-sm text-slate-500">
                    JSON feed:
                    <a :href="route('status.json')" class="text-indigo-600 hover:underline marketing-dark:text-indigo-400">/status.json</a>
                </p>
            </section>
        </div>

        <MarketingFooter :can-login="canLogin" />
    </div>
</template>
