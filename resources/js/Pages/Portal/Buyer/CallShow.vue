<script setup>
import BuyerPortalLayout from '@/Layouts/BuyerPortalLayout.vue';
import Panel from '@/Components/UI/Panel.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ call: Object, buyer: Object });
</script>

<template>
    <Head :title="`Call ${call.uuid}`" />
    <BuyerPortalLayout>
        <div class="mb-4">
            <AppButton variant="secondary" :href="route('portal.buyer.calls')">Back to calls</AppButton>
        </div>

        <Panel title="Call detail">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <dt class="text-slate-500">Caller</dt><dd>{{ call.caller_number || '—' }}</dd>
                <dt class="text-slate-500">Campaign</dt><dd>{{ call.campaign?.name || '—' }}</dd>
                <dt class="text-slate-500">Status</dt><dd><StatusBadge :status="call.status" /></dd>
                <dt class="text-slate-500">Duration</dt><dd>{{ call.duration_seconds }}s</dd>
                <dt class="text-slate-500">Disposition</dt><dd>{{ call.disposition || '—' }}</dd>
                <dt class="text-slate-500">Received</dt><dd><FormattedDate :value="call.created_at" /></dd>
            </dl>
        </Panel>

        <Panel v-if="call.recordings?.length" title="Recordings" class="mt-4">
            <ul class="text-sm">
                <li v-for="rec in call.recordings" :key="rec.id">
                    <a v-if="rec.url" :href="rec.url" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">Recording</a>
                    <span v-else>Processing…</span>
                    ({{ rec.duration_seconds }}s)
                </li>
            </ul>
        </Panel>
    </BuyerPortalLayout>
</template>
