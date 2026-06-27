<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    entitlement: Object,
    summary: Object,
    topLinks: Array,
    pendingQueue: Object,
    capAlerts: Array,
    filters: Object,
});

const { formatMoney } = useMoneyFormat();

const strip = computed(() => [
    { label: 'Clicks today', value: props.summary?.today?.clicks ?? 0, accent: 'indigo' },
    { label: 'Conversions today', value: props.summary?.today?.conversions ?? 0, accent: 'emerald' },
    { label: 'Revenue today', value: formatMoney(props.summary?.today?.revenue ?? 0), accent: 'cyan' },
    { label: 'Pending approvals', value: props.summary?.pending_actions?.conversions ?? 0, accent: 'amber' },
]);
</script>

<template>
    <Head title="Click Track" />
    <AuthenticatedLayout>
        <PageHeader
            title="Click Track"
            description="Affiliate link tracking, click logs, and conversion reporting — separate from ping-tree LMS routing."
        >
            <template #actions>
                <AppButton :href="route('click-track.links.index')" variant="secondary">Manage links</AppButton>
                <AppButton :href="route('click-track.reports.index')">Reports</AppButton>
            </template>
        </PageHeader>

        <div v-if="!entitlement?.entitled" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
            Click Track is not enabled on your plan.
            <Link :href="route('click-track.settings.edit')" class="ml-1 font-semibold underline">Enable in settings</Link>
        </div>

        <CompactStatStrip :items="strip" :columns="4" class="mb-6" />

        <div v-if="pendingQueue?.count" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
            <span class="font-semibold">{{ pendingQueue.count }} conversion(s)</span> awaiting approval.
            <Link :href="route('click-track.conversions.index', { status: 'pending' })" class="ml-1 font-semibold underline">Review queue</Link>
        </div>

        <Panel v-if="capAlerts?.length" title="Cap alerts" class="mb-6">
            <div v-for="alert in capAlerts" :key="alert.link_id" class="border-b border-slate-100 py-2 text-sm last:border-0 dark:border-slate-800">
                <p class="font-semibold text-slate-900 dark:text-white">{{ alert.link_name }}</p>
                <p class="text-xs text-amber-700 dark:text-amber-300">
                    <span v-if="alert.click_cap_reached">Click cap reached</span>
                    <span v-if="alert.click_cap_reached && alert.conversion_cap_reached"> · </span>
                    <span v-if="alert.conversion_cap_reached">Conversion cap reached</span>
                </p>
            </div>
        </Panel>

        <div class="mb-6 grid gap-4 md:grid-cols-4">
            <Panel title="Period summary">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Impressions</dt><dd class="font-semibold">{{ summary?.impressions ?? 0 }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Clicks</dt><dd class="font-semibold">{{ summary?.clicks ?? 0 }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Unique clicks</dt><dd class="font-semibold">{{ summary?.unique_clicks ?? 0 }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">CR %</dt><dd class="font-semibold">{{ summary?.cr ?? '—' }}%</dd></div>
                </dl>
            </Panel>
            <Panel title="Financials">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Revenue</dt><dd class="font-semibold">{{ formatMoney(summary?.revenue ?? 0) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Affiliate payout</dt><dd class="font-semibold">{{ formatMoney(summary?.payout ?? 0) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Margin</dt><dd class="font-semibold">{{ formatMoney(summary?.margin ?? 0) }}</dd></div>
                </dl>
            </Panel>
            <Panel title="Conversions">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Approved</dt><dd class="font-semibold text-emerald-600">{{ summary?.approved ?? 0 }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Pending</dt><dd class="font-semibold text-amber-600">{{ summary?.pending ?? 0 }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Rejected</dt><dd class="font-semibold text-red-600">{{ summary?.rejected ?? 0 }}</dd></div>
                </dl>
            </Panel>
            <Panel title="Usage">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Clicks used</dt><dd class="font-semibold">{{ entitlement?.clicks_used ?? 0 }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Cap</dt><dd class="font-semibold">{{ entitlement?.clicks_cap ?? 'Unlimited' }}</dd></div>
                </dl>
            </Panel>
        </div>

        <Panel title="Top offers">
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <div v-for="link in topLinks" :key="link.id" class="flex items-center justify-between py-3 text-sm">
                    <div>
                        <p class="font-semibold text-slate-900 dark:text-white">{{ link.name }}</p>
                        <p class="text-xs text-slate-500">{{ link.campaign?.name }} · {{ link.supplier?.name ?? 'All affiliates' }}</p>
                    </div>
                    <div class="text-right text-xs text-slate-500">
                        <p>{{ link.clicks_count }} clicks</p>
                        <p>{{ link.conversions_count }} conversions</p>
                    </div>
                </div>
                <p v-if="!topLinks?.length" class="py-4 text-sm text-slate-500">No tracking links yet.</p>
            </div>
        </Panel>
    </AuthenticatedLayout>
</template>
