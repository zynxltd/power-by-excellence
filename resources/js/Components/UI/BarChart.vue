<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { chartXTicks, shortenChartLabel } from '@/utils/chartTicks';

const props = defineProps({
    title: { type: String, default: '' },
    labels: { type: Array, default: () => [] },
    dates: { type: Array, default: () => [] },
    datasets: { type: Array, default: () => [] },
    height: { type: Number, default: 260 },
    valueFormatter: { type: Function, default: null },
    drilldownRoute: { type: String, default: '' },
    drilldownQuery: { type: Object, default: () => ({}) },
    showLegend: { type: Boolean, default: true },
    scrollable: { type: Boolean, default: null },
});

const LABEL_AREA_PX = 32;
const GROUP_MIN_WIDTH_PX = 28;

const hover = ref(null);

const toNumber = (value) => Number(value) || 0;

const shouldScroll = computed(() => {
    if (props.scrollable !== null) {
        return props.scrollable;
    }

    return props.labels.length > 14;
});

const groupWidth = computed(() => (
    shouldScroll.value
        ? GROUP_MIN_WIDTH_PX
        : Math.max(GROUP_MIN_WIDTH_PX, Math.floor(600 / Math.max(props.labels.length, 1)))
));

const plotMinWidth = computed(() => props.labels.length * groupWidth.value);

const niceMax = (peak) => {
    if (peak <= 0) {
        return 1;
    }

    const padded = peak * 1.08;
    const exp = Math.floor(Math.log10(padded));
    const base = 10 ** exp;
    const fraction = padded / base;

    let niceFraction = 10;
    if (fraction <= 1) niceFraction = 1;
    else if (fraction <= 2) niceFraction = 2;
    else if (fraction <= 5) niceFraction = 5;

    return niceFraction * base;
};

const maxValue = computed(() => {
    const all = props.datasets.flatMap((d) => (d.data ?? []).map(toNumber));
    const peak = all.length ? Math.max(...all) : 0;

    return niceMax(peak);
});

const gridLines = computed(() => {
    const lines = 4;

    return Array.from({ length: lines + 1 }, (_, i) => ({
        position: (i / lines) * 100,
        value: Math.round(maxValue.value * (1 - i / lines)),
    }));
});

const yAxisWidth = computed(() => {
    const labels = gridLines.value
        .slice(0, -1)
        .map((line) => String(formatValue(line.value)));
    const maxLen = labels.length ? Math.max(...labels.map((s) => s.length)) : 2;

    return Math.min(52, Math.max(28, maxLen * 6.5 + 6));
});

