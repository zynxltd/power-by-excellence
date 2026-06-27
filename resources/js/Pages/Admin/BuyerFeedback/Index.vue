<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import ClickableTableRow from '@/Components/UI/ClickableTableRow.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    summary: Object,
    breakdowns: Object,
    feedback: Object,
    filters: { type: Object, default: () => ({}) },
    filterOptions: { type: Object, default: () => ({}) },
});

const { formatMoney } = useMoneyFormat();

const localFilters = ref({ ...props.filters });

const statStrip = computed(() => [
    { label: 'Total feedback', value: props.summary?.total ?? 0, accent: 'indigo' },
    { label: 'Invalid / bad', value: props.summary?.invalid ?? 0, accent: 'rose' },
    { label: 'Invalid rate', value: props.summary?.invalid_rate != null ? `${props.summary.invalid_rate}%` : '—', accent: 'amber' },
    { label: 'Converted', value: props.summary?.converted ?? 0, accent: 'emerald' },
    { label: 'With notes', value: props.summary?.with_notes ?? 0, accent: 'violet' },
]);

const activeFilters = computed(() => {
    const f = props.filters ?? {};
    const chips = [];

    if (f.status) {
        chips.push({ key: 'status', label: `Status: ${f.status}` });
    }
    if (f.supplier_id) {
        const s = props.filterOptions.suppliers?.find((x) => String(x.id) === String(f.supplier_id));
        chips.push({ key: 'supplier_id', label: `Supplier: ${s?.name ?? f.supplier_id}` });
    }
    if (f.campaign_id) {
        const c = props.filterOptions.campaigns?.find((x) => String(x.id) === String(f.campaign_id));
        chips.push({ key: 'campaign_id', label: `Campaign: ${c?.name ?? f.campaign_id}` });
    }
    if (f.buyer_id) {
        const b = props.filterOptions.buyers?.find((x) => String(x.id) === String(f.buyer_id));
        chips.push({ key: 'buyer_id', label: `Buyer: ${b?.name ?? f.buyer_id}` });
    }
    if (f.sid) {
        chips.push({ key: 'sid', label: `SID: ${f.sid}` });
    }
    if (f.from_date || f.to_date) {
        chips.push({ key: 'dates', label: `${f.from_date ?? '…'} → ${f.to_date ?? '…'}` });
    }
    if (f.search) {
        chips.push({ key: 'search', label: `Search: ${f.search}` });
    }

    return chips;
});

const applyFilters = (overrides = {}) => {
    const payload = { ...localFilters.value, ...overrides };
    Object.keys(payload).forEach((k) => {
        if (payload[k] === '' || payload[k] == null) {
            delete payload[k];
        }
    });
    router.get(route('buyer-feedback.index'), payload, { preserveState: true, preserveScroll: true });
};

const drill = (patch) => {
    applyFilters({ ...props.filters, ...patch });
};

const removeFilter = (key) => {
    if (key === 'dates') {
        const next = { ...props.filters };
        delete next.from_date;
        delete next.to_date;
        router.get(route('buyer-feedback.index'), next, { preserveState: true });
        return;
    }
    const next = { ...props.filters };
    delete next[key];
    router.get(route('buyer-feedback.index'), next, { preserveState: true });
};

const clearFilters = () => {
    localFilters.value = {};
    router.get(route('buyer-feedback.index'));
};

const statusBadgeTone = (row) => {
    if (row.is_invalid) return 'danger';
    if (row.converted || ['converted', 'funded'].includes(row.status)) return 'success';
    return 'info';
};

const statusLabel = (row) => {
    if (row.converted && row.status !== 'converted') {
        return `${row.status} · converted`;
    }
    return row.status;
};
</script>

