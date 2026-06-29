<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    exports: Object,
    leadCount: Number,
    queueThreshold: Number,
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const requestForm = useForm({});

const requestExport = () => {
    requestForm.post(route('tools.data-export.store'));
};

const statusTone = (status) => {
    if (status === 'ready') return 'emerald';
    if (status === 'failed') return 'rose';
    if (status === 'processing') return 'amber';
    return 'slate';
};

const refresh = () => router.reload({ only: ['exports'] });
</script>

<template>
    <Head title="Tenant data export" />
    <AuthenticatedLayout>
        <PageHeader
            title="Export tenant data"
            description="Generate a GDPR subject access request (SAR) archive with leads, users, access logs, audit logs, and marketing opt-outs."
        >
            <template #actions>
                <AppButton variant="secondary" @click="refresh">Refresh</AppButton>
                <AppButton :disabled="requestForm.processing" @click="requestExport">
                    {{ requestForm.processing ? 'Requesting…' : 'Request export' }}
                </AppButton>
            </template>
        </PageHeader>

        <p v-if="flashSuccess" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ flashSuccess }}
        </p>

        <Panel class="mb-6">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                This tenant has <strong class="text-slate-900 dark:text-white">{{ leadCount?.toLocaleString() ?? 0 }}</strong> leads.
                Exports with more than {{ queueThreshold?.toLocaleString() ?? 500 }} leads are processed in the background.
                Download links expire after 7 days.
            </p>
            <p class="mt-2 text-xs text-slate-500">
                Archive contents: leads.csv, users.csv, access_logs.csv, audit_logs.csv, marketing_opt_outs.csv, manifest.json
            </p>
        </Panel>

        <Panel title="Recent exports" :padding="false">
            <DataTable :empty="!exports.data?.length" empty-message="No exports yet. Request one to generate a SAR archive.">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Requested</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Leads</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Requester</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Completed</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Download</th>
                </template>
                <tr v-for="row in exports.data" :key="row.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4"><FormattedDate :value="row.created_at" /></td>
                    <td class="px-6 py-4">
                        <StatusBadge :label="row.status" :tone="statusTone(row.status)" />
                    </td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ row.lead_count?.toLocaleString() ?? 0 }}</td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ row.requester?.name ?? '—' }}</td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                        <FormattedDate v-if="row.completed_at" :value="row.completed_at" />
                        <span v-else-if="row.error_message" class="text-rose-600 dark:text-rose-400">{{ row.error_message }}</span>
                        <span v-else>—</span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <a
                            v-if="row.status === 'ready'"
                            :href="route('tools.data-export.download', row.id)"
                            class="text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                        >
                            Download ZIP
                        </a>
                    </td>
                </tr>
            </DataTable>
            <Pagination :links="exports.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
