<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import BarChart from '@/Components/UI/BarChart.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import BuyerAccountPanel from '@/Components/Portal/BuyerAccountPanel.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { useBuyerPortalI18n } from '@/Composables/useBuyerPortalI18n';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    buyer: Object,
    stats: Object,
    account: Object,
    recentLeads: Array,
    recentActivity: Array,
    charts: Object,
    currency: { type: String, default: 'GBP' },
});

const { t } = useBuyerPortalI18n();
const { formatMoney, currency: displayCurrency } = useMoneyFormat(props.currency);

const buyerPortalStrip = computed(() => [
    { label: t('dashboard.credit'), value: formatMoney(props.buyer.credit_balance), accent: props.account?.is_low_credit ? 'rose' : 'emerald' },
    { label: t('dashboard.leads_today'), value: props.stats.leads_today, accent: 'indigo' },
    { label: t('dashboard.spend_7d'), value: formatMoney(props.stats.spend_7d), accent: 'cyan' },
    { label: t('dashboard.conversion'), value: props.stats.conversion_rate != null ? `${props.stats.conversion_rate}%` : '—', accent: 'violet' },
]);

const pendingReturnsMessage = computed(() => {
    const count = props.stats.pending_returns ?? 0;

    return t(count === 1 ? 'dashboard.pending_returns_one' : 'dashboard.pending_returns_many', { count });
});

const activityLabel = (item) => {
    if (item.type === 'return') {
        return t('dashboard.activity_return', { status: item.status });
    }

    return t('dashboard.activity_feedback', { status: item.status }) + (item.converted ? t('dashboard.activity_converted') : '');
};

const pendingReturnsUrl = computed(() => route('portal.buyer.leads', { return: 'pending' }));
</script>

<template>
    <Head :title="t('portal_title')" />
    <AuthenticatedLayout>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">{{ t('dashboard.title') }}</h1>
                <p class="text-xs text-slate-500">{{ t('dashboard.subtitle', { name: buyer.name }) }}</p>
            </div>
            <div class="flex gap-2">
                <AppButton :href="route('portal.buyer.leads.download')" variant="secondary" external>{{ t('common.download_csv') }}</AppButton>
                <AppButton :href="route('portal.buyer.leads')">{{ t('common.view_all_leads') }}</AppButton>
            </div>
        </div>

        <div
            v-if="stats.pending_returns > 0"
            class="mb-6 flex flex-wrap items-center gap-x-1 gap-y-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200"
        >
            <span>{{ pendingReturnsMessage }}</span>
            <Link
                :href="pendingReturnsUrl"
                class="font-semibold underline underline-offset-2 hover:text-amber-950 dark:hover:text-amber-100"
            >
                {{ t('common.view_leads') }}
            </Link>
        </div>

        <CompactStatStrip :items="buyerPortalStrip" :columns="4" class="mb-6" />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="grid gap-6 lg:grid-cols-2">
                    <Panel :title="t('dashboard.leads_chart')">
                        <BarChart :labels="charts.labels" :datasets="[{ label: t('dashboard.chart_leads'), data: charts.leads, color: '#6366f1' }]" />
                    </Panel>
                    <Panel :title="t('dashboard.spend_chart', { currency: displayCurrency })">
                        <BarChart
                            :labels="charts.labels"
                            :datasets="[{ label: t('dashboard.chart_spend', { currency: displayCurrency }), data: charts.spend, color: '#10b981' }]"
                            :value-formatter="(v) => formatMoney(v)"
                        />
                    </Panel>
                </div>

                <Panel :title="t('dashboard.recent_leads')" :padding="false">
                    <DataTable :empty="!recentLeads?.length">
                        <template #head>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('common.lead') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('common.name') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('common.feedback') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('common.cost') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500" />
                        </template>
                        <tr v-for="lead in recentLeads" :key="lead.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-6 py-4 font-mono text-xs text-slate-500">{{ lead.uuid?.slice(0, 12) }}…</td>
                            <td class="px-6 py-4 text-sm text-slate-900 dark:text-white">
                                {{ lead.field_data?.firstname }} {{ lead.field_data?.lastname }}
                            </td>
                            <td class="px-6 py-4 text-xs capitalize text-slate-500">
                                {{ lead.feedback?.status ?? '—' }}
                            </td>
                            <td class="px-6 py-4 font-medium text-emerald-600 dark:text-emerald-400">{{ formatMoney(lead.financials?.revenue ?? 0) }}</td>
                            <td class="px-6 py-4 text-right">
                                <Link :href="route('portal.buyer.leads.show', lead.uuid)" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">{{ t('common.view') }}</Link>
                            </td>
                        </tr>
                    </DataTable>
                </Panel>
            </div>

            <div class="space-y-6">
                <BuyerAccountPanel :account="account" :currency="currency" />

                <Panel :title="t('dashboard.recent_activity')">
                    <div v-if="!recentActivity?.length" class="py-4 text-sm text-slate-500">{{ t('common.no_activity') }}</div>
                    <ul v-else class="space-y-3">
                        <li v-for="(item, index) in recentActivity" :key="index" class="border-b border-slate-100 pb-3 last:border-0 dark:border-slate-800">
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ activityLabel(item) }}</p>
                            <p class="mt-0.5 font-mono text-xs text-slate-500">{{ item.lead_uuid?.slice(0, 12) }}…</p>
                            <FormattedDate :value="item.at" class="mt-1 text-xs text-slate-400" />
                        </li>
                    </ul>
                </Panel>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
