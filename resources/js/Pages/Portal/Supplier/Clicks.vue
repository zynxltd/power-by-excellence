<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head } from '@inertiajs/vue3';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({ supplier: Object, account: Object, stats: Object, currency: String });

const { formatMoney } = useMoneyFormat();
const capForLink = (linkId) => props.stats?.link_caps?.find((c) => c.link_id === linkId);
</script>

<template>
    <Head title="Click stats" />
    <AuthenticatedLayout>
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Click stats</h1>
                <p class="text-xs text-slate-500">Tracking links, earnings, and payouts for {{ supplier.name }}</p>
            </div>
            <AppButton :href="route('portal.supplier.clicks.export')" variant="secondary" external>Export payouts CSV</AppButton>
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-5">
            <Panel title="Today"><p class="text-2xl font-bold">{{ stats.clicks_today }}</p><p class="text-xs text-slate-500">clicks</p></Panel>
            <Panel title="7 days"><p class="text-2xl font-bold">{{ stats.conversions_7d }}</p><p class="text-xs text-slate-500">approved conversions</p></Panel>
            <Panel title="Pending earnings"><p class="text-2xl font-bold text-amber-600">{{ formatMoney(stats.pending_earnings ?? 0) }}</p><p class="text-xs text-slate-500">{{ stats.pending_conversions ?? 0 }} awaiting approval</p></Panel>
            <Panel title="Approved earnings"><p class="text-2xl font-bold text-emerald-600">{{ formatMoney(stats.approved_earnings ?? 0) }}</p><p class="text-xs text-slate-500">ledger total</p></Panel>
            <Panel title="Links"><p class="text-2xl font-bold">{{ stats.links?.length ?? 0 }}</p><p class="text-xs text-slate-500">tracking links</p></Panel>
        </div>

        <Panel v-if="stats.cap_alerts?.length" title="Cap alerts" class="mb-6">
            <div v-for="alert in stats.cap_alerts" :key="alert.link_id" class="border-b border-slate-100 py-2 text-sm last:border-0 dark:border-slate-800">
                <p class="font-semibold">{{ alert.link_name }}</p>
                <p class="text-xs text-amber-700 dark:text-amber-300">
                    <span v-if="alert.click_cap_reached">Click cap reached</span>
                    <span v-if="alert.conversion_cap_reached">Conversion cap reached</span>
                </p>
            </div>
        </Panel>

        <Panel title="Your tracking links" class="mb-6">
            <div v-for="link in stats.links" :key="link.id" class="border-b border-slate-100 py-3 text-sm dark:border-slate-800">
                <p class="font-semibold">{{ link.name }}</p>
                <p class="font-mono text-xs text-indigo-600">{{ route('click.redirect', link.token) }}</p>
                <p class="text-xs text-slate-500">{{ link.clicks_count }} clicks · {{ link.conversions_count }} conversions · payout {{ formatMoney(link.payout_amount ?? 0) }}<span v-if="link.revenue_share_pct"> · {{ link.revenue_share_pct }}% share</span></p>
            </div>
            <p v-if="!stats.links?.length" class="text-sm text-slate-500">No links assigned to your affiliate account yet.</p>
        </Panel>

        <Panel title="Recent payout ledger" class="mb-6">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-slate-500"><th class="py-2">Date</th><th class="py-2">Offer</th><th class="py-2">Status</th><th class="py-2">Payout</th></tr></thead>
                <tbody>
                    <tr v-for="entry in stats.recent_payouts" :key="entry.id" class="border-t border-slate-100 dark:border-slate-800">
                        <td class="py-2"><FormattedDate :date="entry.created_at" /></td>
                        <td class="py-2">{{ entry.tracking_conversion?.tracking_link?.name ?? '—' }}</td>
                        <td class="py-2 capitalize">{{ entry.status }}</td>
                        <td class="py-2">{{ formatMoney(entry.amount) }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!stats.recent_payouts?.length" class="text-sm text-slate-500">No payout entries yet.</p>
        </Panel>

        <Panel title="Recent clicks">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-slate-500"><th class="py-2">Time</th><th class="py-2">Offer</th><th class="py-2">Sub1</th><th class="py-2">Unique</th></tr></thead>
                <tbody>
                    <tr v-for="click in stats.recent_clicks" :key="click.id" class="border-t border-slate-100 dark:border-slate-800">
                        <td class="py-2"><FormattedDate :date="click.clicked_at" /></td>
                        <td class="py-2">{{ click.tracking_link?.name }}</td>
                        <td class="py-2">{{ click.sub1 ?? '—' }}</td>
                        <td class="py-2">{{ click.is_unique ? 'Yes' : 'No' }}</td>
                    </tr>
                </tbody>
            </table>
        </Panel>
    </AuthenticatedLayout>
</template>
