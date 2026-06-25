<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    labels: { type: Array, default: () => [] },
    datasets: { type: Array, default: () => [] },
    height: { type: Number, default: 180 },
    drilldownRoute: { type: String, default: '' },
    valueFormatter: { type: Function, default: null },
});

const hover = ref(null);

const padding = { top: 16, right: 12, bottom: 28, left: 8 };
const innerWidth = computed(() => 100 - padding.left - padding.right);
const innerHeight = computed(() => props.height - padding.top - padding.bottom);

const maxValue = computed(() => {
    const all = props.datasets.flatMap((d) => d.data);
    return Math.max(...all, 1);
});

const pointsFor = (dataset) => {
    const len = props.labels.length || 1;
    return dataset.data.map((value, i) => {
        const x = padding.left + (len <= 1 ? innerWidth.value / 2 : (i / (len - 1)) * innerWidth.value);
        const y = padding.top + innerHeight.value - (value / maxValue.value) * innerHeight.value;
        return { x, y, value, index: i };
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

const formatValue = (v) => (props.valueFormatter ? props.valueFormatter(v) : v);

const onPointClick = (index) => {
    if (!props.drilldownRoute) return;
    router.get(props.drilldownRoute, { day_index: index });
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
    <div class="relative w-full" :style="{ height: `${height}px` }">
        <svg
            :viewBox="`0 0 100 ${height}`"
            class="h-full w-full overflow-visible"
            preserveAspectRatio="none"
            @mousemove="onMouseMove"
            @mouseleave="onMouseLeave"
        >
            <defs>
                <linearGradient v-for="(dataset, di) in datasets" :key="`grad-${di}`" :id="`line-grad-${di}`" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" :stop-color="dataset.color" stop-opacity="0.35" />
                    <stop offset="100%" :stop-color="dataset.color" stop-opacity="0.02" />
                </linearGradient>
            </defs>
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
                    stroke-width="0.75"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    vector-effect="non-scaling-stroke"
                />
            </template>
            <line
                v-if="tooltip"
                :x1="tooltip.x"
                :x2="tooltip.x"
                :y1="padding.top"
                :y2="padding.top + innerHeight"
                stroke="#94a3b8"
                stroke-width="0.25"
                stroke-dasharray="1 1"
                vector-effect="non-scaling-stroke"
            />
            <circle
                v-if="tooltip"
                :cx="tooltip.x"
                :cy="tooltip.y"
                r="1.4"
                fill="#fff"
                stroke="#6366f1"
                stroke-width="0.5"
                vector-effect="non-scaling-stroke"
            />
        </svg>
        <div class="mt-1 flex justify-between gap-1 px-1">
            <span v-for="label in labels" :key="label" class="flex-1 truncate text-center text-[10px] text-slate-500">{{ label }}</span>
        </div>
        <div
            v-if="tooltip"
            class="pointer-events-none absolute z-10 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs shadow-lg dark:border-slate-700 dark:bg-slate-900"
            :style="{
                left: `${Math.min(Math.max(tooltip.x, 8), 92)}%`,
                top: `${Math.max((tooltip.y / height) * 100 - 18, 4)}%`,
                transform: 'translateX(-50%)',
            }"
        >
            <p class="font-semibold text-slate-900 dark:text-white">{{ tooltip.label }}</p>
            <p v-for="row in tooltip.rows" :key="row.label" class="mt-0.5" :style="{ color: row.color }">
                {{ row.label }}: {{ formatValue(row.value) }}
            </p>
        </div>
        <div v-if="datasets.length > 1" class="mt-3 flex flex-wrap gap-4">
            <div v-for="(dataset, i) in datasets" :key="i" class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                <span class="h-2 w-4 rounded-full" :style="{ backgroundColor: dataset.color }" />
                {{ dataset.label }}
            </div>
        </div>
    </div>
</template>
