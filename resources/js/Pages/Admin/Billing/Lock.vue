<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps({
    billing: Object,
    account: Object,
});

const unlock = () => {
    if (confirm('Unlock this account and restore full platform access?')) {
        router.post(route('billing.unlock'));
    }
};
</script>

<template>
    <Head title="Account Locked" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-2xl py-12 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-900/40">
                <svg class="h-8 w-8 text-rose-600 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>

            <h1 class="mt-6 text-2xl font-bold text-slate-900 dark:text-white">Account billing locked</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-400">
                <strong>{{ account.display_name }}</strong> is suspended until billing is resolved.
                Lead ingest, distribution, and most admin features are unavailable.
            </p>
            <p class="mt-3 text-sm text-amber-700 dark:text-amber-300">
                Tenant admins see this page when the platform operator locks the account. Contact your platform provider to restore access.
            </p>

            <Panel class="mt-8 text-left">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Status</dt>
                        <dd class="mt-1 capitalize text-slate-900 dark:text-white">{{ billing.status }}</dd>
                    </div>
                    <div v-if="billing.due_at">
                        <dt class="text-xs font-semibold uppercase text-slate-500">Billing due</dt>
                        <dd class="mt-1"><FormattedDate :value="billing.due_at" format="date" /></dd>
                    </div>
                    <div v-if="billing.locked_at" class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase text-slate-500">Locked since</dt>
                        <dd class="mt-1"><FormattedDate :value="billing.locked_at" /></dd>
                    </div>
                    <div v-if="billing.lock_reason" class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase text-slate-500">Reason</dt>
                        <dd class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ billing.lock_reason }}</dd>
                    </div>
                </dl>
            </Panel>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <AppButton :href="route('billing.index')">Manage billing</AppButton>
                <AppButton variant="secondary" :href="route('settings.edit')">Account settings</AppButton>
                <AppButton variant="secondary" @click="unlock">Unlock account</AppButton>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
