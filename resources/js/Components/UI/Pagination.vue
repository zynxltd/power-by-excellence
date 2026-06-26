<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: { type: Array, default: () => [] },
});
</script>

<template>
    <div
        v-if="links?.length > 3"
        class="flex flex-wrap justify-center gap-1 border-t border-slate-100 px-3 py-2 sm:px-4 dark:border-slate-800"
    >
        <Link
            v-for="link in links"
            :key="link.label + (link.url ?? '')"
            :href="link.url ?? '#'"
            :class="[
                'rounded-md px-2.5 py-1 text-xs',
                link.active
                    ? 'bg-indigo-600 text-white'
                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
                !link.url && 'pointer-events-none opacity-40',
            ]"
            v-html="link.label"
            preserve-scroll
            preserve-state
        />
    </div>
</template>
