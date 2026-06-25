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
    showLegend: { type: Boolean, default: true },
});

const LABEL_AREA_PX = 28;

const hover = ref(null);

const toNumber = (value) => Number(value) || 0;

const plotHeight = computed(() => Math.max(props.height - LABEL_AREA_PX, 40));

const maxValue = computed(() => {
    const all = props.datasets.flatMap((d) => (d.data ?? []).map(toNumber));
    const peak = all.length ? Math.max(...all) : 0;
    if (peak === 0) {
        return 1;
    }

    const magnitude = Math.pow(10, Math.floor(Math.log10(peak)));
    return Math.ceil(peak / magnitude) * magnitude;
});

const gridLines = computed(() => {
    const lines = 4;
    return Array.from({ length: lines + 1 }, (_, i) => ({
        position: (i / lines) * 100,
        value: Math.round(maxValue.value * (1 - i / lines)),
    }));
});

const formatValue = (v) => (props.valueFormatter ? props.valueFormatter(v) : v);

const barHeightPx = (value) => {
    const v = toNumber(value);
    if (v <= 0) {
        return 0;
    }

    return Math.max(4, Math.round((v / maxValue.value) * plotHeight.value));
};

const barBackground = (dataset) => {
    if (dataset.gradient && dataset.colorTo) {
        return `linear-gradient(to top, ${dataset.color}, ${dataset.colorTo})`;
    }

    return dataset.color;
};

const barWidthClass = computed(() => (
    props.datasets.length === 1 ? 'w-9 sm:w-10' : 'min-w-[3px] flex-1'
));

const onBarEnter = (index) => {
    hover.value = index;
};

const onBarLeave = () => {
    hover.value = null;
};

const onBarClick = (index) => {
    if (!props.drilldownRoute) {
        return;
    }

    router.get(props.drilldownRoute, { day_index: index });
};

const tooltip = computed(() => {
    if (hover.value === null) {
        return null;
    }

    const i = hover.value;

    return {
        label: props.labels[i],
        rows: props.datasets.map((d) => ({ label: d.label, value: d.data[i], color: d.color })),
    };
});

const periodTotal = computed(() => (
    props.datasets.reduce((sum, dataset) => (
        sum + (dataset.data ?? []).reduce((inner, value) => inner + toNumber(value), 0)
    ), 0)
));
</script>

<template>
    <div class="relative">
        <p v-if="title" class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">{{ title }}</p>

        <div class="relative" :style="{ height: `${height}px` }">
            <div class="pointer-events-none absolute inset-x-0 bottom-7 left-8 top-0 flex flex-col justify-between">
                <div
                    v-for="(line, index) in gridLines"
                    :key="index"
                    class="relative border-t border-slate-100 dark:border-slate-800/80"
                    :style="{ opacity: index === gridLines.length - 1 ? 0 : 1 }"
                >
                    <span
                        v-if="index < gridLines.length - 1"
                        class="absolute -left-8 -top-2 w-7 text-right text-[9px] tabular-nums text-slate-400"
                    >
                        {{ formatValue(line.value) }}
                    </span>
                </div>
            </div>

            <div
                v-if="tooltip"
                class="pointer-events-none absolute left-1/2 top-0 z-20 -translate-x-1/2 rounded-xl border border-slate-200/80 bg-white/95 px-3 py-2 text-xs shadow-xl backdrop-blur dark:border-slate-700 dark:bg-slate-900/95"
            >
                <p class="font-semibold text-slate-900 dark:text-white">{{ tooltip.label }}</p>
                <p v-for="row in tooltip.rows" :key="row.label" class="mt-0.5 font-medium" :style="{ color: row.color }">
                    {{ row.label }}: {{ formatValue(row.value) }}
                </p>
            </div>

            <div
                class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-1 pl-8 sm:gap-2"
                :style="{ height: `${plotHeight + LABEL_AREA_PX}px` }"
            >
                <div
                    v-for="(label, i) in labels"
                    :key="`${label}-${i}`"
                    class="group flex h-full flex-1 flex-col items-center justify-end"
                    @mouseenter="onBarEnter(i)"
                    @mouseleave="onBarLeave"
                    @click="onBarClick(i)"
                >
                    <div
                        class="flex w-full items-end justify-center gap-1"
                        :style="{ height: `${plotHeight}px` }"
                    >
                        <div
                            v-for="(dataset, di) in datasets"
                            :key="di"
                            :class="[
                                barWidthClass,
                                'rounded-t-lg transition-all duration-200',
                                hover === i ? 'opacity-100 shadow-md' : 'opacity-90',
                                drilldownRoute ? 'cursor-pointer' : '',
                            ]"
                            :style="{
                                height: `${barHeightPx(dataset.data[i])}px`,
                                background: barBackground(dataset),
                                transform: hover === i ? 'translateY(-2px)' : 'translateY(0)',
                                boxShadow: hover === i ? `0 8px 20px -8px ${dataset.color}88` : 'none',
                            }"
                        />
                    </div>
                    <span
                        :class="[
                            'mt-1 text-center text-[10px] font-medium leading-tight transition',
                            hover === i ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400',
                        ]"
                    >
                        {{ label }}
                    </span>
                </div>
            </div>
        </div>

        <div v-if="showLegend && datasets.length" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3 dark:border-slate-800">
            <div class="flex flex-wrap gap-4">
                <div v-for="(dataset, i) in datasets" :key="i" class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                    <span
                        class="h-2.5 w-2.5 rounded-sm shadow-sm"
                        :style="{ background: barBackground(dataset) }"
                    />
                    {{ dataset.label }}
                </div>
            </div>
            <p v-if="datasets.length === 1 && periodTotal > 0" class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                Period total:
                <span class="text-slate-900 dark:text-white">{{ formatValue(periodTotal) }}</span>
            </p>
        </div>
    </div>
</template>
