<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    title: { type: String, default: '' },
    labels: { type: Array, default: () => [] },
    datasets: { type: Array, default: () => [] },
    height: { type: Number, default: 200 },
    valueFormatter: { type: Function, default: null },
    drilldownRoute: { type: String, default: '' },
});

const hover = ref(null);

const maxValue = computed(() => {
    const all = props.datasets.flatMap((d) => d.data);
    return Math.max(...all, 1);
});

const formatValue = (v) => (props.valueFormatter ? props.valueFormatter(v) : v);

const onBarEnter = (index) => {
    hover.value = index;
};

const onBarLeave = () => {
    hover.value = null;
};

const onBarClick = (index) => {
    if (!props.drilldownRoute) return;
    router.get(props.drilldownRoute, { day_index: index });
};

const tooltip = computed(() => {
    if (hover.value === null) return null;
    const i = hover.value;
    return {
        label: props.labels[i],
        rows: props.datasets.map((d) => ({ label: d.label, value: d.data[i], color: d.color })),
    };
});
</script>

<template>
    <div class="relative">
        <p v-if="title" class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">{{ title }}</p>
        <div class="flex items-end gap-2" :style="{ height: `${height}px` }">
            <div
                v-for="(label, i) in labels"
                :key="label"
                class="group flex flex-1 flex-col items-center justify-end gap-1"
                @mouseenter="onBarEnter(i)"
                @mouseleave="onBarLeave"
                @click="onBarClick(i)"
            >
                <div class="flex w-full items-end justify-center gap-0.5" :style="{ height: `${height - 24}px` }">
                    <div
                        v-for="(dataset, di) in datasets"
                        :key="di"
                        :class="[
                            'min-w-[2px] flex-1 rounded-t-md transition-all',
                            hover === i ? 'opacity-100' : 'opacity-90',
                            drilldownRoute ? 'cursor-pointer' : '',
                        ]"
                        :style="{
                            height: `${(dataset.data[i] / maxValue) * 100}%`,
                            backgroundColor: dataset.color,
                            minHeight: dataset.data[i] > 0 ? '4px' : '0',
                            boxShadow: hover === i ? `0 0 0 1px ${dataset.color}` : 'none',
                        }"
                    />
                </div>
                <span
                    :class="[
                        'text-[10px] font-medium transition',
                        hover === i ? 'text-slate-800 dark:text-slate-200' : 'text-slate-500 dark:text-slate-400',
                    ]"
                >
                    {{ label }}
                </span>
            </div>
        </div>

        <div
            v-if="tooltip"
            class="pointer-events-none absolute left-1/2 top-2 z-10 -translate-x-1/2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs shadow-lg dark:border-slate-700 dark:bg-slate-900"
        >
            <p class="font-semibold text-slate-900 dark:text-white">{{ tooltip.label }}</p>
            <p v-for="row in tooltip.rows" :key="row.label" class="mt-0.5" :style="{ color: row.color }">
                {{ row.label }}: {{ formatValue(row.value) }}
            </p>
        </div>

        <div v-if="datasets.length > 1" class="mt-3 flex flex-wrap gap-4">
            <div v-for="(dataset, i) in datasets" :key="i" class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                <span class="h-2.5 w-2.5 rounded-sm" :style="{ backgroundColor: dataset.color }" />
                {{ dataset.label }}
            </div>
        </div>
    </div>
</template>