const formatValue = (v) => {
    if (props.valueFormatter) {
        return props.valueFormatter(v);
    }

    const n = Number(v);
    if (Number.isNaN(n)) {
        return String(v ?? '');
    }

    return new Intl.NumberFormat('en-GB', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(n);
};

const barHeightPct = (value) => {
    const v = toNumber(value);
    if (v <= 0) {
        return 0;
    }

    return Math.max(4, (v / maxValue.value) * 100);
};

const barBackground = (dataset) => {
    if (dataset.gradient && dataset.colorTo) {
        return `linear-gradient(to top, ${dataset.color}, ${dataset.colorTo})`;
    }

    return dataset.color;
};

const barWidthStyle = computed(() => {
    if (props.datasets.length === 1) {
        return { width: '65%', maxWidth: '36px' };
    }

    return { width: '42%', maxWidth: '18px' };
});

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

    const date = props.dates[index];
    const params = date
        ? { from_date: date, to_date: date }
        : { day_index: index };

    router.get(props.drilldownRoute, {
        ...props.drilldownQuery,
        ...params,
    });
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

const labelTicks = computed(() => new Set(chartXTicks(props.labels, 7).map((t) => t.index)));

const displayLabel = (label, index) => {
    if (shouldScroll.value || props.labels.length <= 10) {
        return shortenChartLabel(label, props.labels.length);
    }

    return labelTicks.value.has(index) ? shortenChartLabel(label, props.labels.length) : '';
};
</script>

<template>
    <div class="relative w-full">
        <p v-if="title" class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">{{ title }}</p>

        <div class="relative w-full" :style="{ height: `${height}px` }">
            <div
                class="pointer-events-none absolute inset-y-0 left-0 z-10 flex flex-col justify-between"
                :style="{ width: `${yAxisWidth}px`, bottom: `${LABEL_AREA_PX}px` }"
            >
                <div
                    v-for="(line, index) in gridLines"
                    :key="index"
                    class="relative flex items-center"
                    :style="{ height: index === gridLines.length - 1 ? '0' : `${100 / (gridLines.length - 1)}%` }"
                >
                    <span
                        v-if="index < gridLines.length - 1"
                        class="w-full pr-0.5 text-right text-[10px] tabular-nums leading-none text-slate-400"
                    >
                        {{ formatValue(line.value) }}
                    </span>
                </div>
            </div>

            <div
                v-if="tooltip"
                class="pointer-events-none absolute left-1/2 top-2 z-20 -translate-x-1/2 rounded-xl border border-slate-200/80 bg-white/95 px-3 py-2 text-xs shadow-xl backdrop-blur dark:border-slate-700 dark:bg-slate-900/95"
            >
                <p class="font-semibold text-slate-900 dark:text-white">{{ tooltip.label }}</p>
                <p v-for="row in tooltip.rows" :key="row.label" class="mt-0.5 font-medium" :style="{ color: row.color }">
                    {{ row.label }}: {{ formatValue(row.value) }}
                </p>
            </div>

            <div
                class="absolute inset-0 flex flex-col"
                :style="{ paddingLeft: `${yAxisWidth}px` }"
            >
                <div
                    class="relative min-h-0 flex-1"
                    :class="shouldScroll ? 'overflow-x-auto overflow-y-hidden' : 'overflow-hidden'"
                >
                    <div
                        class="relative h-full"
                        :style="{ minWidth: shouldScroll ? `${plotMinWidth}px` : '100%' }"
                    >
                        <div
                            class="pointer-events-none absolute inset-0 flex flex-col justify-between"
                        >
                            <div
                                v-for="(line, index) in gridLines"
                                :key="`grid-${index}`"
                                class="border-t border-slate-100 dark:border-slate-800/80"
                                :style="{ opacity: index === gridLines.length - 1 ? 0 : 1 }"
                            />
                        </div>

                        <div class="absolute inset-0 flex items-stretch justify-between gap-0.5">
                            <div
                                v-for="(label, i) in labels"
                                :key="`${label}-${i}`"
                                class="group flex h-full min-w-0 flex-col"
                                :style="{ width: shouldScroll ? `${groupWidth}px` : undefined, flex: shouldScroll ? '0 0 auto' : '1 1 0' }"
                                @mouseenter="onBarEnter(i)"
                                @mouseleave="onBarLeave"
                                @click="onBarClick(i)"
                            >
                                <div class="flex min-h-0 flex-1 items-end justify-center gap-0.5">
                                    <div
                                        v-for="(dataset, di) in datasets"
                                        :key="di"
                                        :class="[
                                            'rounded-t-md transition-all duration-200',
                                            hover === i ? 'opacity-100 shadow-md' : 'opacity-90',
                                            drilldownRoute ? 'cursor-pointer' : '',
                                        ]"
                                        :style="{
                                            height: `${barHeightPct(dataset.data[i])}%`,
                                            ...barWidthStyle,
                                            background: barBackground(dataset),
                                            transform: hover === i ? 'translateY(-2px)' : 'translateY(0)',
                                            boxShadow: hover === i ? `0 8px 20px -8px ${dataset.color}88` : 'none',
                                        }"
                                    />
                                </div>
                                <span
                                    :class="[
                                        'mt-1 shrink-0 text-center text-[10px] font-medium leading-tight transition',
                                        hover === i ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400',
                                        displayLabel(label, i) ? '' : 'invisible',
                                        shouldScroll ? 'w-full truncate' : '',
                                    ]"
                                    :title="label"
                                >
                                    {{ displayLabel(label, i) }}
                                </span>
                            </div>
                        </div>
                    </div>
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
