<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    entitlement: Object,
    tab: String,
    summary: Object,
    performance: Array,
    conversionStatus: Array,
    campaigns: Array,
    suppliers: Array,
    filters: Object,
    groupOptions: Array,
});

const { formatMoney } = useMoneyFormat();
const strip = computed(() => [
    { label: 'Clicks', value: props.summary?.clicks ?? 0, accent: 'indigo' },
    { label: 'Conversions', value: props.summary?.approved ?? 0, accent: 'emerald' },
    { label: 'Revenue', value: formatMoney(props.summary?.revenue ?? 0), accent: 'cyan' },
    { label: 'CR %', value: props.summary?.cr != null ? `${props.summary.cr}%` : '—', accent: 'violet' },
]);

const setTab = (tab) => router.get(route('click-track.reports.index'), { ...props.filters, tab }, { preserveState: true });
const setGroup = (group_by) => router.get(route('click-track.reports.index'), { ...props.filters, group_by }, { preserveState: true });
</script>

<template>
    <Head title="Click Track Reports" />
    <AuthenticatedLayout>
        <PageHeader title="Click Track reports" description="Performance, conversion status, and grouped analytics." />
        <CompactStatStrip :items="strip" :columns="4" class="mb-6" />

        <div class="mb-4 flex flex-wrap gap-2 border-b border-slate-200 dark:border-slate-800">
            <button type="button" :class="['px-4 py-2 text-sm font-semibold', tab === 'performance' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500']" @click="setTab('performance')">Performance</button>
            <button type="button" :class="['px-4 py-2 text-sm font-semibold', tab === 'status' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500']" @click="setTab('status')">Conversion status</button>
        </div>

        <Panel v-if="tab !== 'status'" title="Performance report">
            <div class="mb-4 flex flex-wrap gap-2">
                <button v-for="g in groupOptions" :key="g.value" type="button" :class="['rounded-lg border px-3 py-1 text-xs font-semibold', filters.group_by === g.value ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-slate-200']" @click="setGroup(g.value)">{{ g.label }}</button>
            </div>
            <table class="min-w-full text-sm">
                <thead><tr class="border-b text-left text-xs uppercase text-slate-500"><th class="py-2">Label</th><th class="py-2">Clicks</th><th class="py-2">Unique</th><th class="py-2">Conversions</th><th class="py-2">CR %</th><th class="py-2">Revenue</th><th class="py-2">Payout</th></tr></thead>
                <tbody>
                    <tr v-for="row in performance" :key="row.label" class="border-b border-slate-100 dark:border-slate-800">
                        <td class="py-2 font-medium">{{ row.label }}</td>
                        <td class="py-2">{{ row.clicks }}</td>
                        <td class="py-2">{{ row.unique_clicks }}</td>
                        <td class="py-2">{{ row.conversions }}</td>
                        <td class="py-2">{{ row.cr }}%</td>
                        <td class="py-2">{{ formatMoney(row.revenue) }}</td>
                        <td class="py-2">{{ formatMoney(row.payout) }}</td>
                    </tr>
                </tbody>
            </table>
        </Panel>

        <Panel v-else title="Conversion status by date">
            <table class="min-w-full text-sm">
                <thead><tr class="border-b text-left text-xs uppercase text-slate-500"><th class="py-2">Date</th><th class="py-2">Gross</th><th class="py-2">Pending</th><th class="py-2">Approved</th><th class="py-2">Rejected</th><th class="py-2">Payout</th><th class="py-2">Revenue</th></tr></thead>
                <tbody>
                    <tr v-for="row in conversionStatus" :key="row.date" class="border-b border-slate-100 dark:border-slate-800">
                        <td class="py-2">{{ row.date }}</td>
                        <td class="py-2">{{ row.gross }}</td>
                        <td class="py-2">{{ row.pending }}</td>
                        <td class="py-2">{{ row.approved }} ({{ row.approved_pct }}%)</td>
                        <td class="py-2">{{ row.rejected }} ({{ row.rejected_pct }}%)</td>
                        <td class="py-2">{{ formatMoney(row.payout) }}</td>
                        <td class="py-2">{{ formatMoney(row.revenue) }}</td>
                    </tr>
                </tbody>
            </table>
        </Panel>
    </AuthenticatedLayout>
</template>
