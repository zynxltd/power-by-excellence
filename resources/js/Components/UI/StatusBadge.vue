<script setup>
const props = defineProps({
    status: { type: String, required: true },
});

const styles = {
    sold: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-400',
    rejected: 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-400',
    unsold: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-400',
    quarantined: 'bg-purple-100 text-purple-800 dark:bg-purple-500/15 dark:text-purple-400',
    pending: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
    active: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-400',
    paused: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-400',
    inactive: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400',
    contacted: 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-400',
    converted: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-400',
    invalid: 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-400',
    processing: 'bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-400',
    validating: 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-400',
    distributing: 'bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-400',
    success: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-400',
    failed: 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-400',
    skipped: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-400',
};

const cls = styles[props.status] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
</script>

<template>
    <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize', cls]">
        {{ status.replace(/_/g, ' ') }}
    </span>
</template>
