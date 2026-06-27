<script setup>
import { computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';

const page = usePage();
const billing = computed(() => page.props.auth?.billing);
const isBuyerPortal = computed(() => page.props.auth?.isBuyerPortal);
const isSupplierPortal = computed(() => page.props.auth?.isSupplierPortal);

const messages = {
    past_due: 'Your platform billing is past due. Lead processing continues - please arrange payment to avoid a platform lock.',
    locked: 'This account is locked due to billing. Most features are unavailable until resolved.',
};

const show = computed(() => billing.value && billing.value.status !== 'active');
const isLocked = computed(() => billing.value?.status === 'locked');

const billingHref = computed(() => {
    if (isLocked.value && (isBuyerPortal.value || isSupplierPortal.value)) {
        return route('portal.billing.lock');
    }
    if (isBuyerPortal.value) {
        return route('portal.buyer.billing');
    }
    if (isSupplierPortal.value) {
        return route('portal.supplier.billing');
    }
    return route('billing.index');
});

const linkLabel = computed(() => (
    isLocked.value && (isBuyerPortal.value || isSupplierPortal.value)
        ? 'View account status →'
        : 'View billing →'
));
</script>

<template>
    <div
        v-if="show"
        :class="[
            'border-b px-4 py-3 text-sm',
            isLocked
                ? 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-200'
                : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-200',
        ]"
    >
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2">
            <p>{{ messages[billing.status] ?? 'Billing attention required.' }}</p>
            <Link
                :href="billingHref"
                class="shrink-0 font-semibold underline underline-offset-2 hover:no-underline"
            >
                {{ linkLabel }}
            </Link>
        </div>
    </div>
</template>
