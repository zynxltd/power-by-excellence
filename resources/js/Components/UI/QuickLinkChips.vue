<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: { type: Array, default: () => [] },
    size: { type: String, default: 'sm' }, // sm | md
});
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <Link
            v-for="link in links"
            :key="link.href + link.label"
            :href="link.href"
            :title="link.description"
            :class="[
                'inline-flex items-center rounded-lg border font-medium transition',
                size === 'md' ? 'px-3.5 py-2 text-sm' : 'px-3 py-1.5 text-sm',
                link.active
                    ? 'border-indigo-600 bg-indigo-600 text-white shadow-sm'
                    : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-600 dark:hover:bg-indigo-950/40 dark:hover:text-indigo-300',
            ]"
        >
            {{ link.label }}
        </Link>
    </div>
</template>
