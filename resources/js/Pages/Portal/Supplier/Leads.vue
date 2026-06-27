<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import SupplierAccountPanel from '@/Components/Portal/SupplierAccountPanel.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    leads: Object,
    filters: Object,
    campaigns: Array,
    sids: Array,
    statuses: Array,
    account: Object,
    recentActivity: Array,
    currency: { type: String, default: 'GBP' },
});

const localFilters = ref({ ...props.filters });
const applyFilters = () => router.get(route('portal.supplier.leads'), localFilters.value, { preserveState: true, replace: true });
const clearFilters = () => { localFilters.value = {}; applyFilters(); };
watch(() => props.filters, (f) => { localFilters.value = { ...f }; });

const { formatMoney } = useMoneyFormat(props.currency);

const copyUuid = async (uuid) => {
    try {
        await navigator.clipboard.writeText(uuid);
    } catch {
        // ignore
    }
};

const activityLabel = (item) => {
    const sid = item.sid ? ` · ${item.sid}` : '';
    return `${item.status}${sid}`;
};
</script>

<template>
    <Head title="Supplier Leads" />
    <AuthenticatedLayout>
        <PageHeader title="Supplier Leads" description="All leads submitted through your sources — filter by campaign, SID, status, and date.">
            <template #actions>
                <AppButton :href="route('portal.supplier.leads.download', localFilters)" variant="secondary" external>Export CSV</AppButton>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-4 lg:items-stretch">
            <div class="flex flex-col gap-6 lg:col-span-3">
                <Panel title="Filters" class="shrink-0">
                    <div
                        class="grid w-full grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,0.8fr)_minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end"
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
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400">SID</label>
                            <select v-model="localFilters.sid" class="form-select !mt-0.5 !py-1.5 !px-2 !text-sm w-full">
                                <option value="">All</option>
                                <option v-for="sid in sids" :key="sid" :value="sid">{{ sid }}</option>
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

                <Panel :padding="false" class="flex min-h-0 flex-1 flex-col">
                    <div class="min-h-0 flex-1 overflow-x-auto">
                        <DataTable :empty="!leads.data?.length">
                        <template #head>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Lead</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">SID</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Payout</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Received</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500" />
                        </template>
                        <tr v-for="lead in leads.data" :key="lead.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-6 py-4">
                                <button type="button" class="font-mono text-xs text-slate-500 hover:text-indigo-600" :title="lead.uuid" @click="copyUuid(lead.uuid)">
                                    {{ lead.uuid?.slice(0, 10) }}…
                                </button>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-900 dark:text-white">
                                {{ lead.field_data?.firstname }} {{ lead.field_data?.lastname }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ lead.campaign?.name }}</td>
                            <td class="px-6 py-4 font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ lead.sid || '—' }}</td>
                            <td class="px-6 py-4"><StatusBadge :status="lead.status" /></td>
                            <td class="px-6 py-4 font-medium text-emerald-600 dark:text-emerald-400">{{ formatMoney(lead.financials?.payout ?? 0) }}</td>
                            <td class="px-6 py-4"><FormattedDate :value="lead.received_at" /></td>
                            <td class="px-6 py-4 text-right">
                                <Link :href="route('portal.supplier.leads.show', lead.uuid)" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">View</Link>
                            </td>
                        </tr>
                    </DataTable>
                    </div>
                    <Pagination :links="leads.links" class="mt-auto shrink-0" />
                </Panel>
            </div>

            <div class="flex flex-col gap-6">
                <SupplierAccountPanel :account="account" :currency="currency" />

                <Panel title="Recent activity">
                    <div v-if="!recentActivity?.length" class="py-4 text-sm text-slate-500">No submissions yet.</div>
                    <ul v-else class="space-y-3">
                        <li v-for="(item, index) in recentActivity" :key="index" class="border-b border-slate-100 pb-3 last:border-0 dark:border-slate-800">
                            <p class="text-sm font-medium capitalize text-slate-900 dark:text-white">{{ activityLabel(item) }}</p>
                            <p class="mt-0.5 font-mono text-xs text-slate-500">{{ item.lead_uuid?.slice(0, 12) }}…</p>
                            <FormattedDate :value="item.at" class="mt-1 text-xs text-slate-400" />
                        </li>
                    </ul>
                </Panel>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
