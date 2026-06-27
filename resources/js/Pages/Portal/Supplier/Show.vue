<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    lead: Object,
    currency: { type: String, default: 'GBP' },
});

const { formatMoney } = useMoneyFormat(props.currency);

const conversionLabel = computed(() => {
    const event = props.lead.conversion_event;
    if (!event) return null;
    return event.replace(/^lead\./, '').replace(/_/g, ' ');
});

const leadName = computed(() => {
    const name = `${props.lead.field_data?.firstname ?? ''} ${props.lead.field_data?.lastname ?? ''}`.trim();
    return name || 'Lead detail';
});
</script>

<template>
    <Head :title="`Lead ${lead.uuid?.slice(0, 8)}`" />
    <AuthenticatedLayout>
        <PageHeader :title="leadName" :description="lead.uuid">
            <template #actions>
                <AppButton :href="route('portal.supplier.leads')" variant="secondary">Back to leads</AppButton>
            </template>
        </PageHeader>

        <CompactStatStrip
            class="mb-6"
            :columns="4"
            :items="[
                { label: 'Status', value: lead.status, accent: 'indigo' },
                { label: 'Payout', value: formatMoney(lead.financials?.payout ?? 0), accent: 'emerald' },
                { label: 'SID', value: lead.sid || '—', accent: 'cyan' },
                { label: 'Received', value: lead.received_at ? new Date(lead.received_at).toLocaleDateString() : '—', accent: 'amber' },
            ]"
        />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <Panel title="Lead data">
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div v-for="field in lead.fields" :key="field.key">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ field.key }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ field.value || '—' }}</dd>
                        </div>
                    </dl>
                </Panel>

                <Panel v-if="lead.reject_reason" title="Reject reason">
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ lead.reject_reason }}</p>
                </Panel>
            </div>

            <div class="space-y-6">
                <Panel title="Tracking">
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ lead.campaign?.name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Source (SID)</dt>
                            <dd class="mt-1 font-mono text-sm text-indigo-600 dark:text-indigo-400">{{ lead.sid || '—' }}</dd>
                        </div>
                        <div v-if="lead.ssid">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Sub-affiliate (SSID)</dt>
                            <dd class="mt-1 font-mono text-sm text-slate-900 dark:text-white">{{ lead.ssid }}</dd>
                        </div>
                        <div v-if="lead.source_record?.name">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Source name</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ lead.source_record.name }}</dd>
                        </div>
                        <div v-if="lead.ingest_source">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Ingest source</dt>
                            <dd class="mt-1 font-mono text-xs text-slate-600 dark:text-slate-400">{{ lead.ingest_source }}</dd>
                        </div>
                    </dl>
                </Panel>

                <Panel title="Outcome">
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Status</dt>
                            <dd class="mt-1"><StatusBadge :status="lead.status" /></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Payout</dt>
                            <dd class="mt-1 text-lg font-semibold text-emerald-600 dark:text-emerald-400">{{ formatMoney(lead.financials?.payout ?? 0) }}</dd>
                        </div>
                        <div v-if="lead.distributed_at">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Sold at</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white"><FormattedDate :value="lead.distributed_at" /></dd>
                        </div>
                        <div v-if="conversionLabel">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Conversion event</dt>
                            <dd class="mt-1 text-sm capitalize text-slate-900 dark:text-white">{{ conversionLabel }}</dd>
                        </div>
                    </dl>
                </Panel>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
