<script setup>
import Spinner from '@/Components/UI/Spinner.vue';

defineProps({
    empty: { type: Boolean, default: false },
    emptyMessage: { type: String, default: 'No records found.' },
    loading: { type: Boolean, default: false },
});
</script>

<template>
    <div class="relative overflow-x-auto">
        <Transition
            enter-active-class="transition duration-150"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="loading"
                class="absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-white/70 backdrop-blur-[1px] dark:bg-slate-900/70"
                aria-busy="true"
                aria-live="polite"
            >
                <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    <Spinner size="sm" class="text-indigo-500" />
                    Loading…
                </div>
            </div>
        </Transition>
        <table class="admin-compact-table min-w-full divide-y divide-slate-100 text-xs dark:divide-slate-800">
            <thead>
                <tr class="bg-slate-50/80 dark:bg-slate-800/50">
                    <slot name="head" />
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <slot />
            </tbody>
        </table>
        <div v-if="empty && !loading" class="px-4 py-6 text-center text-xs text-slate-500 dark:text-slate-400">
            {{ emptyMessage }}
        </div>
    </div>
</template>
