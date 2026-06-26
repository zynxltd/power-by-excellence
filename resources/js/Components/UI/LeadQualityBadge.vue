<script setup>
import { computed } from 'vue';

const props = defineProps({
    quality: { type: Object, default: null },
    compact: { type: Boolean, default: false },
});

const gradeStyles = {
    excellent: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    good: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300',
    fair: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    poor: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
};

const badgeClass = computed(() => [
    'inline-flex items-center gap-1 rounded-full font-semibold',
    props.compact ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-0.5 text-xs',
    gradeStyles[props.quality?.grade] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
]);
</script>

<template>
    <span v-if="quality" :class="badgeClass" :title="`${quality.grade_label} — email: ${quality.email?.label}, HLR: ${quality.hlr?.label}, IP: ${quality.ip?.label ?? 'n/a'}`">
        <span>{{ quality.score }}</span>
        <span v-if="!compact" class="font-normal opacity-80">{{ quality.grade_label }}</span>
    </span>
    <span v-else class="text-xs text-slate-400">—</span>
</template>
