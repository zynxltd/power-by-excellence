<script setup>
import { computed } from 'vue';

const props = defineProps({
    usage: { type: Object, default: null },
    compact: { type: Boolean, default: false },
});

const daily = computed(() => props.usage?.daily ?? null);

const label = computed(() => {
    if (!daily.value || daily.value.limit === null) {
        return '—';
    }

    return `${daily.value.used} / ${daily.value.limit}`;
});

const atCap = computed(() =>
    daily.value?.limit !== null && daily.value.used >= daily.value.limit,
);

const badgeClass = computed(() => {
    if (!daily.value || daily.value.limit === null) {
        return 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400';
    }

    if (atCap.value) {
        return 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300';
    }

    if (daily.value.used >= daily.value.limit * 0.8) {
        return 'bg-amber-100 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200';
    }

    return 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300';
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center rounded-full font-semibold tabular-nums',
            compact ? 'px-1.5 py-0.5 text-[10px]' : 'px-2 py-0.5 text-[11px]',
            badgeClass,
        ]"
        :title="daily?.limit !== null ? `Daily cap: ${daily.used} of ${daily.limit} used` : 'No daily cap'"
    >
        <span v-if="!compact" class="mr-1 font-normal opacity-75">cap</span>{{ label }}
    </span>
</template>
