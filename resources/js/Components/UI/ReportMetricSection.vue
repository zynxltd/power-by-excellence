<script setup>
import Panel from '@/Components/UI/Panel.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    items: { type: Array, required: true },
    /** Max columns on large screens — items wrap into rows */
    columns: { type: Number, default: 4 },
});

const gridClass = computed(() => {
    const map = {
        2: 'sm:grid-cols-2',
        3: 'sm:grid-cols-2 lg:grid-cols-3',
        4: 'sm:grid-cols-2 lg:grid-cols-4',
        5: 'sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5',
        6: 'sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6',
        8: 'sm:grid-cols-2 md:grid-cols-4 xl:grid-cols-4 2xl:grid-cols-8',
    };

    return map[props.columns] ?? map[4];
});

const valueClass = (accent) => {
    const map = {
        emerald: 'text-emerald-600 dark:text-emerald-400',
        amber: 'text-amber-600 dark:text-amber-400',
        rose: 'text-rose-600 dark:text-rose-400',
        orange: 'text-orange-600 dark:text-orange-400',
        cyan: 'text-cyan-600 dark:text-cyan-400',
        indigo: 'text-indigo-600 dark:text-indigo-400',
        violet: 'text-violet-600 dark:text-violet-400',
    };

    return map[accent] ?? 'text-slate-900 dark:text-white';
};

const accentBorder = (accent) => {
    const map = {
        emerald: 'border-l-emerald-500',
        amber: 'border-l-amber-500',
        rose: 'border-l-rose-500',
        orange: 'border-l-orange-500',
        cyan: 'border-l-cyan-500',
        indigo: 'border-l-indigo-500',
        violet: 'border-l-violet-500',
    };

    return map[accent] ?? 'border-l-slate-300 dark:border-l-slate-600';
};

const accentGlow = (accent) => {
    const map = {
        emerald: 'from-emerald-500/8 to-transparent',
        amber: 'from-amber-500/8 to-transparent',
        rose: 'from-rose-500/8 to-transparent',
        orange: 'from-orange-500/8 to-transparent',
        cyan: 'from-cyan-500/8 to-transparent',
        indigo: 'from-indigo-500/8 to-transparent',
        violet: 'from-violet-500/8 to-transparent',
    };

    return map[accent] ?? 'from-slate-500/5 to-transparent';
};
</script>

<template>
    <Panel class="mb-4">
        <template #header>
            <div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ title }}</h3>
                <p v-if="description" class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ description }}</p>
            </div>
        </template>

        <div :class="['grid grid-cols-1 gap-3', gridClass]">
            <component
                :is="item.href ? Link : 'div'"
                v-for="item in items"
                :key="item.label"
                :href="item.href ?? undefined"
                :title="item.title ?? undefined"
                :class="[
                    'relative overflow-hidden rounded-xl border border-slate-200/80 border-l-[3px] bg-gradient-to-br to-white p-3.5 dark:border-slate-800 dark:to-slate-900',
                    accentBorder(item.accent),
                    accentGlow(item.accent),
                    item.href ? 'cursor-pointer transition hover:border-indigo-200 hover:shadow-md dark:hover:border-indigo-700' : '',
                ]"
            >
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ item.label }}
                </p>
                <p
                    :class="[
                        'mt-1.5 text-xl font-bold tabular-nums leading-tight tracking-tight sm:text-2xl',
                        valueClass(item.accent),
                    ]"
                >
                    {{ item.value }}
                </p>
            </component>
        </div>
    </Panel>
</template>
