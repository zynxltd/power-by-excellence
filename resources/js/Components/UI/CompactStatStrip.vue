<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

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

const columnCount = computed(() => Number(props.columns) || props.items.length);

const gridStyle = computed(() => ({
    gridTemplateColumns: `repeat(${columnCount.value}, minmax(4.5rem, 1fr))`,
}));
</script>

<template>
    <div class="w-full overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div
            class="grid w-full min-w-full divide-x divide-slate-200 dark:divide-slate-800"
            :style="gridStyle"
        >
            <component
                :is="item.href ? Link : 'div'"
                v-for="item in items"
                :key="item.label"
                :href="item.href ?? undefined"
                :title="item.title ?? undefined"
                :class="[
                    'flex min-w-0 flex-col items-center px-2.5 py-2.5 text-center sm:px-3',
                    item.href ? 'cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60' : '',
                ]"
            >
                <span class="truncate text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ item.label }}
                </span>
                <span
                    :class="[
                        'mt-0.5 truncate text-sm font-bold leading-tight sm:text-base',
                        item.accent === 'emerald' ? 'text-emerald-600 dark:text-emerald-400'
                        : item.accent === 'amber' ? 'text-amber-600 dark:text-amber-400'
                        : item.accent === 'rose' ? 'text-rose-600 dark:text-rose-400'
                        : item.accent === 'orange' ? 'text-orange-600 dark:text-orange-400'
                        : item.accent === 'cyan' ? 'text-cyan-600 dark:text-cyan-400'
                        : item.accent === 'indigo' ? 'text-indigo-600 dark:text-indigo-400'
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
