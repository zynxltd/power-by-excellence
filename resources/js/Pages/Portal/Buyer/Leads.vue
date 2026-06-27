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
import { useBuyerPortalI18n } from '@/Composables/useBuyerPortalI18n';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({
    leads: Object,
    filters: Object,
    campaigns: Array,
    suppliers: { type: Array, default: () => [] },
    sids: { type: Array, default: () => [] },
    statuses: Array,
    account: Object,
    recentActivity: Array,
    actionLeads: { type: Array, default: () => [] },
    currency: { type: String, default: 'GBP' },
});

const page = usePage();
const { t } = useBuyerPortalI18n();
const localFilters = ref({ ...props.filters });
const applyFilters = () => router.get(route('portal.buyer.leads'), localFilters.value, { preserveState: true, replace: true });
const clearFilters = () => { localFilters.value = {}; applyFilters(); };
watch(() => props.filters, (f) => { localFilters.value = { ...f }; });

const { formatMoney } = useMoneyFormat(props.currency);

const quickActionsEl = ref(null);
const selectAllRef = ref(null);
const selectedLeadUuid = ref('');
const selectedUuids = ref(new Set());

const feedbackForm = useForm({ lead_uuid: '', status: 'contacted', converted: false, notes: '' });
const returnForm = useForm({ lead_uuid: '', reason: '' });
const bulkFeedbackForm = useForm({ lead_uuids: [], status: 'contacted', converted: false, notes: '' });
const bulkReturnForm = useForm({ lead_uuids: [], reason: '' });

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

const canReturnSelectedLead = computed(() => {
    if (!selectedLeadUuid.value || bulkMode.value) {
        return false;
    }

    const meta = selectedLeadMeta.value;

    return meta
        && !meta.return_pending
        && meta.return_request?.status !== 'pending';
});

const canBulkReturn = computed(() => selectedCount.value > 0);

const pageUuids = computed(() => props.leads.data?.map((lead) => lead.uuid) ?? []);
const selectedCount = computed(() => selectedUuids.value.size);
const bulkMode = computed(() => selectedCount.value > 1);
const allPageSelected = computed(() => (
    pageUuids.value.length > 0 && pageUuids.value.every((uuid) => selectedUuids.value.has(uuid))
));
const somePageSelected = computed(() => pageUuids.value.some((uuid) => selectedUuids.value.has(uuid)));

const isLeadSelected = (uuid) => selectedUuids.value.has(uuid);

const toggleLeadSelection = (uuid) => {
    const next = new Set(selectedUuids.value);
    if (next.has(uuid)) {
        next.delete(uuid);
    } else {
        next.add(uuid);
    }
    selectedUuids.value = next;
};

const toggleSelectAllPage = () => {
    const next = new Set(selectedUuids.value);
    if (allPageSelected.value) {
        pageUuids.value.forEach((uuid) => next.delete(uuid));
    } else {
        pageUuids.value.forEach((uuid) => next.add(uuid));
    }
    selectedUuids.value = next;
};

const clearSelection = () => {
    selectedUuids.value = new Set();
};

const exportSelected = () => {
    if (!selectedCount.value) {
        return;
    }

    const params = new URLSearchParams();
    selectedUuids.value.forEach((uuid) => params.append('uuids[]', uuid));
    window.location.href = `${route('portal.buyer.leads.download')}?${params.toString()}`;
};

watch([allPageSelected, somePageSelected], () => {
    if (selectAllRef.value) {
        selectAllRef.value.indeterminate = somePageSelected.value && !allPageSelected.value;
    }
});

watch(selectedUuids, (uuids) => {
    if (uuids.size === 1) {
        selectedLeadUuid.value = [...uuids][0];
    } else if (uuids.size === 0) {
        selectedLeadUuid.value = '';
    }
}, { deep: true });

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
    selectedUuids.value = new Set([lead.uuid]);
    selectedLeadUuid.value = lead.uuid;
    syncFormsToSelection(lead);
    returnForm.reason = '';
    feedbackForm.clearErrors();
    returnForm.clearErrors();
    bulkFeedbackForm.clearErrors();
    bulkReturnForm.clearErrors();

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

    if (uuid && (selectedUuids.value.size !== 1 || !selectedUuids.value.has(uuid))) {
        selectedUuids.value = new Set([uuid]);
    }
});

