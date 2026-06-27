<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { Head, usePage } from '@inertiajs/vue3';

const props = defineProps({
    buyer: Object,
});

const page = usePage();
const currency = page.props.auth?.account?.default_currency ?? 'GBP';
const { formatMoney } = useMoneyFormat(currency);
</script>

<template>
    <Head title="Payment successful" />
    <AuthenticatedLayout>
        <PageHeader title="Payment successful" description="Your credit top-up has been processed." />

        <Panel class="mx-auto max-w-lg text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                <svg class="h-7 w-7 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h2 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">Thank you!</h2>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                Your updated balance for <strong>{{ buyer?.name }}</strong> is
                <strong class="text-emerald-600 dark:text-emerald-400">{{ formatMoney(buyer?.credit_balance ?? 0) }}</strong>.
            </p>
            <p class="mt-2 text-xs text-slate-500">It may take a moment for the transaction to appear in your ledger.</p>

            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <AppButton :href="route('portal.buyer.billing')">View billing</AppButton>
                <AppButton variant="secondary" :href="route('portal.buyer.dashboard')">Dashboard</AppButton>
            </div>
        </Panel>
    </AuthenticatedLayout>
</template>
