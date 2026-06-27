<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    lead: Object,
    currency: { type: String, default: 'GBP' },
});

const page = usePage();
const { formatMoney } = useMoneyFormat(props.currency);

const returnPresets = [
    'Wrong phone number',
    'Duplicate lead',
    'Invalid contact details',
    'Customer did not request',
    'Unable to reach',
];

const feedbackForm = useForm({
    lead_uuid: props.lead.uuid,
    status: props.lead.feedback?.status ?? 'contacted',
    converted: props.lead.feedback?.converted ?? false,
    notes: props.lead.feedback?.notes ?? '',
});

const returnForm = useForm({
    lead_uuid: props.lead.uuid,
    reason: '',
});

const submitFeedback = () => {
    feedbackForm.lead_uuid = props.lead.uuid;
    feedbackForm.post(route('portal.buyer.feedback'), { preserveScroll: true });
};

const submitReturn = () => {
    returnForm.lead_uuid = props.lead.uuid;
    returnForm.post(route('portal.buyer.returns'), { preserveScroll: true, onSuccess: () => returnForm.reset('reason') });
};

const applyReturnPreset = (text) => {
    returnForm.reason = text;
};

const conversionLabel = computed(() => {
    const event = props.lead.conversion_event;
    if (!event) return null;
    return event.replace(/^lead\./, '').replace(/_/g, ' ');
});
</script>

<template>
    <Head :title="`Lead ${lead.uuid?.slice(0, 8)}`" />
    <AuthenticatedLayout>
        <PageHeader
            :title="`${lead.field_data?.firstname ?? ''} ${lead.field_data?.lastname ?? ''}`.trim() || 'Lead detail'"
            :description="lead.uuid"
        >
            <template #actions>
                <AppButton :href="route('portal.buyer.leads')" variant="secondary">Back to leads</AppButton>
            </template>
        </PageHeader>

        <div
            v-if="page.props.flash?.success"
            class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-200"
        >
            {{ page.props.flash.success }}
        </div>

        <CompactStatStrip
            class="mb-6"
            :columns="4"
            :items="[
                { label: 'Status', value: lead.status, accent: 'indigo' },
                { label: 'Revenue', value: formatMoney(lead.financials?.revenue ?? 0), accent: 'emerald' },
                { label: 'Campaign', value: lead.campaign?.reference ?? '-', accent: 'cyan' },
                { label: 'Purchased', value: lead.distributed_at ? new Date(lead.distributed_at).toLocaleDateString() : '-', accent: 'amber' },
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

                <Panel v-if="lead.return_history?.length" title="Return history">
                    <ul class="space-y-3">
                        <li
                            v-for="(item, index) in lead.return_history"
                            :key="index"
                            class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <StatusBadge :status="item.status" />
                                <FormattedDate :value="item.submitted_at" class="text-xs text-slate-500" />
                            </div>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ item.reason }}</p>
                        </li>
                    </ul>
                </Panel>
            </div>

            <div class="space-y-6">
                <Panel title="Conversion status">
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Your feedback</dt>
                            <dd class="mt-1 capitalize text-slate-900 dark:text-white">
                                {{ lead.feedback?.status ?? 'Not reported yet' }}
                                <span v-if="lead.feedback?.converted" class="ml-1 text-emerald-600">· converted</span>
                            </dd>
                        </div>
                        <div v-if="conversionLabel">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Platform event</dt>
                            <dd class="mt-1 capitalize text-slate-900 dark:text-white">{{ conversionLabel }}</dd>
                        </div>
                        <div v-if="lead.return_request">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Return request</dt>
                            <dd class="mt-1"><StatusBadge :status="lead.return_request.status" /></dd>
                        </div>
                    </dl>
                </Panel>

                <Panel title="Report outcome">
                    <p class="mb-4 text-xs text-slate-500">Updates conversion tracking. Does not change lead price or credit — returns require admin approval.</p>
                    <form class="space-y-4" @submit.prevent="submitFeedback">
                        <FormErrorSummary :errors="feedbackForm.errors" />
                        <div>
                            <InputLabel value="Status" />
                            <select v-model="feedbackForm.status" class="form-select mt-1 w-full">
                                <option value="contacted">Contacted</option>
                                <option value="called">Called</option>
                                <option value="callback">Callback scheduled</option>
                                <option value="converted">Converted</option>
                                <option value="funded">Funded</option>
                                <option value="invalid">Invalid</option>
                            </select>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                            <input v-model="feedbackForm.converted" type="checkbox" class="rounded border-slate-300 text-indigo-600" />
                            Mark as converted
                        </label>
                        <div>
                            <InputLabel value="Notes" />
                            <textarea v-model="feedbackForm.notes" class="form-input mt-1 w-full" rows="3" placeholder="Optional notes for your account manager" />
                        </div>
                        <PrimaryButton :disabled="feedbackForm.processing">Save feedback</PrimaryButton>
                    </form>
                </Panel>

                <Panel title="Request return">
                    <p class="mb-4 text-xs text-slate-500">Return requests are reviewed by your platform administrator. Credit is not refunded automatically.</p>
                    <form v-if="lead.can_request_return" class="space-y-4" @submit.prevent="submitReturn">
                        <FormErrorSummary :errors="returnForm.errors" />
                        <div>
                            <InputLabel value="Quick reasons" />
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="preset in returnPresets"
                                    :key="preset"
                                    type="button"
                                    class="rounded-full border border-slate-200 px-3 py-1 text-xs font-medium text-slate-600 transition hover:border-amber-300 hover:bg-amber-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-amber-950/30"
                                    @click="applyReturnPreset(preset)"
                                >
                                    {{ preset }}
                                </button>
                            </div>
                        </div>
                        <div>
                            <InputLabel value="Reason" />
                            <textarea v-model="returnForm.reason" class="form-input mt-1 w-full" rows="4" placeholder="Explain why this lead should be returned" required />
                            <InputError class="mt-1" :message="returnForm.errors.reason" />
                        </div>
                        <PrimaryButton :disabled="returnForm.processing">Submit return request</PrimaryButton>
                    </form>
                    <p v-else class="text-sm text-amber-700 dark:text-amber-300">A return is already pending review for this lead.</p>
                </Panel>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
