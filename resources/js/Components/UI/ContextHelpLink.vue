<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    slug: { type: String, required: true },
    label: { type: String, default: 'Help' },
});
</script>

<template>
    <Link
        :href="route('help.show', slug)"
        target="_blank"
        class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
    >
        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        {{ label }}
    </Link>
</template>
