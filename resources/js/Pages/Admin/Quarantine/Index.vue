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
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    leads: Object,
    stats: Object,
    filters: Object,
    policy: Object,
});

const selected = ref([]);

const reasonTabs = computed(() => [
    { key: null, label: 'All', count: props.stats.total },
    { key: 'expiring', label: 'Expiring', count: props.stats.expiring_today },
    { key: 'validation', label: 'Validation', count: props.stats.validation },
    { key: 'out_of_hours', label: 'Out of hours', count: props.stats.out_of_hours },
    { key: 'unsold', label: 'Unsold retry', count: props.stats.unsold },
    { key: 'hold', label: 'General hold', count: props.stats.hold },
]);

const reasonLabel = (reason) => ({
    out_of_hours: 'Out of hours',
    validation: 'Validation',
    unsold: 'Unsold retry',
    hold: 'General hold',
}[reason] ?? reason);

const reasonBadgeClass = (reason) => ({
    out_of_hours: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
    validation: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
    unsold: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    hold: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
}[reason] ?? 'bg-slate-100 text-slate-700');

const toggleSelect = (id) => {
    const idx = selected.value.indexOf(id);
    if (idx >= 0) selected.value.splice(idx, 1);
    else selected.value.push(id);
};

const toggleAll = () => {
    if (selected.value.length === props.leads.data.length) {
        selected.value = [];
    } else {
        selected.value = props.leads.data.map((l) => l.id);
    }
};

const filterByReason = (reason) => {
    router.get(route('quarantine.index'), reason ? { reason } : {}, { preserveState: true, preserveScroll: true });
};

const bulkRelease = () => {
    if (!selected.value.length || !confirm(`Release ${selected.value.length} lead(s) from quarantine?`)) return;
    router.post(route('quarantine.bulk-release'), { lead_ids: selected.value }, {
        onSuccess: () => { selected.value = []; },
    });
};

const bulkReject = () => {
    if (!selected.value.length || !confirm(`Reject ${selected.value.length} lead(s) permanently?`)) return;
    router.post(route('quarantine.bulk-reject'), { lead_ids: selected.value }, {
        onSuccess: () => { selected.value = []; },
    });
};

const extendHold = (leadId) => {
    router.post(route('quarantine.extend', leadId), { hours: 24 });
};

const quarantineStrip = computed(() => [
    { label: 'In quarantine', value: props.stats.total, accent: 'violet' },
    { label: 'Expiring today', value: props.stats.expiring_today, accent: 'amber' },
    { label: 'Validation', value: props.stats.validation, accent: 'rose' },
    { label: 'Out of hours', value: props.stats.out_of_hours, accent: 'indigo' },
    { label: 'Unsold retry', value: props.stats.unsold, accent: 'cyan' },
]);
</script>

<template>
    <Head title="Quarantine Queue" />
    <AuthenticatedLayout>
        <PageHeader
            title="Quarantine Queue"
            description="Review, release, extend, or reject held leads - validation failures, out-of-hours, and unsold retries."
        >
            <template #actions>
                <AppButton v-if="selected.length" variant="secondary" @click="bulkRelease">
                    Release {{ selected.length }}
                </AppButton>
                <AppButton v-if="selected.length" variant="danger" @click="bulkReject">
                    Reject {{ selected.length }}
                </AppButton>
            </template>
        </PageHeader>

        <div class="mb-4 rounded-lg border border-indigo-200 bg-indigo-50/60 px-3 py-2 text-xs text-indigo-900 dark:border-indigo-900 dark:bg-indigo-950/30 dark:text-indigo-200">
            <p class="font-semibold">Hold period & expiry</p>
            <p class="mt-0.5">
                Leads are held for <strong>{{ policy?.default_hours ?? 48 }} hours</strong> by default (configurable per campaign).
                When the hold expires:
                <strong>unsold / out-of-hours / general holds</strong> are auto-released back into distribution;
                <strong>validation holds</strong> are auto-rejected.
                Expired leads stay in this queue until the scheduler runs (every 15 min) or you release/reject manually.
            </p>
        </div>

        <CompactStatStrip :items="quarantineStrip" :columns="5" class="mb-6" />

        <div class="mb-4 flex flex-wrap gap-2">
            <button
                v-for="tab in reasonTabs"
                :key="tab.key ?? 'all'"
                type="button"
                :class="[
                    'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                    (filters.reason ?? null) === tab.key
                        ? 'bg-indigo-600 text-white'
                        : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700',
                ]"
                @click="filterByReason(tab.key)"
            >
                {{ tab.label }} ({{ tab.count }})
            </button>
        </div>

        <Panel title="Quarantined leads" :padding="false">
            <DataTable :empty="!leads?.data?.length" empty-message="No leads in quarantine for this filter.">
                <template #head>
                    <th class="px-4 py-3">
                        <input type="checkbox" :checked="selected.length === leads.data.length && leads.data.length > 0" @change="toggleAll" />
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Lead</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Reason</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Held until</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Received</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                </template>
                <tr v-for="lead in leads.data" :key="lead.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-4 py-4">
                        <input type="checkbox" :checked="selected.includes(lead.id)" @change="toggleSelect(lead.id)" />
                    </td>
                    <td class="px-6 py-4">
                        <Link :href="route('leads.show', lead.id)" class="font-mono text-xs text-indigo-600 hover:underline dark:text-indigo-400">
                            {{ lead.uuid?.slice(0, 12) }}…
                        </Link>
                        <p class="text-sm text-slate-600 dark:text-slate-400">{{ lead.field_data?.email }}</p>
                        <p v-if="lead.quarantine_message" class="mt-1 max-w-xs truncate text-xs text-slate-500" :title="lead.quarantine_message">
                            {{ lead.quarantine_message }}
                        </p>
                    </td>
                    <td class="px-6 py-4">
                        <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-semibold', reasonBadgeClass(lead.quarantine_reason)]">
                            {{ reasonLabel(lead.quarantine_reason) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-900 dark:text-white">{{ lead.campaign?.name }}</td>
                    <td class="px-6 py-4">
                        <FormattedDate :value="lead.quarantined_until" />
                        <p v-if="lead.quarantined_until && new Date(lead.quarantined_until) <= new Date()" class="text-xs text-rose-600">Expired</p>
                    </td>
                    <td class="px-6 py-4"><FormattedDate :value="lead.received_at" format="relative" /></td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-2">
                            <AppButton
                                v-if="lead.quarantine_reason === 'unsold'"
                                :href="route('leads.repost', lead.id)"
                                method="post"
                                as="button"
                                variant="secondary"
                            >
                                Repost
                            </AppButton>
                            <AppButton
                                v-else-if="lead.quarantine_reason !== 'validation'"
                                :href="route('quarantine.release', lead.id)"
                                method="post"
                                as="button"
                                variant="secondary"
                            >
                                Release
                            </AppButton>
                            <AppButton variant="secondary" @click="extendHold(lead.id)">+24h</AppButton>
                            <AppButton
                                :href="route('quarantine.reject', lead.id)"
                                method="post"
                                as="button"
                                variant="danger"
                            >
                                Reject
                            </AppButton>
                        </div>
                    </td>
                </tr>
            </DataTable>
            <Pagination :links="leads.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
