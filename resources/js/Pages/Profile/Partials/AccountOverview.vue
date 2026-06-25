<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useDateFormat } from '@/composables/useDateFormat';

const page = usePage();
const { formatDate } = useDateFormat();
const user = computed(() => page.props.auth.user);
const account = computed(() => page.props.auth.account);

const roleLabel = computed(() => {
    if (page.props.auth.isBuyerPortal) return 'Buyer Portal User';
    if (page.props.auth.isSupplierPortal) return 'Supplier Portal User';
    if (page.props.auth.isSuperAdmin) return 'Super Administrator';
    return 'Platform Administrator';
});

const memberSince = computed(() => formatDate(user.value?.created_at));
</script>

<template>
    <div>
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Account Overview</h3>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Your platform membership and access level.</p>

        <dl class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Role</dt>
                <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white">{{ roleLabel }}</dd>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Platform</dt>
                <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white">{{ account?.display_name ?? account?.name ?? '—' }}</dd>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Member since</dt>
                <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white">{{ memberSince }}</dd>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Email verified</dt>
                <dd class="mt-1 text-sm font-medium" :class="user?.email_verified_at ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'">
                    {{ user?.email_verified_at ? 'Yes' : 'Pending verification' }}
                </dd>
            </div>
        </dl>

        <p v-if="page.props.auth.isBuyerPortal" class="mt-4 text-sm text-slate-500 dark:text-slate-400">
            Manage your credit and billing from the
            <a :href="route('portal.buyer.billing')" class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Billing</a>
            section.
        </p>
    </div>
</template>