const submitFeedback = () => {
    if (!selectedLeadUuid.value) {
        feedbackForm.setError('lead_uuid', 'Select a lead first.');
        return;
    }

    feedbackForm.lead_uuid = selectedLeadUuid.value;
    feedbackForm.post(route('portal.buyer.feedback'), {
        preserveScroll: true,
        onSuccess: () => {
            feedbackForm.reset('notes');
            clearSelection();
        },
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
        onSuccess: () => {
            returnForm.reset('reason');
            clearSelection();
        },
    });
};

const submitBulkFeedback = () => {
    if (!selectedCount.value) {
        return;
    }

    bulkFeedbackForm.lead_uuids = [...selectedUuids.value];
    bulkFeedbackForm.post(route('portal.buyer.feedback.bulk'), {
        preserveScroll: true,
        onSuccess: () => {
            bulkFeedbackForm.reset('notes');
            clearSelection();
        },
    });
};

const submitBulkReturn = () => {
    if (!selectedCount.value) {
        return;
    }

    bulkReturnForm.lead_uuids = [...selectedUuids.value];
    bulkReturnForm.post(route('portal.buyer.returns.bulk'), {
        preserveScroll: true,
        onSuccess: () => {
            bulkReturnForm.reset('reason');
            clearSelection();
        },
    });
};

