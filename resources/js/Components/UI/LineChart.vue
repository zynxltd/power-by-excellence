<script setup>
import { computed, ref } from 'vue';
import { chartXTicks, xPositionPercent } from '@/utils/chartTicks';

const props = defineProps({
    labels: { type: Array, default: () => [] },
    datasets: { type: Array, default: () => [] },
    height: { type: Number, default: 260 },
    drilldownRoute: { type: String, default: '' },
    valueFormatter: { type: Function, default: null },
    maxXTicks: { type: Number, default: 7 },
});

const hover = ref(null);

const padding = { top: 12, right: 1, bottom: 40, left: 0 };
const labelRowHeight = 28;

const formatValue = (v) => (props.valueFormatter ? props.valueFormatter(v) : v);

const yAxisLabels = computed(() => (
    gridLines.value
        .slice(0, -1)
        .map((line) => ({
            value: line.value,
            topPercent: (line.y / props.height) * 100,
        }))
));

const yAxisWidth = computed(() => {
    const labels = yAxisLabels.value.map((line) => String(formatValue(line.value)));
    const maxLen = labels.length ? Math.max(...labels.map((s) => s.length)) : 2;

    return Math.min(48, Math.max(24, maxLen * 6.5 + 6));
});

const innerWidth = computed(() => 100 - padding.left - padding.right);
const innerHeight = computed(() => props.height - padding.top - padding.bottom - labelRowHeight);

const toNumber = (value) => Number(value) || 0;

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
        y: padding.top + (i / lines) * innerHeight.value,
        value: Math.round(maxValue.value * (1 - i / lines)),
    }));
});

const xTicks = computed(() => (
    chartXTicks(props.labels, props.maxXTicks).map((tick) => ({
        ...tick,
        left: xPositionPercent(tick.position, padding.left, padding.right),
    }))
));

const pointsFor = (dataset) => {
    const len = props.labels.length || 1;

    return (dataset.data ?? []).map((value, i) => {
        const x = padding.left + (len <= 1 ? innerWidth.value / 2 : (i / (len - 1)) * innerWidth.value);
        const y = padding.top + innerHeight.value - (toNumber(value) / maxValue.value) * innerHeight.value;

        return { x, y, value: toNumber(value), index: i };
    });
};

const smoothPath = (points) => {
    if (!points.length) return '';
    if (points.length === 1) return `M ${points[0].x} ${points[0].y}`;

    let d = `M ${points[0].x} ${points[0].y}`;

    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[i - 1] ?? points[i];
        const p1 = points[i];
        const p2 = points[i + 1];
        const p3 = points[i + 2] ?? p2;

        const cp1x = p1.x + (p2.x - p0.x) / 6;
        const cp1y = p1.y + (p2.y - p0.y) / 6;
        const cp2x = p2.x - (p3.x - p1.x) / 6;
        const cp2y = p2.y - (p3.y - p1.y) / 6;

        d += ` C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${p2.x} ${p2.y}`;
    }

    return d;
};

const areaPath = (points) => {
    if (!points.length) return '';
    const base = padding.top + innerHeight.value;
    const start = points[0];
    const end = points[points.length - 1];

    return `${smoothPath(points)} L ${end.x} ${base} L ${start.x} ${base} Z`;
};

const hoverIndex = computed(() => hover.value?.index ?? null);

const tooltip = computed(() => {
    if (hoverIndex.value === null) return null;
    const i = hoverIndex.value;

    return {
        label: props.labels[i],
        x: hover.value.x,
        y: hover.value.y,
        rows: props.datasets.map((d) => ({ label: d.label, value: d.data[i], color: d.color })),
    };
});

const onMouseMove = (event) => {
    const svg = event.currentTarget;
    const rect = svg.getBoundingClientRect();
    const x = ((event.clientX - rect.left) / rect.width) * 100;
    const len = props.labels.length;
    if (!len) return;

    const index = len <= 1
        ? 0
        : Math.max(0, Math.min(len - 1, Math.round(((x - padding.left) / innerWidth.value) * (len - 1))));

    const primary = props.datasets[0];
    if (!primary) return;

    const point = pointsFor(primary)[index];
    hover.value = point ? { ...point, index } : null;
};

const onMouseLeave = () => {
    hover.value = null;
};
</script>

