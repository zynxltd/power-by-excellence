<script setup>
import { useTheme } from '@/Composables/useTheme';

defineProps({
    variant: { type: String, default: 'default' },
});

const { theme, toggle } = useTheme();
</script>

<template>
    <button
        type="button"
        :class="[
            'flex shrink-0 items-center justify-center rounded-md border transition',
            variant === 'dark'
                ? 'h-8 w-8 border-slate-700 bg-slate-900 text-slate-400 hover:bg-slate-800 hover:text-white'
                : 'h-9 w-9 border-slate-200 bg-white text-slate-500 hover:bg-slate-50 hover:text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-200',
        ]"
        :title="theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
        @click="toggle"
    >
        <svg v-if="theme === 'dark'" :class="variant === 'dark' ? 'h-4 w-4' : 'h-5 w-5'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
        <svg v-else :class="variant === 'dark' ? 'h-4 w-4' : 'h-5 w-5'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
        </svg>
    </button>
</template>
