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
import { computed, ref, watch } from 'vue';

const props = defineProps({
    supplier: Object,
    campaigns: Array,
    recentImports: { type: Array, default: () => [] },
    importResult: { type: Object, default: null },
});

const form = useForm({
    campaign_id: '',
    file: null,
    column_mapping: {},
});

const csvHeaders = ref([]);
const previewRows = ref([]);
const parseError = ref('');

const selectedCampaign = computed(() =>
    props.campaigns?.find((campaign) => String(campaign.id) === String(form.campaign_id)),
);

const campaignFields = computed(() => selectedCampaign.value?.fields ?? []);

const parseCsvLine = (line) => {
    const cells = [];
    let current = '';
    let inQuotes = false;

    for (let i = 0; i < line.length; i += 1) {
        const char = line[i];
        if (char === '"') {
            if (inQuotes && line[i + 1] === '"') {
                current += '"';
                i += 1;
            } else {
                inQuotes = !inQuotes;
            }
        } else if (char === ',' && !inQuotes) {
            cells.push(current);
            current = '';
        } else {
            current += char;
        }
    }

    cells.push(current);

    return cells.map((cell) => cell.trim());
};

const parseCsvPreview = async (file) => {
    parseError.value = '';
    csvHeaders.value = [];
    previewRows.value = [];
    form.column_mapping = {};

    if (!file) {
        return;
    }

    const text = await file.text();
    const lines = text.split(/\r?\n/).filter((line) => line.trim() !== '');

    if (lines.length === 0) {
        parseError.value = 'CSV file is empty.';
        return;
    }

    const headers = parseCsvLine(lines[0]).map((header) => header.replace(/^\uFEFF/, ''));
    csvHeaders.value = headers;

    previewRows.value = lines.slice(1, 6).map((line) => parseCsvLine(line));

    autoMapColumns();
};

const autoMapColumns = () => {
    if (!selectedCampaign.value || csvHeaders.value.length === 0) {
        return;
    }

    const mapping = {};
    const fieldNames = campaignFields.value.map((field) => field.name);

    csvHeaders.value.forEach((header) => {
        const normalized = header.toLowerCase().replace(/[\s-]+/g, '_');
        const match = fieldNames.find((field) => field.toLowerCase() === normalized || field.toLowerCase() === header.toLowerCase());
        if (match) {
            mapping[header] = match;
        }
    });

    form.column_mapping = mapping;
};

const onFile = async (event) => {
    const file = event.target.files[0] ?? null;
    form.file = file;
    await parseCsvPreview(file);
};

watch(() => form.campaign_id, () => autoMapColumns());

const mappedCount = computed(() => Object.values(form.column_mapping).filter(Boolean).length);

const submit = () => {
    form.post(route('portal.supplier.leads.import.store'), {
        forceFormData: true,
        preserveScroll: true,
    });
};

const errorDownloadUrl = (importId) => route('portal.supplier.leads.import.errors', importId);
</script>