const scrollToQuickActions = () => {
    nextTick(() => {
        quickActionsEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
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

const showingPendingReturns = computed(() => localFilters.value.return === 'pending');

onMounted(() => {
    if (props.filters?.return === 'pending') {
        nextTick(() => {
            document.getElementById('lead-inventory')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }
});
</script>

<template>
    <Head :title="t('leads.title')" />
    <AuthenticatedLayout>
        <PageHeader :title="t('leads.title')" :description="t('leads.description')">
            <template #actions>
                <AppButton :href="route('portal.buyer.leads.download', localFilters)" variant="secondary" external>{{ t('common.export_csv') }}</AppButton>
            </template>
        </PageHeader>

        <div
            v-if="page.props.flash?.success"
            class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-200"
        >
            {{ page.props.flash.success }}
        </div>

        <div
            v-if="showingPendingReturns"
            class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200"
        >
            <span>Showing leads with return requests awaiting platform review.</span>
            <AppButton variant="secondary" class="!px-3 !py-1.5" @click="clearFilters">Clear filter</AppButton>
        </div>

        <div class="grid gap-6 lg:grid-cols-4">
            <div class="space-y-6 lg:col-span-3">
                <Panel title="Filters" class="shrink-0">
                    <div
                        class="grid w-full grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-[minmax(0,1.5fr)_minmax(0,0.9fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,0.8fr)_minmax(0,0.9fr)_minmax(0,0.9fr)_minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end"
                    >
                        <div class="col-span-2 min-w-0 sm:col-span-3 md:col-span-1">
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400">Search</label>
                            <input
                                v-model="localFilters.search"
                                class="form-input !mt-0.5 !py-1.5 !px-2.5 !text-sm w-full"
                                placeholder="Name, email, UUID"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                        <div class="min-w-0">
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400">Status</label>
                            <select v-model="localFilters.status" class="form-select !mt-0.5 !py-1.5 !px-2 !text-sm w-full">
                                <option value="">All</option>
                                <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div class="min-w-0">
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400">Campaign</label>
                            <select v-model="localFilters.campaign_id" class="form-select !mt-0.5 !py-1.5 !px-2 !text-sm w-full">
                                <option value="">All</option>
                                <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="min-w-0">
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400">Supplier</label>
                            <select v-model="localFilters.supplier_id" class="form-select !mt-0.5 !py-1.5 !px-2 !text-sm w-full">
                                <option value="">All</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <div class="min-w-0">
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400">SID</label>
                            <select v-model="localFilters.sid" class="form-select !mt-0.5 !py-1.5 !px-2 !text-sm w-full">
                                <option value="">All</option>
                                <option v-for="sid in sids" :key="sid" :value="sid">{{ sid }}</option>
                            </select>
                        </div>
                        <div class="min-w-0">
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400">Feedback</label>
                            <select v-model="localFilters.feedback" class="form-select !mt-0.5 !py-1.5 !px-2 !text-sm w-full">
                                <option value="">Any</option>
                                <option value="none">Not reported</option>
                                <option value="reported">Reported</option>
                                <option value="converted">Converted</option>
                            </select>
                        </div>
                        <div class="min-w-0">
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400">Return</label>
                            <select v-model="localFilters.return" class="form-select !mt-0.5 !py-1.5 !px-2 !text-sm w-full">
                                <option value="">Any</option>
                                <option value="pending">Pending review</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="min-w-0">
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400">From</label>
                            <input v-model="localFilters.from_date" type="date" class="form-input !mt-0.5 !py-1.5 !px-2 !text-sm w-full" />
                        </div>
                        <div class="min-w-0">
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400">To</label>
                            <input v-model="localFilters.to_date" type="date" class="form-input !mt-0.5 !py-1.5 !px-2 !text-sm w-full" />
                        </div>
                        <div class="col-span-2 flex justify-end gap-2 sm:col-span-3 md:col-span-1 md:justify-start">
                            <AppButton class="!px-3 !py-1.5" @click="applyFilters">Apply</AppButton>
                            <AppButton variant="secondary" class="!px-3 !py-1.5" @click="clearFilters">Clear</AppButton>
                        </div>
                    </div>
                </Panel>

                <Panel id="lead-inventory" title="Lead inventory" :padding="false">
                    <div
                        v-if="selectedCount"
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-indigo-50/80 px-4 py-3 dark:border-slate-700 dark:bg-indigo-950/30"
                    >
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                            {{ selectedCount }} selected
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <AppButton variant="secondary" class="!px-3 !py-1.5" @click="exportSelected">Export selected</AppButton>
                            <AppButton class="!px-3 !py-1.5" @click="scrollToQuickActions">Feedback / return</AppButton>
                            <AppButton variant="secondary" class="!px-3 !py-1.5" @click="clearSelection">Clear</AppButton>
                        </div>
                    </div>
                    <div class="hidden md:block">
                    <DataTable :empty="!leads.data?.length" empty-message="No leads match your filters. Clear filters or contact your account manager if you expect inventory here.">
                        <template #head>
                            <th class="w-10 px-4 py-3">
                                <input
                                    ref="selectAllRef"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-indigo-600 dark:border-slate-600"
                                    :checked="allPageSelected"
                                    @change="toggleSelectAllPage"
                                />
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Lead</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Contact</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Feedback</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Return</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Cost</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                        </template>
                        <tr
                            v-for="lead in leads.data"
                            :key="lead.id"
                            class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            :class="isLeadSelected(lead.uuid) && 'bg-indigo-50/70 dark:bg-indigo-950/20'"
                        >
                            <td class="px-4 py-3">
                                <input
                                    type="checkbox"
                                    class="rounded border-slate-300 text-indigo-600 dark:border-slate-600"
                                    :checked="isLeadSelected(lead.uuid)"
                                    @change="toggleLeadSelection(lead.uuid)"
                                />
                            </td>
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
                    </div>

                    <div class="md:hidden divide-y divide-slate-200 dark:divide-slate-800">
                        <div v-if="!leads.data?.length" class="px-4 py-8 text-center text-sm text-slate-500">No leads match your filters.</div>
                        <article
                            v-for="lead in leads.data"
                            :key="lead.id"
                            class="space-y-2 px-4 py-4"
                            :class="isLeadSelected(lead.uuid) && 'bg-indigo-50/70 dark:bg-indigo-950/20'"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" class="rounded border-slate-300" :checked="isLeadSelected(lead.uuid)" @change="toggleLeadSelection(lead.uuid)" />
                                    <button type="button" class="font-mono text-xs text-indigo-600 dark:text-indigo-400" @click="copyUuid(lead.uuid)">{{ lead.uuid?.slice(0, 10) }}…</button>
                                </label>
                                <StatusBadge :status="lead.status" />
                            </div>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ lead.field_data?.firstname }} {{ lead.field_data?.lastname }}</p>
                            <p class="text-xs text-slate-500">{{ lead.field_data?.email }}</p>
                            <p class="text-xs text-slate-500">{{ lead.campaign?.reference }} · {{ feedbackLabel(lead) }} · {{ returnLabel(lead) }}</p>
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-emerald-600 dark:text-emerald-400">{{ formatMoney(lead.financials?.revenue ?? 0) }}</span>
                                <div class="flex gap-2">
                                    <Link :href="route('portal.buyer.leads.show', lead.uuid)" class="text-xs font-semibold text-slate-600">View</Link>
                                    <button type="button" class="text-xs font-semibold text-indigo-600" @click="selectLeadForAction(lead)">Actions</button>
                                </div>
                            </div>
                        </article>
                    </div>

                    <Pagination :links="leads.links" />
                </Panel>

                <div ref="quickActionsEl">
                    <Panel title="Quick actions">
                        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                            <template v-if="bulkMode">
                                {{ selectedCount }} leads selected — apply the same feedback or return reason to all, or clear selection to work on one lead.
                            </template>
                            <template v-else>
                                Pick a lead from your inventory — use row checkboxes, table actions, or the dropdown below.
                            </template>
                        </p>

                        <div v-if="bulkMode" class="grid gap-6 lg:grid-cols-2">
                            <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Bulk feedback</h3>
                                <p class="mt-1 text-xs text-slate-500">Apply the same status to all selected leads.</p>
                                <form class="mt-4 space-y-4" @submit.prevent="submitBulkFeedback">
                                    <FormErrorSummary :errors="bulkFeedbackForm.errors" />
                                    <div>
                                        <InputLabel value="Status" />
                                        <select v-model="bulkFeedbackForm.status" class="form-select mt-1 w-full">
                                            <option value="contacted">Contacted</option>
                                            <option value="called">Called</option>
                                            <option value="callback">Callback scheduled</option>
                                            <option value="converted">Converted</option>
                                            <option value="funded">Funded</option>
                                            <option value="invalid">Invalid</option>
                                        </select>
                                    </div>
                                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                                        <input v-model="bulkFeedbackForm.converted" type="checkbox" class="rounded border-slate-300 text-indigo-600" />
                                        Mark as converted
                                    </label>
                                    <div>
                                        <InputLabel value="Notes" />
                                        <textarea v-model="bulkFeedbackForm.notes" class="form-input mt-1 w-full" rows="3" placeholder="Optional notes" />
                                    </div>
                                    <PrimaryButton :disabled="bulkFeedbackForm.processing">
                                        Submit feedback for {{ selectedCount }} leads
                                    </PrimaryButton>
                                </form>
                            </div>

                            <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Bulk return</h3>
                                <p class="mt-1 text-xs text-slate-500">Leads with a pending return are skipped automatically.</p>
                                <form v-if="canBulkReturn" class="mt-4 space-y-4" @submit.prevent="submitBulkReturn">
                                    <FormErrorSummary :errors="bulkReturnForm.errors" />
                                    <div>
                                        <InputLabel value="Quick reasons" />
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <button
                                                v-for="preset in returnPresets"
                                                :key="`bulk-${preset}`"
                                                type="button"
                                                class="rounded-full border border-slate-200 px-3 py-1 text-xs font-medium text-slate-600 transition hover:border-amber-300 hover:bg-amber-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-amber-950/30"
                                                @click="bulkReturnForm.reason = preset"
                                            >
                                                {{ preset }}
                                            </button>
                                        </div>
                                    </div>
                                    <div>
                                        <InputLabel value="Return reason" />
                                        <textarea v-model="bulkReturnForm.reason" class="form-input mt-1 w-full" rows="4" placeholder="Explain why these leads should be returned" required />
                                        <InputError class="mt-1" :message="bulkReturnForm.errors.reason" />
                                    </div>
                                    <PrimaryButton :disabled="bulkReturnForm.processing">
                                        Submit return for {{ selectedCount }} leads
                                    </PrimaryButton>
                                </form>
                            </div>
                        </div>

                        <template v-else>
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
                        </template>
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
