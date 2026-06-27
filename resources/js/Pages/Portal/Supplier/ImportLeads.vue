<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FlashMessage from '@/Components/UI/FlashMessage.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    supplier: Object,
    campaigns: Array,
    recentImports: { type: Array, default: () => [] },
});

const form = useForm({ campaign_id: '', file: null });

const onFile = (e) => { form.file = e.target.files[0]; };

const submit = () => form.post(route('portal.supplier.leads.import.store'), {
    forceFormData: true,
    preserveScroll: true,
});
</script>

<template>
    <Head title="Import Leads" />
    <AuthenticatedLayout>
        <PageHeader
            title="Import leads"
            :description="`Bulk CSV upload for ${supplier.name} — rows are validated and queued like API ingest.`"
        >
            <template #actions>
                <AppButton variant="secondary" :href="route('portal.supplier.leads')">← My leads</AppButton>
            </template>
        </PageHeader>

        <FlashMessage class="mb-6" />

        <div class="space-y-6">
            <Panel class="border-amber-200 bg-amber-50/50 dark:border-amber-500/30 dark:bg-amber-500/5">
                <p class="text-sm text-slate-700 dark:text-slate-300">
                    <strong>Queue worker required.</strong> Imported rows are processed asynchronously through the same pipeline as API leads.
                    Ensure a worker is running or rows may stay pending.
                </p>
            </Panel>

            <Panel title="Upload CSV">
                <form class="space-y-4" @submit.prevent="submit">
                    <FormErrorSummary :errors="form.errors" />
                    <div>
                        <InputLabel value="Campaign" />
                        <select v-model="form.campaign_id" class="form-select mt-1 w-full max-w-md" required>
                            <option value="">Select campaign</option>
                            <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }} ({{ c.reference }})</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="CSV file" />
                        <input
                            type="file"
                            accept=".csv,.txt"
                            class="mt-1 block w-full max-w-lg text-sm text-slate-500 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 dark:file:bg-indigo-500/15 dark:file:text-indigo-400"
                            required
                            @change="onFile"
                        />
                    </div>
                    <PrimaryButton :disabled="form.processing">Upload and import</PrimaryButton>
                </form>
                <p class="mt-4 text-sm text-slate-600 dark:text-slate-400">
                    Column headers must match campaign field names (e.g. <code class="text-xs">email</code>, <code class="text-xs">phone1</code>).
                    Leads are attributed to your supplier account automatically.
                </p>
            </Panel>

            <Panel title="Recent imports" :padding="false">
                <DataTable :empty="!recentImports?.length" empty-message="No imports yet. Upload a CSV to bulk-ingest leads.">
                    <template #head>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">File</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Rows</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Success</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Failed</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                    </template>
                    <tr v-for="item in recentImports" :key="item.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ item.filename }}</td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ item.campaign?.name ?? '—' }}</td>
                        <td class="px-6 py-4"><StatusBadge :status="item.status" /></td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ item.total_rows ?? 0 }}</td>
                        <td class="px-6 py-4 text-emerald-600 dark:text-emerald-400">{{ item.success_rows }}</td>
                        <td class="px-6 py-4 text-rose-600 dark:text-rose-400">{{ item.failed_rows }}</td>
                        <td class="px-6 py-4"><FormattedDate :value="item.created_at" /></td>
                    </tr>
                </DataTable>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