<template>
    <div class="relative w-full">
        <div class="relative w-full" :style="{ height: `${height}px` }">
            <div
                class="pointer-events-none absolute inset-y-0 left-0 z-10"
                :style="{ width: `${yAxisWidth}px`, bottom: `${labelRowHeight}px` }"
            >
                <span
                    v-for="(label, index) in yAxisLabels"
                    :key="`y-${index}`"
                    class="absolute right-0 -translate-y-1/2 text-right text-[10px] font-medium tabular-nums leading-none text-slate-500 dark:text-slate-400"
                    :style="{ top: `${label.topPercent}%` }"
                >
                    {{ formatValue(label.value) }}
                </span>
            </div>

            <div
                class="absolute inset-0"
                :style="{ paddingLeft: `${yAxisWidth}px` }"
            >
                <svg
                    :viewBox="`0 0 100 ${height}`"
                    class="h-full w-full overflow-visible"
                    preserveAspectRatio="none"
                    @mousemove="onMouseMove"
                    @mouseleave="onMouseLeave"
                >
                <defs>
                    <linearGradient v-for="(dataset, di) in datasets" :key="`grad-${di}`" :id="`line-grad-${di}`" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" :stop-color="dataset.color" stop-opacity="0.3" />
                        <stop offset="100%" :stop-color="dataset.color" stop-opacity="0.02" />
                    </linearGradient>
                </defs>

                <g v-for="(line, index) in gridLines" :key="`grid-${index}`">
                    <line
                        :x1="padding.left"
                        :x2="100 - padding.right"
                        :y1="line.y"
                        :y2="line.y"
                        stroke="#e2e8f0"
                        stroke-width="0.15"
                        class="dark:stroke-slate-800"
                    />
                </g>

                <template v-for="(dataset, di) in datasets" :key="di">
                    <path
                        v-if="pointsFor(dataset).length"
                        :d="areaPath(pointsFor(dataset))"
                        :fill="`url(#line-grad-${di})`"
                    />
                    <path
                        v-if="pointsFor(dataset).length"
                        :d="smoothPath(pointsFor(dataset))"
                        fill="none"
                        :stroke="dataset.color"
                        stroke-width="0.35"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                    <circle
                        v-for="point in pointsFor(dataset)"
                        :key="`${di}-${point.index}`"
                        :cx="point.x"
                        :cy="point.y"
                        r="0.55"
                        :fill="dataset.color"
                        class="opacity-0 transition-opacity"
                        :class="hoverIndex === point.index ? 'opacity-100' : ''"
                    />
                </template>

                <line
                    v-if="tooltip"
                    :x1="tooltip.x"
                    :x2="tooltip.x"
                    :y1="padding.top"
                    :y2="padding.top + innerHeight"
                    stroke="#94a3b8"
                    stroke-width="0.15"
                    stroke-dasharray="0.6 0.6"
                />
                <circle
                    v-if="tooltip"
                    :cx="tooltip.x"
                    :cy="tooltip.y"
                    r="1"
                    fill="#fff"
                    stroke="#6366f1"
                    stroke-width="0.25"
                />
            </svg>

            <div
                class="pointer-events-none absolute inset-x-0 bottom-0"
                :style="{ height: `${labelRowHeight}px` }"
            >
                <span
                    v-for="tick in xTicks"
                    :key="tick.index"
                    class="absolute bottom-0 max-w-[3.5rem] -translate-x-1/2 truncate text-center text-[10px] font-medium leading-tight text-slate-500 dark:text-slate-400"
                    :style="{ left: `${tick.left}%` }"
                    :title="labels[tick.index]"
                >
                    {{ tick.label }}
                </span>
            </div>

            <div
                v-if="tooltip"
                class="pointer-events-none absolute z-10 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs shadow-lg dark:border-slate-700 dark:bg-slate-900"
                :style="{
                    left: `${Math.min(Math.max(tooltip.x, 6), 94)}%`,
                    top: `${Math.max((tooltip.y / height) * 100 - 14, 2)}%`,
                    transform: 'translateX(-50%)',
                }"
            >
                <p class="font-semibold text-slate-900 dark:text-white">{{ tooltip.label }}</p>
                <p v-for="row in tooltip.rows" :key="row.label" class="mt-0.5" :style="{ color: row.color }">
                    {{ row.label }}: {{ formatValue(row.value) }}
                </p>
            </div>
            </div>
        </div>

        <div v-if="datasets.length > 1" class="mt-3 flex flex-wrap gap-x-4 gap-y-1 border-t border-slate-100 pt-3 dark:border-slate-800" :style="{ marginLeft: `${yAxisWidth}px` }">
            <div v-for="(dataset, i) in datasets" :key="i" class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400">
                <span class="h-2 w-3 rounded-full" :style="{ backgroundColor: dataset.color }" />
                {{ dataset.label }}
            </div>
        </div>
    </div>
</template>
