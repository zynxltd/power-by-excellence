<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    summary: Object,
    byCampaign: Array,
    trafficFlow: Array,
    waves: Array,
});

const stats = computed(() => [
    { label: 'Total calls', value: props.summary.total_calls },
    { label: 'Connect rate', value: `${props.summary.connect_rate}%` },
    { label: 'Sold', value: props.summary.sold_calls },
    { label: 'Revenue', value: props.summary.revenue },
    { label: 'Avg duration', value: `${props.summary.avg_duration_seconds}s` },
]);
</script>

<template>
    <Head title="Call Logic - Reports" />
    <AuthenticatedLayout>
        <PageHeader title="Call Logic analytics" description="Connect rates, Traffic Flow, and Waves suggestions." />
        <CompactStatStrip :stats="stats" class="mb-4" />

        <div class="grid gap-4 lg:grid-cols-2">
            <Panel title="Traffic Flow (buyer first-look %)">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-slate-500"><th>Buyer</th><th>Pings</th><th>Accept %</th><th>First look %</th></tr></thead>
                    <tbody>
                        <tr v-for="row in trafficFlow" :key="row.buyer_id" class="border-t border-slate-100 dark:border-slate-800">
                            <td class="py-2">{{ row.buyer_name }}</td>
                            <td>{{ row.total_pings }}</td>
                            <td>{{ row.accept_rate }}%</td>
                            <td>{{ row.first_look_pct }}%</td>
                        </tr>
                    </tbody>
                </table>
            </Panel>

            <Panel title="Waves suggestions">
                <ul v-if="waves.length" class="space-y-2 text-sm">
                    <li v-for="(w, i) in waves" :key="i" class="rounded border border-slate-200 p-3 dark:border-slate-700">
                        <span class="font-medium capitalize">{{ w.type.replace('_', ' ') }}</span>
                        <p class="text-slate-600 dark:text-slate-400">{{ w.message }}</p>
                    </li>
                </ul>
                <p v-else class="text-sm text-slate-500">No suggestions yet — need more call volume.</p>
            </Panel>

            <Panel title="By campaign" class="lg:col-span-2">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-slate-500"><th>Campaign</th><th>Calls</th><th>Sold</th><th>Revenue</th></tr></thead>
                    <tbody>
                        <tr v-for="row in byCampaign" :key="row.campaign_id" class="border-t border-slate-100 dark:border-slate-800">
                            <td class="py-2">{{ row.campaign_name || '—' }}</td>
                            <td>{{ row.total }}</td>
                            <td>{{ row.sold }}</td>
                            <td>{{ row.revenue }}</td>
                        </tr>
                    </tbody>
                </table>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
