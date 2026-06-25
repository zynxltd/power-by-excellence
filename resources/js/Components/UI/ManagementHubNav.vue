<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    type: { type: String, required: true }, // buyer | supplier
    entity: Object,
});

const links = (type, id) => ({
    buyer: [
        { label: 'Overview', route: 'buyers.show', params: id },
        { label: 'Edit', route: 'buyers.edit', params: id },
        { label: 'Billing', route: 'billing.show', params: id },
        { label: 'All buyers', route: 'buyers.index', params: null },
    ],
    supplier: [
        { label: 'Overview', route: 'suppliers.show', params: id },
        { label: 'Edit', route: 'suppliers.edit', params: id },
        { label: 'All suppliers', route: 'suppliers.index', params: null },
    ],
}[type] ?? []);
</script>

<template>
    <nav class="mb-6 flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-slate-50 p-2 dark:border-slate-700 dark:bg-slate-800/50">
        <Link
            v-for="item in links(type, entity?.id)"
            :key="item.label"
            :href="item.params ? route(item.route, item.params) : route(item.route)"
            :class="[
                'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                route().current(item.route) || route().current(item.route.replace('.show', '.*'))
                    ? 'bg-indigo-600 text-white'
                    : 'text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-700',
            ]"
        >
            {{ item.label }}
        </Link>
    </nav>
</template>
