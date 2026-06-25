<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useLiveStats } from '@/Composables/useLiveStats';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import Spinner from '@/Components/UI/Spinner.vue';

const { stats, loading, intervalSeconds } = useLiveStats();
const { formatMoney } = useMoneyFormat();

const items = computed(() => {
    if (!stats.value) {
        return [];
    }

    return [
        { label: 'Leads', value: stats.value.leads_today, href: route('leads.index'), color: 'text-indigo-600 dark:text-indigo-400' },
        { label: 'Sold', value: stats.value.sold_today, href: route('leads.index', { status: 'sold' }), color: 'text-emerald-600 dark:text-emerald-400' },
        { label: 'Queue', value: stats.value.pending, href: route('operations.index'), color: 'text-amber-600 dark:text-amber-400' },
        { label: 'Quarantine', value: stats.value.quarantined, href: route('quarantine.index'), color: 'text-rose-600 dark:text-rose-400' },
        { label: 'Revenue', value: formatMoney(stats.value.revenue_today, { decimals: 0 }), href: route('finance.index'), color: 'text-cyan-600 dark:text-cyan-400' },
    ];
});

const processingCount = computed(() => stats.value?.processing_count ?? 0);

const updatedLabel = computed(() => {
    if (!stats.value?.updated_at) {
        return '';
    }

    return `Updated ${new Date(stats.value.updated_at).toLocaleTimeString()}`;
});
</script>

<template>
    <div
        v-if="stats || loading"
        class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200/80 bg-white/80 px-4 py-2.5 text-sm shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/80"
    >
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
            <span class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60" />
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
                </span>
                Live
            </span>
            <template v-for="item in items" :key="item.label">
                <Link :href="item.href" class="group flex items-baseline gap-1.5 transition hover:opacity-80">
                    <span class="text-slate-500 dark:text-slate-400">{{ item.label }}</span>
                    <span :class="['font-bold tabular-nums', item.color]">{{ item.value }}</span>
                </Link>
            </template>
            <Link
                v-if="processingCount > 0"
                :href="route('leads.index', { status: 'processing' })"
                class="inline-flex items-center gap-1.5 rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-semibold text-violet-700 transition hover:bg-violet-200 dark:bg-violet-900/40 dark:text-violet-200 dark:hover:bg-violet-900/60"
            >
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-violet-400 opacity-75" />
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-violet-500" />
                </span>
                {{ processingCount }} processing
            </Link>
            <Spinner v-if="loading && !stats" size="sm" class="text-indigo-500" />
        </div>
        <p class="text-xs text-slate-400 dark:text-slate-500">
            {{ updatedLabel }}
            <span v-if="intervalSeconds"> · refreshes every {{ intervalSeconds }}s</span>
        </p>
    </div>
</template>
