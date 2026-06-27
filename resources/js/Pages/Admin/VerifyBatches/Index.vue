<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    batches: Object,
});

const form = useForm({ file: null });

const onFile = (e) => { form.file = e.target.files[0]; };

const submit = () => form.post(route('verify-batches.store'), { forceFormData: true });
</script>

<template>
    <Head title="Verify batches" />
    <AuthenticatedLayout>
        <PageHeader title="Verify batches" description="Upload CSV files to validate email and phone rows in bulk." />

        <Panel title="Upload CSV" class="mb-6">
            <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                CSV must include <code class="text-xs">email</code> and/or <code class="text-xs">phone</code> / <code class="text-xs">phone1</code> columns. After upload, open the batch and run processing to see results.
            </p>
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <InputLabel value="CSV file" />
                    <input
                        type="file"
                        accept=".csv,.txt"
                        class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 dark:file:bg-indigo-500/15 dark:file:text-indigo-400"
                        required
                        @change="onFile"
                    />
                </div>
                <PrimaryButton :disabled="form.processing">Upload batch</PrimaryButton>
            </form>
        </Panel>

        <Panel title="Recent batches" :padding="false">
            <DataTable :empty="!batches?.data?.length">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">File</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Rows</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Valid</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Invalid</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Uploaded</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500" />
                </template>
                <tr v-for="batch in batches.data" :key="batch.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ batch.filename }}</td>
                    <td class="px-6 py-4"><StatusBadge :status="batch.status" /></td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ batch.total_rows }}</td>
                    <td class="px-6 py-4 text-emerald-600 dark:text-emerald-400">{{ batch.valid_rows ?? '—' }}</td>
                    <td class="px-6 py-4 text-rose-600 dark:text-rose-400">{{ batch.invalid_rows ?? '—' }}</td>
                    <td class="px-6 py-4"><FormattedDate :value="batch.created_at" /></td>
                    <td class="px-6 py-4 text-right">
                        <Link :href="route('verify-batches.show', batch.id)" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">View</Link>
                    </td>
                </tr>
            </DataTable>
            <Pagination :links="batches.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
