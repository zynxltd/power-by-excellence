<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ call: Object });
</script>

<template>
    <Head :title="`Call ${call.uuid}`" />
    <AuthenticatedLayout>
        <PageHeader :title="call.caller_number || 'Call detail'" :description="call.uuid">
            <template #actions>
                <AppButton variant="secondary" :href="route('call-logic.calls.index')">Back to calls</AppButton>
            </template>
        </PageHeader>

        <div class="grid gap-4 lg:grid-cols-2">
            <Panel title="Session">
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <dt class="text-slate-500">Status</dt><dd><StatusBadge :status="call.status" /></dd>
                    <dt class="text-slate-500">Campaign</dt><dd>{{ call.campaign?.name || '—' }}</dd>
                    <dt class="text-slate-500">Buyer</dt><dd>{{ call.sold_to_buyer?.name || '—' }}</dd>
                    <dt class="text-slate-500">Duration</dt><dd>{{ call.duration_seconds }}s (billable: {{ call.billable_seconds }}s)</dd>
                    <dt class="text-slate-500">Revenue</dt><dd>{{ call.revenue ?? '—' }}</dd>
                    <dt class="text-slate-500">Billing</dt>
                    <dd>
                        <span v-if="call.billed_at" class="text-emerald-600">Billed {{ call.billed_amount }} · <FormattedDate :value="call.billed_at" /></span>
                        <span v-else-if="call.sold_to_buyer_id" class="text-amber-600">Pending / not billed</span>
                        <span v-else>—</span>
                    </dd>
                    <dt class="text-slate-500">Disposition</dt><dd>{{ call.disposition || '—' }}</dd>
                    <dt class="text-slate-500">Tracking #</dt><dd>{{ call.tracking_number?.phone_number || '—' }}</dd>
                    <dt class="text-slate-500">Received</dt><dd><FormattedDate :value="call.created_at" /></dd>
                </dl>
            </Panel>

            <Panel title="IVR data">
                <pre class="overflow-auto text-xs">{{ JSON.stringify(call.ivr_data || {}, null, 2) }}</pre>
            </Panel>

            <Panel title="Events" class="lg:col-span-2">
                <ul class="divide-y divide-slate-200 dark:divide-slate-700">
                    <li v-for="event in call.events" :key="event.id" class="py-2 text-sm">
                        <span class="font-medium">{{ event.event_type }}</span>
                        <span class="text-slate-500"> — {{ event.message }}</span>
                        <span class="float-right text-xs text-slate-400"><FormattedDate :value="event.created_at" /></span>
                    </li>
                </ul>
            </Panel>

            <Panel v-if="call.delivery_logs?.length" title="Delivery logs" class="lg:col-span-2">
                <ul class="divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                    <li v-for="log in call.delivery_logs" :key="log.id" class="py-2">
                        {{ log.delivery?.name || log.delivery_id }} — {{ log.status }}
                        <span v-if="log.revenue"> (£{{ log.revenue }})</span>
                    </li>
                </ul>
            </Panel>

            <Panel v-if="call.recordings?.length" title="Recordings" class="lg:col-span-2">
                <ul class="space-y-3 text-sm">
                    <li v-for="rec in call.recordings" :key="rec.id" class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <p class="text-xs text-slate-500">
                            {{ rec.provider_recording_sid || 'Recording' }} · {{ rec.duration_seconds }}s
                            <span v-if="rec.retention_expires_at"> · expires <FormattedDate :value="rec.retention_expires_at" /></span>
                        </p>
                        <audio v-if="rec.playback_url" :src="rec.playback_url" controls preload="none" class="mt-2 w-full max-w-md" />
                        <p v-else class="mt-1 text-xs text-amber-600">Processing or expired…</p>
                        <a v-if="rec.playback_url" :href="rec.playback_url" target="_blank" rel="noopener" class="mt-1 inline-block text-xs text-indigo-600 hover:underline">Open recording</a>
                    </li>
                </ul>
            </Panel>

            <Panel v-if="call.lead" title="Hybrid lead" class="lg:col-span-2">
                <Link :href="route('leads.show', call.lead.id)" class="text-indigo-600 hover:underline">View lead {{ call.lead.uuid }}</Link>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
