<script setup>
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import PingTreeTierTable from '@/Components/UI/PingTreeTierTable.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    config: { type: Object, required: true },
});
</script>

<template>
    <article class="rounded-xl border border-slate-200 bg-slate-50/40 dark:border-slate-700 dark:bg-slate-800/20">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-700">
            <div class="min-w-0">
                <h4 class="truncate font-semibold text-slate-900 dark:text-white">{{ config.name }}</h4>
                <p class="mt-0.5 text-xs text-slate-500">
                    {{ config.config?.groups?.length ?? 0 }} routing tiers
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-3">
                <StatusBadge :status="config.is_active ? 'active' : 'inactive'" />
                <Link
                    :href="route('distribution.show', config.id)"
                    class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300"
                >
                    View
                </Link>
                <Link
                    :href="route('distribution.edit', config.id)"
                    class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                >
                    Edit
                </Link>
            </div>
        </div>
        <div class="p-4">
            <PingTreeTierTable :groups="config.config?.groups ?? []" />
        </div>
    </article>
</template>
