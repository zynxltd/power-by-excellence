<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
    columns: {
        type: [Number, String],
        default: null,
    },
});

const gridCols = () => {
    const n = props.columns ?? props.items.length;
    return {
        5: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5',
        7: 'grid-cols-2 sm:grid-cols-4 lg:grid-cols-7',
        10: 'grid-cols-2 sm:grid-cols-5 lg:grid-cols-10',
    }[n] ?? `grid-cols-${Math.min(n, 5)}`;
};
</script>

<template>
    <div class="w-full overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div :class="['grid w-full divide-x divide-y divide-slate-200 dark:divide-slate-800', gridCols()]">
            <component
                :is="item.href ? Link : 'div'"
                v-for="item in items"
                :key="item.label"
                :href="item.href ?? undefined"
                :title="item.title ?? undefined"
                :class="[
                    'flex min-w-0 flex-col px-3 py-2.5 sm:px-4',
                    item.href ? 'cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60' : '',
                ]"
            >
                <span class="truncate text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ item.label }}
                </span>
                <span
                    :class="[
                        'mt-0.5 truncate text-base font-bold leading-tight sm:text-lg',
                        item.accent === 'emerald' ? 'text-emerald-600 dark:text-emerald-400'
                        : item.accent === 'amber' ? 'text-amber-600 dark:text-amber-400'
                        : item.accent === 'rose' ? 'text-rose-600 dark:text-rose-400'
                        : item.accent === 'cyan' ? 'text-cyan-600 dark:text-cyan-400'
                        : item.accent === 'violet' ? 'text-violet-600 dark:text-violet-400'
                        : 'text-slate-900 dark:text-white',
                    ]"
                >
                    {{ item.value }}
                </span>
            </component>
        </div>
    </div>
</template>