<template>
    <Head title="Buyer feedback" />
    <AuthenticatedLayout>
        <PageHeader
            title="Buyer feedback"
            description="Track buyer-reported outcomes — drill down by supplier, campaign, source, and buyer to see who flagged invalid leads and where they originated."
        >
            <template #actions>
                <AppButton :href="route('leads.index', { buyer_feedback: 'invalid' })" variant="secondary">
                    Flagged in pipeline
                </AppButton>
            </template>
        </PageHeader>

        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50/60 px-3 py-2 text-xs text-rose-900 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200">
            <p class="font-semibold">Invalid feedback trail</p>
            <p class="mt-0.5">
                When a buyer marks a lead <strong>invalid</strong>, it appears here with supplier, SID, campaign, and revenue context.
                Click any breakdown row to filter. Open a lead for the full event and delivery history.
            </p>
        </div>

        <CompactStatStrip :items="statStrip" :columns="5" class="mb-6" />

        <div v-if="activeFilters.length" class="mb-4 flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium uppercase text-slate-500">Active filters</span>
            <button
                v-for="chip in activeFilters"
                :key="chip.key"
                type="button"
                class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-medium text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200"
                @click="removeFilter(chip.key)"
            >
                {{ chip.label }}
                <span aria-hidden="true">×</span>
            </button>
            <button type="button" class="text-xs text-slate-500 underline" @click="clearFilters">Clear all</button>
        </div>

        <div class="mb-6 grid gap-4 lg:grid-cols-3">
            <Panel title="By supplier" class="min-h-0">
                <p class="mb-3 text-xs text-slate-500">Click a row to filter — trace bad feedback to affiliate source.</p>
                <div class="max-h-56 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="pb-2">Supplier</th>
                                <th class="pb-2 text-right">Invalid</th>
                                <th class="pb-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in breakdowns.suppliers"
                                :key="`sup-${row.id ?? 'none'}`"
                                class="cursor-pointer border-t border-slate-100 hover:bg-indigo-50/50 dark:border-slate-800 dark:hover:bg-indigo-950/20"
                                @click="drill({ supplier_id: row.id, campaign_id: '', buyer_id: '', sid: '', status: filters.status })"
                            >
                                <td class="py-2 pr-2">
                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ row.name }}</span>
                                    <span v-if="row.reference" class="block text-xs text-slate-500">{{ row.reference }}</span>
                                </td>
                                <td class="py-2 text-right font-medium text-rose-600">{{ row.invalid }}</td>
                                <td class="py-2 text-right text-slate-600">{{ row.total }}</td>
                            </tr>
                            <tr v-if="!breakdowns.suppliers?.length">
                                <td colspan="3" class="py-4 text-center text-sm text-slate-500">No feedback in this view</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Panel>

            <Panel title="By campaign" class="min-h-0">
                <p class="mb-3 text-xs text-slate-500">Which verticals attract the most invalid reports.</p>
                <div class="max-h-56 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="pb-2">Campaign</th>
                                <th class="pb-2 text-right">Invalid</th>
                                <th class="pb-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in breakdowns.campaigns"
                                :key="`camp-${row.id ?? 'none'}`"
                                class="cursor-pointer border-t border-slate-100 hover:bg-indigo-50/50 dark:border-slate-800 dark:hover:bg-indigo-950/20"
                                @click="drill({ campaign_id: row.id, supplier_id: '', buyer_id: '', sid: '' })"
                            >
                                <td class="py-2 pr-2">
                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ row.name }}</span>
                                </td>
                                <td class="py-2 text-right font-medium text-rose-600">{{ row.invalid }}</td>
                                <td class="py-2 text-right text-slate-600">{{ row.total }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Panel>

            <Panel title="By buyer" class="min-h-0">
                <p class="mb-3 text-xs text-slate-500">Which buyers reported feedback — open buyer for full history.</p>
                <div class="max-h-56 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="pb-2">Buyer</th>
                                <th class="pb-2 text-right">Invalid</th>
                                <th class="pb-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in breakdowns.buyers"
                                :key="`buy-${row.id}`"
                                class="cursor-pointer border-t border-slate-100 hover:bg-indigo-50/50 dark:border-slate-800 dark:hover:bg-indigo-950/20"
                                @click="drill({ buyer_id: row.id, supplier_id: '', campaign_id: '', sid: '' })"
                            >
                                <td class="py-2 pr-2">
                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ row.name }}</span>
                                </td>
                                <td class="py-2 text-right font-medium text-rose-600">{{ row.invalid }}</td>
                                <td class="py-2 text-right text-slate-600">{{ row.total }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Panel>
        </div>

        <Panel v-if="breakdowns.sids?.length" title="By source (SID)" class="mb-6">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="row in breakdowns.sids"
                    :key="row.sid ?? 'none'"
                    type="button"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-left text-sm transition hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-slate-700 dark:hover:bg-indigo-950/20"
                    @click="drill({ sid: row.sid ?? '', supplier_id: '', campaign_id: '', buyer_id: '' })"
                >
                    <span class="font-mono font-medium">{{ row.sid ?? 'No SID' }}</span>
                    <span class="ml-2 text-xs text-slate-500">{{ row.invalid }} invalid / {{ row.total }} total</span>
                </button>
            </div>
        </Panel>

        <Panel title="Filters">
            <form class="grid gap-4 md:grid-cols-4 lg:grid-cols-6" @submit.prevent="applyFilters()">
                <div>
                    <label class="text-xs font-medium text-slate-600">Status</label>
                    <select v-model="localFilters.status" class="form-select mt-1 w-full">
                        <option value="">All statuses</option>
                        <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-600">Supplier</label>
                    <select v-model="localFilters.supplier_id" class="form-select mt-1 w-full">
                        <option value="">All suppliers</option>
                        <option v-for="s in filterOptions.suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-600">Campaign</label>
                    <select v-model="localFilters.campaign_id" class="form-select mt-1 w-full">
                        <option value="">All campaigns</option>
                        <option v-for="c in filterOptions.campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-600">Buyer</label>
                    <select v-model="localFilters.buyer_id" class="form-select mt-1 w-full">
                        <option value="">All buyers</option>
                        <option v-for="b in filterOptions.buyers" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-600">From</label>
                    <input v-model="localFilters.from_date" type="date" class="form-input mt-1 w-full" />
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-600">To</label>
                    <input v-model="localFilters.to_date" type="date" class="form-input mt-1 w-full" />
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs font-medium text-slate-600">Search UUID / queue / SID / notes</label>
                    <input v-model="localFilters.search" type="search" class="form-input mt-1 w-full" placeholder="Lead UUID, queue ID…" />
                </div>
                <div class="flex items-end gap-2">
                    <AppButton type="submit">Apply</AppButton>
                    <AppButton type="button" variant="secondary" @click="clearFilters">Reset</AppButton>
                    <AppButton type="button" variant="secondary" @click="applyFilters({ status: 'invalid' })">Invalid only</AppButton>
                </div>
            </form>
        </Panel>

        <Panel title="Feedback trail" class="mt-6">
            <DataTable>
                <template #head>
                    <tr>
                        <th>Recorded</th>
                        <th>Status</th>
                        <th>Lead</th>
                        <th>Campaign</th>
                        <th>Supplier / SID</th>
                        <th>Buyer</th>
                        <th>Revenue</th>
                        <th>Notes</th>
                    </tr>
                </template>
                <template #body>
                    <ClickableTableRow
                        v-for="row in feedback.data"
                        :key="row.id"
                        :href="row.lead ? route('leads.show', row.lead.id) : ''"
                    >
                        <td class="whitespace-nowrap text-sm">
                            <FormattedDate :value="row.recorded_at" />
                        </td>
                        <td>
                            <StatusBadge :tone="statusBadgeTone(row)">{{ statusLabel(row) }}</StatusBadge>
                        </td>
                        <td class="text-sm">
                            <span class="font-mono text-xs">{{ row.lead?.uuid?.slice(0, 8) }}…</span>
                            <span class="block text-xs text-slate-500">{{ row.lead?.queue_id }}</span>
                        </td>
                        <td class="text-sm">
                            <Link
                                v-if="row.lead?.campaign"
                                :href="route('campaigns.show', row.lead.campaign.id)"
                                class="text-indigo-600 hover:underline"
                                @click.stop
                            >
                                {{ row.lead.campaign.name }}
                            </Link>
                            <span v-else>—</span>
                        </td>
                        <td class="text-sm">
                            <template v-if="row.lead?.supplier">
                                <Link
                                    :href="route('suppliers.show', row.lead.supplier.id)"
                                    class="font-medium text-indigo-600 hover:underline"
                                    @click.stop
                                >
                                    {{ row.lead.supplier.name }}
                                </Link>
                                <button
                                    v-if="row.lead.sid"
                                    type="button"
                                    class="mt-0.5 block font-mono text-xs text-slate-500 hover:text-indigo-600"
                                    @click.stop="drill({ sid: row.lead.sid, supplier_id: row.lead.supplier.id })"
                                >
                                    SID: {{ row.lead.sid }}
                                </button>
                            </template>
                            <span v-else class="text-slate-500">{{ row.lead?.sid ?? '—' }}</span>
                        </td>
                        <td class="text-sm">
                            <Link
                                v-if="row.buyer"
                                :href="route('buyers.show', { buyer: row.buyer.id, feedback: row.id })"
                                class="text-indigo-600 hover:underline"
                                @click.stop
                            >
                                {{ row.buyer.name }}
                            </Link>
                        </td>
                        <td class="text-sm whitespace-nowrap">
                            {{ row.lead?.revenue != null ? formatMoney(row.lead.revenue, row.lead.currency) : '—' }}
                        </td>
                        <td class="max-w-xs truncate text-sm text-slate-600" :title="row.notes">
                            {{ row.notes || '—' }}
                        </td>
                    </ClickableTableRow>
                    <tr v-if="!feedback.data?.length">
                        <td colspan="8" class="py-8 text-center text-sm text-slate-500">No buyer feedback matches these filters.</td>
                    </tr>
                </template>
            </DataTable>
            <Pagination :links="feedback.links" class="mt-4" />
        </Panel>
    </AuthenticatedLayout>
</template>
