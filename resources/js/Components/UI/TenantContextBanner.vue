<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const account = computed(() => page.props.auth.account);
const isSuperAdmin = computed(() => page.props.auth.isSuperAdmin);
</script>

<template>
    <div
        v-if="isSuperAdmin && !account"
        class="mb-6 flex flex-col gap-2 rounded-xl border border-amber-200/80 bg-amber-50/90 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-amber-500/30 dark:bg-amber-500/10"
    >
        <p class="text-sm text-amber-950 dark:text-amber-100">
            <span class="font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400">No tenant selected</span>
            <span class="mx-2 text-amber-300 dark:text-amber-600">·</span>
            <span>You're viewing data across <strong>all partner platforms</strong>. Select a tenant to manage buyers, suppliers, and scoped settings.</span>
        </p>
        <Link
            :href="route('accounts.index')"
            class="shrink-0 text-sm font-semibold text-amber-800 underline decoration-amber-300 underline-offset-2 hover:text-amber-950 dark:text-amber-300 dark:hover:text-white"
        >
            Choose platform →
        </Link>
    </div>
    <div
        v-else-if="account"
        class="mb-6 flex flex-col gap-2 rounded-xl border border-indigo-200/80 bg-indigo-50/90 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-indigo-500/30 dark:bg-indigo-500/10"
    >
        <p class="text-sm text-indigo-950 dark:text-indigo-100">
            <span class="font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Active tenant</span>
            <span class="mx-2 text-indigo-300 dark:text-indigo-600">·</span>
            <span class="font-semibold">{{ account.display_name || account.name }}</span>
            <span v-if="!isSuperAdmin" class="text-indigo-700 dark:text-indigo-300"> — buyers and suppliers are scoped to this platform.</span>
            <span v-else class="text-indigo-700 dark:text-indigo-300"> — manage buyers and suppliers for this platform below, or switch tenant.</span>
        </p>
        <Link
            v-if="isSuperAdmin"
            :href="route('accounts.index')"
            class="shrink-0 text-sm font-semibold text-indigo-700 underline decoration-indigo-300 underline-offset-2 hover:text-indigo-900 dark:text-indigo-300 dark:hover:text-white"
        >
            Switch platform →
        </Link>
    </div>
</template>
