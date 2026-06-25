<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    title: { type: String, default: '' },
    items: { type: Object, default: () => ({}) },
    colors: { type: Object, default: () => ({}) },
    drilldownRoute: { type: String, default: '' },
});

const defaultColors = {
    sold: '#10b981',
    unsold: '#f59e0b',
    rejected: '#f43f5e',
    quarantined: '#a855f7',
    pending: '#64748b',
    processing: '#3b82f6',
    duplicate: '#94a3b8',
};

const segments = computed(() => {
    const total = Object.values(props.items).reduce((s, v) => s + Number(v), 0) || 1;
    let offset = 0;
    return Object.entries(props.items).map(([key, value]) => {
        const pct = (Number(value) / total) * 100;
        const segment = { key, value, pct, offset, color: props.colors[key] ?? defaultColors[key] ?? '#6366f1' };
        offset += pct;
        return segment;
    });
});

const gradient = computed(() => {
    if (!segments.value.length) return '#e2e8f0';
    let stops = [];
    segments.value.forEach((s) => {
        stops.push(`${s.color} ${s.offset}% ${s.offset + s.pct}%`);
    });
    return `conic-gradient(${stops.join(', ')})`;
});

const total = computed(() => Object.values(props.items).reduce((s, v) => s + Number(v), 0));

const drillHref = (status) => {
    if (!props.drilldownRoute) return null;
    return `${props.drilldownRoute}?status=${status}`;
};
</script>

<template>
    <div>
        <p v-if="title" class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">{{ title }}</p>
        <div class="flex items-center gap-6">
            <Link
                v-if="drilldownRoute"
                :href="drilldownRoute"
                class="relative h-28 w-28 shrink-0 transition hover:opacity-90"
                title="View all leads"
            >
                <div class="h-full w-full rounded-full" :style="{ background: gradient }" />
                <div class="absolute inset-3 flex flex-col items-center justify-center rounded-full bg-white dark:bg-slate-900">
                    <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ total }}</span>
                    <span class="text-[10px] text-slate-500">30 days</span>
                </div>
            </Link>
            <div v-else class="relative h-28 w-28 shrink-0">
                <div class="h-full w-full rounded-full" :style="{ background: gradient }" />
                <div class="absolute inset-3 flex flex-col items-center justify-center rounded-full bg-white dark:bg-slate-900">
                    <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ total }}</span>
                    <span class="text-[10px] text-slate-500">30 days</span>
                </div>
            </div>
            <div class="flex-1 space-y-2">
                <component
                    :is="drillHref(s.key) ? Link : 'div'"
                    v-for="s in segments"
                    :key="s.key"
                    :href="drillHref(s.key) ?? undefined"
                    :class="[
                        'flex items-center justify-between rounded-lg px-2 py-1 text-sm transition',
                        drillHref(s.key) ? 'cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800' : '',
                    ]"
                >
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: s.color }" />
                        <span class="capitalize text-slate-600 dark:text-slate-400">{{ s.key.replace(/_/g, ' ') }}</span>
                    </div>
                    <span class="font-semibold text-slate-900 dark:text-white">{{ s.value }}</span>
                </component>
            </div>
        </div>
    </div>
</template>
