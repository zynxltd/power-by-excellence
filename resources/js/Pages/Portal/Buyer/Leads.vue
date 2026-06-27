<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import BuyerAccountPanel from '@/Components/Portal/BuyerAccountPanel.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    leads: Object,
    filters: Object,
    campaigns: Array,
    statuses: Array,
    account: Object,
    recentActivity: Array,
    actionLeads: { type: Array, default: () => [] },
    currency: { type: String, default: 'GBP' },
});

const page = usePage();
const localFilters = ref({ ...props.filters });
const applyFilters = () => router.get(route('portal.buyer.leads'), localFilters.value, { preserveState: true, replace: true });
const clearFilters = () => { localFilters.value = {}; applyFilters(); };
watch(() => props.filters, (f) => { localFilters.value = { ...f }; });

const { formatMoney } = useMoneyFormat(props.currency);

const quickActionsEl = ref(null);
const selectedLeadUuid = ref('');

const feedbackForm = useForm({ lead_uuid: '', status: 'contacted', converted: false, notes: '' });
const returnForm = useForm({ lead_uuid: '', reason: '' });

const returnPresets = [
    'Wrong phone number',
    'Duplicate lead',
    'Invalid contact details',
    'Customer did not request',
    'Unable to reach',
];

const selectedLeadMeta = computed(() => (
    props.actionLeads?.find((lead) => lead.uuid === selectedLeadUuid.value)
    ?? props.leads.data?.find((lead) => lead.uuid === selectedLeadUuid.value)
));

const canReturnSelectedLead = computed(() => (
    selectedLeadUuid.value
    && !selectedLeadMeta.value?.return_pending
));

const leadOptionLabel = (lead) => {
    const parts = [lead.label];
    if (lead.email) parts.push(lead.email);
    if (lead.campaign) parts.push(lead.campaign);
    return parts.join(' · ');
};

const syncFormsToSelection = (lead) => {
    if (!lead) {
        feedbackForm.lead_uuid = '';
        returnForm.lead_uuid = '';
        return;
    }

    feedbackForm.lead_uuid = lead.uuid;
    returnForm.lead_uuid = lead.uuid;

    if (lead.feedback) {
        feedbackForm.status = lead.feedback.status ?? 'contacted';
        feedbackForm.converted = lead.feedback.converted ?? false;
        feedbackForm.notes = lead.feedback.notes ?? '';
    } else {
        feedbackForm.status = 'contacted';
        feedbackForm.converted = false;
        feedbackForm.notes = '';
    }
};

