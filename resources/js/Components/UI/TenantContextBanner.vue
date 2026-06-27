<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const account = computed(() => page.props.auth.account);
const isSuperAdmin = computed(() => page.props.auth.isSuperAdmin);

const clearTenantContext = () => router.post(route('accounts.clear'));
</script>

<template>
    <div
        v-if="isSuperAdmin && !account"
        class="mb-4 flex flex-col gap-1.5 rounded-lg border border-amber-200/80 bg-amber-50/90 px-3 py-2 sm:flex-row sm:items-center sm:justify-between dark:border-amber-500/30 dark:bg-amber-500/10"
    >
        <p class="text-sm leading-relaxed text-amber-950 dark:text-amber-100">
            <span class="block font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400">No tenant selected</span>
            <span class="mt-1 block">You're viewing data across <strong>all partner platforms</strong>. Each platform is self-serviced by its tenant admin — select one here only when you need to support or inspect a specific partner.</span>
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
        class="mb-4 flex flex-col gap-1.5 rounded-lg border border-indigo-200/80 bg-indigo-50/90 px-3 py-2 sm:flex-row sm:items-center sm:justify-between dark:border-indigo-500/30 dark:bg-indigo-500/10"
    >
        <p class="text-sm leading-relaxed text-indigo-950 dark:text-indigo-100">
            <span class="block font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Active tenant</span>
            <span class="mt-1 block">
                <span class="font-semibold">{{ account.display_name || account.name }}</span>
                <span v-if="!isSuperAdmin" class="text-indigo-700 dark:text-indigo-300"> - buyers and suppliers are scoped to this platform.</span>
                <span v-else class="text-indigo-700 dark:text-indigo-300"> - manage buyers and suppliers for this platform below, or switch tenant.</span>
            </span>
        </p>
        <div v-if="isSuperAdmin" class="flex shrink-0 flex-wrap items-center gap-3">
            <button
                type="button"
                class="text-sm font-semibold text-indigo-700 underline decoration-indigo-300 underline-offset-2 hover:text-indigo-900 dark:text-indigo-300 dark:hover:text-white"
                @click="clearTenantContext"
            >
                All platforms (central admin) →
            </button>
            <Link
                :href="route('accounts.index')"
                class="text-sm font-semibold text-indigo-700 underline decoration-indigo-300 underline-offset-2 hover:text-indigo-900 dark:text-indigo-300 dark:hover:text-white"
            >
                Switch platform →
            </Link>
        </div>
    </div>
</template>
