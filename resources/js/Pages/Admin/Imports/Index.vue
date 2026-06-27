<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({ imports: Object, campaigns: Array });

const form = useForm({ campaign_id: '', file: null });
const suppressionForm = useForm({ type: 'suppression', campaign_id: '', field: 'email', file: null });

const submit = () => form.post(route('imports.store'), { forceFormData: true });
const submitSuppression = () => suppressionForm.post(route('imports.store'), { forceFormData: true });

const onFile = (e) => { form.file = e.target.files[0]; };
const onSuppressionFile = (e) => { suppressionForm.file = e.target.files[0]; };
</script>

<template>
    <Head title="CSV Imports" />
    <AuthenticatedLayout>
        <PageHeader title="CSV Imports" description="Bulk import leads or upload suppression lists (hashed opt-outs)." />

        <Panel class="mb-6">
            <p class="text-sm text-slate-700 dark:text-slate-300">
                <strong>Supplier self-serve:</strong> linked suppliers can bulk-upload CSV files from the supplier portal import page (same validation pipeline, attributed to their account).
            </p>
        </Panel>

        <Panel class="mb-6 border-amber-200 bg-amber-50/50 dark:border-amber-500/30 dark:bg-amber-500/5">
            <p class="text-sm text-slate-700 dark:text-slate-300">
                <strong>Queue worker required.</strong> CSV imports are processed asynchronously. Ensure a queue worker is running
                (<code class="rounded bg-white/80 px-1 text-xs dark:bg-slate-900">php artisan queue:work</code> or Horizon) or rows will stay pending.
            </p>
        </Panel>

        <div class="space-y-6">
            <Panel title="Upload lead CSV">
                <form @submit.prevent="submit" class="space-y-4">
                    <FormErrorSummary :errors="form.errors" />
                    <div>
                        <InputLabel value="Campaign" />
                        <select v-model="form.campaign_id" class="form-select" required>
                            <option value="">Select campaign</option>
                            <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }} ({{ c.reference }})</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="CSV File" />
                        <input type="file" accept=".csv,.txt" class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 dark:file:bg-indigo-500/15 dark:file:text-indigo-400" @change="onFile" required />
                    </div>
                    <PrimaryButton :disabled="form.processing">Import leads</PrimaryButton>
                </form>
                <p class="mt-4 text-sm text-slate-600 dark:text-slate-400">
                    CSV column headers must match campaign field names (e.g. <code class="text-xs">email</code>, <code class="text-xs">phone1</code>, <code class="text-xs">zipcode</code>). Each row is queued through the same validation and distribution pipeline as API ingest.
                </p>
            </Panel>

            <Panel title="Upload suppression list">
                <form @submit.prevent="submitSuppression" class="space-y-4">
                    <FormErrorSummary :errors="suppressionForm.errors" />
                    <div>
                        <InputLabel value="Campaign scope" />
                        <select v-model="suppressionForm.campaign_id" class="form-select" required>
                            <option value="">Select campaign</option>
                            <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }} ({{ c.reference }})</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Field type" />
                        <select v-model="suppressionForm.field" class="form-select" required>
                            <option value="email">Email</option>
                            <option value="phone1">Phone</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="CSV File" />
                        <input type="file" accept=".csv,.txt" class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 dark:file:bg-indigo-500/15 dark:file:text-indigo-400" @change="onSuppressionFile" required />
                    </div>
                    <PrimaryButton :disabled="suppressionForm.processing">Import suppression list</PrimaryButton>
                </form>
                <p class="mt-4 text-sm text-slate-600 dark:text-slate-400">
                    Upload raw emails/phones or pre-computed SHA-256 hashes (one value per row). Values are normalised, stored as one-way hashes, and compared at validation—matching leads are rejected before distribution.
                </p>
            </Panel>

            <Panel title="Import history" :padding="false">
                <DataTable :empty="!imports.data?.length" empty-message="No imports yet. Upload a CSV to bulk-ingest leads.">
                    <template #head>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">File</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Rows</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Success</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Failed</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                    </template>
                    <tr v-for="i in imports.data" :key="i.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ i.filename }}</td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ i.campaign?.name }}</td>
                        <td class="px-6 py-4"><StatusBadge :status="i.status" /></td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ i.total_rows ?? 0 }}</td>
                        <td class="px-6 py-4 text-emerald-600 dark:text-emerald-400">{{ i.success_rows }}</td>
                        <td class="px-6 py-4 text-rose-600 dark:text-rose-400">{{ i.failed_rows }}</td>
                        <td class="px-6 py-4"><FormattedDate :value="i.created_at" /></td>
                    </tr>
                </DataTable>
                <Pagination :links="imports.links" />
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
