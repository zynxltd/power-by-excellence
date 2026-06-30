<script setup>
import BuyerPortalLayout from '@/Layouts/BuyerPortalLayout.vue';
import Panel from '@/Components/UI/Panel.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    call: Object,
    buyer: Object,
    canSubmitReturn: { type: Boolean, default: false },
    returnWindowDays: { type: Number, default: 7 },
});

const returnForm = useForm({ reason: '' });

const submitReturn = () => {
    returnForm.post(route('portal.buyer.calls.return', props.call.uuid), {
        preserveScroll: true,
        onSuccess: () => returnForm.reset(),
    });
};
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
                <dt class="text-slate-500">Billed</dt>
                <dd>
                    <span v-if="call.refunded_at" class="text-emerald-600">Refunded</span>
                    <span v-else-if="call.billed_at">{{ call.billed_amount }} · <FormattedDate :value="call.billed_at" /></span>
                    <span v-else>—</span>
                </dd>
                <dt class="text-slate-500">Disposition</dt><dd>{{ call.disposition || '—' }}</dd>
                <dt class="text-slate-500">Received</dt><dd><FormattedDate :value="call.created_at" /></dd>
            </dl>
        </Panel>

        <Panel v-if="call.call_return" title="Return request" class="mt-4">
            <p class="text-sm"><span class="font-medium capitalize">{{ call.call_return.status }}</span> — {{ call.call_return.reason }}</p>
            <p v-if="call.call_return.resolved_at" class="mt-1 text-xs text-slate-500">Resolved <FormattedDate :value="call.call_return.resolved_at" /></p>
        </Panel>

        <Panel v-else-if="canSubmitReturn" title="Dispute this call" class="mt-4">
            <p class="mb-3 text-sm text-slate-600 dark:text-slate-400">Submit a return within {{ returnWindowDays }} days of billing. Approved returns credit your account.</p>
            <form class="space-y-3" @submit.prevent="submitReturn">
                <div>
                    <InputLabel for="reason" value="Reason" />
                    <textarea id="reason" v-model="returnForm.reason" rows="3" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800" required />
                    <InputError class="mt-1" :message="returnForm.errors.reason" />
                </div>
                <AppButton type="submit" :disabled="returnForm.processing">Submit return</AppButton>
            </form>
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
