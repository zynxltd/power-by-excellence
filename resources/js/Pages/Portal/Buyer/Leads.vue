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
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    leads: Object,
    filters: Object,
    campaigns: Array,
    statuses: Array,
});

const localFilters = ref({ ...props.filters });
const applyFilters = () => router.get(route('portal.buyer.leads'), localFilters.value, { preserveState: true, replace: true });
const clearFilters = () => { localFilters.value = {}; applyFilters(); };
watch(() => props.filters, (f) => { localFilters.value = { ...f }; });

const { formatMoney } = useMoneyFormat();

const feedbackForm = useForm({ lead_uuid: '', status: 'contacted', converted: false, notes: '' });
const returnForm = useForm({ lead_uuid: '', reason: '' });
const submitFeedback = () => feedbackForm.post(route('portal.buyer.feedback'), { onSuccess: () => feedbackForm.reset() });
const submitReturn = () => returnForm.post(route('portal.buyer.returns'), { onSuccess: () => returnForm.reset() });
</script>

<template>
    <Head title="My Leads" />
    <AuthenticatedLayout>
        <PageHeader title="My Leads" description="View purchased leads, filter inventory, and submit feedback or returns.">
            <template #actions>
                <AppButton :href="route('portal.buyer.leads.download', localFilters)" variant="secondary" external>Export CSV</AppButton>
            </template>
        </PageHeader>

        <Panel title="Filters" class="mb-6">
            <div class="grid gap-3 md:grid-cols-5">
                <div><InputLabel value="Search" /><input v-model="localFilters.search" class="form-input mt-1 w-full" placeholder="Name, email, UUID" @keyup.enter="applyFilters" /></div>
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
                <div><InputLabel value="From" /><input v-model="localFilters.from_date" type="date" class="form-input mt-1 w-full" /></div>
                <div><InputLabel value="To" /><input v-model="localFilters.to_date" type="date" class="form-input mt-1 w-full" /></div>
            </div>
            <div class="mt-3 flex gap-2">
                <AppButton @click="applyFilters">Apply</AppButton>
                <AppButton variant="secondary" @click="clearFilters">Clear</AppButton>
            </div>
        </Panel>

        <Panel title="Lead Inventory" :padding="false" class="mb-6">
            <DataTable :empty="!leads.data?.length">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">UUID</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Revenue</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                </template>
                <tr v-for="lead in leads.data" :key="lead.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4 font-mono text-xs text-slate-500">{{ lead.uuid?.slice(0, 10) }}…</td>
                    <td class="px-6 py-4 text-xs text-slate-500">{{ lead.campaign?.reference }}</td>
                    <td class="px-6 py-4 text-slate-900 dark:text-white">{{ lead.field_data?.firstname }} {{ lead.field_data?.lastname }}</td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ lead.field_data?.email }}</td>
                    <td class="px-6 py-4"><StatusBadge :status="lead.status" /></td>
                    <td class="px-6 py-4 font-medium text-emerald-600 dark:text-emerald-400">{{ formatMoney(lead.financials?.revenue ?? 0) }}</td>
                    <td class="px-6 py-4"><FormattedDate :value="lead.distributed_at" /></td>
                </tr>
            </DataTable>
            <Pagination :links="leads.links" />
        </Panel>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <Panel title="Submit Feedback">
                <form @submit.prevent="submitFeedback" class="space-y-4">
                    <FormErrorSummary :errors="feedbackForm.errors" />
                    <div><InputLabel value="Lead UUID" /><input v-model="feedbackForm.lead_uuid" class="form-input" placeholder="Paste lead UUID" required /><InputError class="mt-1" :message="feedbackForm.errors.lead_uuid" /></div>
                    <div>
                        <InputLabel value="Status" />
                        <select v-model="feedbackForm.status" class="form-select">
                            <option>contacted</option>
                            <option>converted</option>
                            <option>invalid</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                        <input v-model="feedbackForm.converted" type="checkbox" class="rounded border-slate-300 text-indigo-600 dark:border-slate-600" />
                        Converted
                    </label>
                    <div><InputLabel value="Notes" /><textarea v-model="feedbackForm.notes" class="form-input" rows="3" placeholder="Optional notes" /></div>
                    <PrimaryButton>Submit Feedback</PrimaryButton>
                </form>
            </Panel>

            <Panel title="Return Lead">
                <form @submit.prevent="submitReturn" class="space-y-4">
                    <FormErrorSummary :errors="returnForm.errors" />
                    <div><InputLabel value="Lead UUID" /><input v-model="returnForm.lead_uuid" class="form-input" placeholder="Paste lead UUID" required /><InputError class="mt-1" :message="returnForm.errors.lead_uuid" /></div>
                    <div><InputLabel value="Return Reason" /><textarea v-model="returnForm.reason" class="form-input" rows="4" placeholder="Explain why this lead is being returned" required /><InputError class="mt-1" :message="returnForm.errors.reason" /></div>
                    <PrimaryButton>Submit Return</PrimaryButton>
                </form>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