const selectLeadForAction = (lead, scroll = true) => {
    selectedLeadUuid.value = lead.uuid;
    syncFormsToSelection(lead);
    returnForm.reason = '';
    feedbackForm.clearErrors();
    returnForm.clearErrors();

    if (scroll) {
        nextTick(() => {
            quickActionsEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }
};

watch(selectedLeadUuid, (uuid) => {
    const lead = props.leads.data?.find((row) => row.uuid === uuid)
        ?? props.actionLeads?.find((row) => row.uuid === uuid);
    syncFormsToSelection(lead ?? null);
});

const submitFeedback = () => {
    if (!selectedLeadUuid.value) {
        feedbackForm.setError('lead_uuid', 'Select a lead first.');
        return;
    }

    feedbackForm.lead_uuid = selectedLeadUuid.value;
    feedbackForm.post(route('portal.buyer.feedback'), {
        preserveScroll: true,
        onSuccess: () => feedbackForm.reset('notes'),
    });
};

const submitReturn = () => {
    if (!selectedLeadUuid.value) {
        returnForm.setError('lead_uuid', 'Select a lead first.');
        return;
    }

    returnForm.lead_uuid = selectedLeadUuid.value;
    returnForm.post(route('portal.buyer.returns'), {
        preserveScroll: true,
        onSuccess: () => returnForm.reset('reason'),
    });
};

const applyReturnPreset = (text) => {
    returnForm.reason = text;
};

const copyUuid = async (uuid) => {
    try {
        await navigator.clipboard.writeText(uuid);
    } catch {
        // ignore
    }
};

const feedbackLabel = (lead) => {
    if (!lead.feedback) return '—';
    return `${lead.feedback.status}${lead.feedback.converted ? ' ✓' : ''}`;
};

const returnLabel = (lead) => {
    if (!lead.return_request) return '—';
    return lead.return_request.status;
};
</script>

<template>
    <Head title="My Leads" />
    <AuthenticatedLayout>
        <PageHeader title="My Leads" description="Search inventory, report conversions, and request returns from the table or quick actions below.">
            <template #actions>
                <AppButton :href="route('portal.buyer.leads.download', localFilters)" variant="secondary" external>Export CSV</AppButton>
            </template>
        </PageHeader>

        <div
            v-if="page.props.flash?.success"
            class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-200"
        >
            {{ page.props.flash.success }}
        </div>

        <div class="grid gap-6 lg:grid-cols-4">
            <div class="space-y-6 lg:col-span-3">
                <Panel title="Filters">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                        <div class="xl:col-span-2">
                            <InputLabel value="Search" />
                            <input v-model="localFilters.search" class="form-input mt-1 w-full" placeholder="Name, email, UUID" @keyup.enter="applyFilters" />
                        </div>
                        <div>
                            <InputLabel value="Status" />
                            <select v-model="localFilters.status" class="form-select mt-1 w-full">
                                <option value="">All</option>
                                <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Campaign" />
                            <select v-model="localFilters.campaign_id" class="form-select mt-1 w-full">
                                <option value="">All</option>
                                <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Feedback" />
                            <select v-model="localFilters.feedback" class="form-select mt-1 w-full">
                                <option value="">Any</option>
                                <option value="none">Not reported</option>
                                <option value="reported">Reported</option>
                                <option value="converted">Converted</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="From" />
                            <input v-model="localFilters.from_date" type="date" class="form-input mt-1 w-full" />
                        </div>
                        <div>
                            <InputLabel value="To" />
                            <input v-model="localFilters.to_date" type="date" class="form-input mt-1 w-full" />
                        </div>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <AppButton @click="applyFilters">Apply</AppButton>
                        <AppButton variant="secondary" @click="clearFilters">Clear</AppButton>
                    </div>
                </Panel>

                <Panel title="Lead inventory" :padding="false">
                    <DataTable :empty="!leads.data?.length" empty-message="No leads match your filters. Clear filters or contact your account manager if you expect inventory here.">
                        <template #head>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Lead</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Contact</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Feedback</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Return</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Cost</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                        </template>
                        <tr v-for="lead in leads.data" :key="lead.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-4 py-3">
                                <button type="button" class="font-mono text-xs text-indigo-600 hover:underline dark:text-indigo-400" :title="lead.uuid" @click="copyUuid(lead.uuid)">
                                    {{ lead.uuid?.slice(0, 10) }}…
                                </button>
                                <div class="mt-1"><StatusBadge :status="lead.status" /></div>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium text-slate-900 dark:text-white">{{ lead.field_data?.firstname }} {{ lead.field_data?.lastname }}</p>
                                <p class="text-xs text-slate-500">{{ lead.field_data?.email }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ lead.campaign?.reference }}</td>
                            <td class="px-4 py-3 text-xs capitalize text-slate-600 dark:text-slate-400">{{ feedbackLabel(lead) }}</td>
                            <td class="px-4 py-3 text-xs capitalize text-slate-600 dark:text-slate-400">{{ returnLabel(lead) }}</td>
                            <td class="px-4 py-3 font-medium text-emerald-600 dark:text-emerald-400">{{ formatMoney(lead.financials?.revenue ?? 0) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-1">
                                    <Link :href="route('portal.buyer.leads.show', lead.uuid)" class="rounded-lg px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">View</Link>
                                    <button type="button" class="rounded-lg px-2 py-1 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/40" @click="selectLeadForAction(lead)">Feedback</button>
                                    <button
                                        v-if="!lead.return_request || lead.return_request.status !== 'pending'"
                                        type="button"
                                        class="rounded-lg px-2 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-amber-950/40"
                                        @click="selectLeadForAction(lead)"
                                    >
                                        Return
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </DataTable>
                    <Pagination :links="leads.links" />
                </Panel>

                <div ref="quickActionsEl">
                    <Panel title="Quick actions">
                        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                            Pick a lead from your inventory — no UUID typing required. Use row actions above to pre-fill, or choose from the list.
                        </p>

                        <div class="mb-6">
                            <InputLabel value="Lead" />
                            <select v-model="selectedLeadUuid" class="form-select mt-1 w-full">
                                <option value="">Select a lead…</option>
                                <option v-for="lead in actionLeads" :key="lead.uuid" :value="lead.uuid">
                                    {{ leadOptionLabel(lead) }}<template v-if="lead.return_pending"> · return pending</template>
                                </option>
                            </select>
                            <InputError class="mt-1" :message="feedbackForm.errors.lead_uuid ?? returnForm.errors.lead_uuid" />
                            <p v-if="selectedLeadMeta?.return_pending" class="mt-2 text-sm text-amber-700 dark:text-amber-300">
                                A return is already pending review for this lead.
                            </p>
                        </div>

                        <div v-if="!actionLeads?.length" class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700">
                            No purchased leads yet. Feedback and returns become available once leads are sold to your account.
                        </div>

                        <div v-else class="grid gap-6 lg:grid-cols-2">
                            <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Submit feedback</h3>
                                <p class="mt-1 text-xs text-slate-500">Report contact or conversion outcome to your platform administrator.</p>
                                <form class="mt-4 space-y-4" @submit.prevent="submitFeedback">
                                    <FormErrorSummary :errors="feedbackForm.errors" />
                                    <div>
                                        <InputLabel value="Status" />
                                        <select v-model="feedbackForm.status" class="form-select mt-1 w-full" :disabled="!selectedLeadUuid">
                                            <option value="contacted">Contacted</option>
                                            <option value="called">Called</option>
                                            <option value="callback">Callback scheduled</option>
                                            <option value="converted">Converted</option>
                                            <option value="funded">Funded</option>
                                            <option value="invalid">Invalid</option>
                                        </select>
                                    </div>
                                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                                        <input v-model="feedbackForm.converted" type="checkbox" class="rounded border-slate-300 text-indigo-600" :disabled="!selectedLeadUuid" />
                                        Mark as converted
                                    </label>
                                    <div>
                                        <InputLabel value="Notes" />
                                        <textarea v-model="feedbackForm.notes" class="form-input mt-1 w-full" rows="3" placeholder="Optional notes" :disabled="!selectedLeadUuid" />
                                    </div>
                                    <PrimaryButton :disabled="feedbackForm.processing || !selectedLeadUuid">Submit feedback</PrimaryButton>
                                </form>
                            </div>

                            <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Request return</h3>
                                <p class="mt-1 text-xs text-slate-500">Requires administrator approval. Credit is not refunded automatically.</p>
                                <form v-if="canReturnSelectedLead" class="mt-4 space-y-4" @submit.prevent="submitReturn">
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
                                        <InputLabel value="Return reason" />
                                        <textarea v-model="returnForm.reason" class="form-input mt-1 w-full" rows="4" placeholder="Explain why this lead should be returned" required />
                                        <InputError class="mt-1" :message="returnForm.errors.reason" />
                                    </div>
                                    <PrimaryButton :disabled="returnForm.processing">Submit return</PrimaryButton>
                                </form>
                                <p v-else-if="selectedLeadUuid && selectedLeadMeta?.return_pending" class="mt-4 text-sm text-amber-700 dark:text-amber-300">
                                    Return already pending for this lead.
                                </p>
                                <p v-else class="mt-4 text-sm text-slate-500">Select a lead to request a return.</p>
                            </div>
                        </div>
                    </Panel>
                </div>
            </div>

            <div class="space-y-6">
                <BuyerAccountPanel :account="account" :currency="currency" />

                <Panel title="Your submissions">
                    <div v-if="!recentActivity?.length" class="py-4 text-sm text-slate-500">No feedback or returns yet.</div>
                    <ul v-else class="space-y-3">
                        <li v-for="(item, index) in recentActivity" :key="index" class="text-sm">
                            <span class="font-medium capitalize text-slate-900 dark:text-white">
                                {{ item.type === 'return' ? `Return · ${item.status}` : `Feedback · ${item.status}` }}
                            </span>
                            <p class="mt-0.5 font-mono text-xs text-slate-500">{{ item.lead_uuid?.slice(0, 12) }}…</p>
                            <FormattedDate :value="item.at" class="text-xs text-slate-400" />
                        </li>
                    </ul>
                </Panel>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
