<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    change: { type: String, default: '' },
    icon: { type: String, default: 'chart' },
    accent: { type: String, default: 'indigo' },
    href: { type: String, default: null },
    title: { type: String, default: null },
});

const accents = {
    indigo: 'from-indigo-500/10 to-violet-500/10 text-indigo-600 dark:text-indigo-400',
    emerald: 'from-emerald-500/10 to-green-500/10 text-emerald-600 dark:text-emerald-400',
    amber: 'from-amber-500/10 to-orange-500/10 text-amber-600 dark:text-amber-400',
    rose: 'from-rose-500/10 to-pink-500/10 text-rose-600 dark:text-rose-400',
    cyan: 'from-cyan-500/10 to-blue-500/10 text-cyan-600 dark:text-cyan-400',
    violet: 'from-violet-500/10 to-purple-500/10 text-violet-600 dark:text-violet-400',
    slate: 'from-slate-500/10 to-slate-600/10 text-slate-600 dark:text-slate-400',
};
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href ?? undefined"
        :title="title ?? undefined"
        :class="[
            'relative block overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition dark:border-slate-800 dark:bg-slate-900',
            href ? 'cursor-pointer hover:border-indigo-300 hover:shadow-md dark:hover:border-indigo-600' : 'hover:shadow-md',
        ]"
    >
        <div :class="['absolute -right-4 -top-4 h-24 w-24 rounded-full bg-gradient-to-br blur-2xl', accents[accent]]" />
        <div class="relative">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ label }}</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ value }}</p>
            <p v-if="change" class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400">{{ change }}</p>
            <p v-if="href" class="mt-2 text-xs font-medium text-indigo-600 dark:text-indigo-400">View logs →</p>
        </div>
    </component>
</template>
