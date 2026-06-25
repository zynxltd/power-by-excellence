<script setup>
defineProps({
    message: { type: String, default: '' },
    type: { type: String, default: 'success' },
});

const styles = {
    success: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-400',
    error: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-400',
    demo: 'border-indigo-200 bg-indigo-50 text-indigo-800 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-400',
};
</script>

<template>
    <div
        v-if="message"
        :class="['mb-6 flex items-center gap-3 rounded-xl border px-4 py-3 text-sm', styles[type] ?? styles.success]"
    >
        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        {{ message }}
    </div>
</template>