<template>
    <Head title="Import Leads" />
    <AuthenticatedLayout>
        <PageHeader
            title="Import leads"
            :description="`Bulk CSV upload for ${supplier.name} — map columns, preview rows, and review failed imports.`"
        >
            <template #actions>
                <AppButton variant="secondary" :href="route('portal.supplier.leads')">← My leads</AppButton>
            </template>
        </PageHeader>

        <FlashMessage class="mb-6" />

        <div class="space-y-6">
            <Panel v-if="importResult?.failed_rows > 0" title="Import error report" class="border-rose-200 dark:border-rose-500/30">
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    {{ importResult.success_rows }} row(s) imported, {{ importResult.failed_rows }} failed.
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <AppButton variant="secondary" :href="errorDownloadUrl(importResult.id)">Download failed rows CSV</AppButton>
                </div>
                <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50">
                            <tr>
                                <th class="px-4 py-2">Row</th>
                                <th class="px-4 py-2">Reason</th>
                                <th class="px-4 py-2">Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in importResult.errors ?? []" :key="`err-${index}`" class="border-t border-slate-100 dark:border-slate-800">
                                <td class="px-4 py-2 font-mono text-xs">{{ item.row }}</td>
                                <td class="px-4 py-2 text-rose-600 dark:text-rose-400">{{ item.error }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ JSON.stringify(item.fields ?? {}) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Panel>

            <Panel class="border-amber-200 bg-amber-50/50 dark:border-amber-500/30 dark:bg-amber-500/5">
                <p class="text-sm text-slate-700 dark:text-slate-300">
                    <strong>Queue worker required.</strong> Imported rows are processed asynchronously through the same pipeline as API leads.
                </p>
            </Panel>

            <Panel title="Upload CSV">
                <form class="space-y-4" @submit.prevent="submit">
                    <FormErrorSummary :errors="form.errors" />

                    <div>
                        <InputLabel value="Campaign" />
                        <select v-model="form.campaign_id" class="form-select mt-1 w-full max-w-md" required>
                            <option value="">Select campaign</option>
                            <option v-for="campaign in campaigns" :key="campaign.id" :value="campaign.id">
                                {{ campaign.name }} ({{ campaign.reference }})
                            </option>
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
                        <p v-if="parseError" class="mt-1 text-sm text-rose-600">{{ parseError }}</p>
                    </div>

                    <div v-if="csvHeaders.length && form.campaign_id" class="space-y-4 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Column mapping</h4>
                            <span class="text-xs text-slate-500">{{ mappedCount }} of {{ csvHeaders.length }} columns mapped</span>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div v-for="header in csvHeaders" :key="header" class="flex items-center gap-3">
                                <code class="min-w-[8rem] rounded bg-slate-100 px-2 py-1 text-xs dark:bg-slate-800">{{ header }}</code>
                                <span class="text-slate-400">→</span>
                                <select v-model="form.column_mapping[header]" class="form-select flex-1 text-sm">
                                    <option value="">Skip column</option>
                                    <option v-for="field in campaignFields" :key="field.name" :value="field.name">
                                        {{ field.label }} ({{ field.name }})
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div v-if="previewRows.length" class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                        <p class="border-b border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-700">
                            Preview (first {{ previewRows.length }} rows)
                        </p>
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50">
                                <tr>
                                    <th v-for="header in csvHeaders" :key="`head-${header}`" class="px-4 py-2">{{ header }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, rowIndex) in previewRows" :key="`preview-${rowIndex}`" class="border-t border-slate-100 dark:border-slate-800">
                                    <td v-for="(cell, cellIndex) in row" :key="`cell-${rowIndex}-${cellIndex}`" class="px-4 py-2 font-mono text-xs text-slate-600 dark:text-slate-300">
                                        {{ cell }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <PrimaryButton :disabled="form.processing || mappedCount === 0">Upload and import</PrimaryButton>
                </form>
            </Panel>

            <Panel title="Recent imports" :padding="false">
                <DataTable :empty="!recentImports?.length" empty-message="No imports yet. Upload a CSV to bulk-ingest leads.">
                    <template #head>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">File</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Success</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Failed</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Errors</th>
                    </template>
                    <tr v-for="item in recentImports" :key="item.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ item.filename }}</td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ item.campaign?.name ?? '—' }}</td>
                        <td class="px-6 py-4"><StatusBadge :status="item.status" /></td>
                        <td class="px-6 py-4 text-emerald-600 dark:text-emerald-400">{{ item.success_rows }}</td>
                        <td class="px-6 py-4 text-rose-600 dark:text-rose-400">{{ item.failed_rows }}</td>
                        <td class="px-6 py-4"><FormattedDate :value="item.created_at" /></td>
                        <td class="px-6 py-4 text-right">
                            <AppButton
                                v-if="item.failed_rows > 0"
                                variant="ghost"
                                :href="errorDownloadUrl(item.id)"
                            >
                                Download CSV
                            </AppButton>
                        </td>
                    </tr>
                </DataTable>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
