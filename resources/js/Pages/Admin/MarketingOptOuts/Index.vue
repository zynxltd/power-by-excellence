<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    optOuts: Object,
    filters: Object,
    sourceOptions: Array,
    fieldTypeOptions: Array,
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const showImport = ref(false);

const filterForm = useForm({
    field_type: props.filters?.field_type ?? '',
    source: props.filters?.source ?? '',
    q: props.filters?.q ?? '',
});

const importForm = useForm({
    file: null,
});

const applyFilters = () => {
    router.get(route('marketing-opt-outs.index'), {
        field_type: filterForm.field_type || undefined,
        source: filterForm.source || undefined,
        q: filterForm.q || undefined,
    }, { preserveState: true, replace: true });
};

const submitImport = () => {
    importForm.post(route('marketing-opt-outs.import'), {
        forceFormData: true,
        onSuccess: () => {
            importForm.reset();
            showImport.value = false;
        },
    });
};

const onFileChange = (event) => {
    importForm.file = event.target.files?.[0] ?? null;
};

const sourceTone = (source) => {
    if (source === 'import') return 'indigo';
    if (source === 'webhook' || source === 'esp') return 'amber';
    if (source === 'manual') return 'slate';
    return 'emerald';
};
</script>

<template>
    <Head title="Marketing suppressions" />
    <AuthenticatedLayout>
        <PageHeader
            title="Marketing suppressions"
            description="Email and phone opt-outs that block marketing sends. Import bulk suppressions or review entries from unsubscribe links and webhooks."
        >
            <template #actions>
                <button
                    type="button"
                    class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                    @click="showImport = !showImport"
                >
                    {{ showImport ? 'Cancel import' : 'Import CSV' }}
                </button>
            </template>
        </PageHeader>

        <p v-if="flashSuccess" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ flashSuccess }}
        </p>

        <Panel v-if="showImport" title="Bulk import suppressions" class="mb-6">
            <form class="space-y-4" @submit.prevent="submitImport">
                <FormErrorSummary :errors="importForm.errors" />
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Upload a CSV with an email or phone column, or one value per line.
                </p>
                <div>
                    <InputLabel value="CSV file" />
                    <input type="file" accept=".csv,text/csv" class="mt-1 block w-full text-sm" @change="onFileChange" />
                </div>
                <PrimaryButton :disabled="importForm.processing || !importForm.file">Import suppressions</PrimaryButton>
            </form>
        </Panel>

        <Panel class="mb-6">
            <form class="grid gap-4 md:grid-cols-4" @submit.prevent="applyFilters">
                <div>
                    <InputLabel value="Field type" />
                    <select v-model="filterForm.field_type" class="form-select mt-1 w-full">
                        <option value="">All</option>
                        <option v-for="type in fieldTypeOptions" :key="type" :value="type">{{ type }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Source" />
                    <select v-model="filterForm.source" class="form-select mt-1 w-full">
                        <option value="">All</option>
                        <option v-for="source in sourceOptions" :key="source" :value="source">{{ source }}</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <InputLabel value="Search label or hash" />
                    <input v-model="filterForm.q" type="search" class="form-input mt-1 w-full" placeholder="Search…" />
                </div>
                <div class="md:col-span-4">
                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900">
                        Apply filters
                    </button>
                </div>
            </form>
        </Panel>

        <Panel title="Suppressions" :padding="false">
            <DataTable :empty="!optOuts.data?.length" empty-message="No marketing suppressions recorded yet.">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Added</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Identifier</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Source</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Hash</th>
                </template>
                <tr v-for="row in optOuts.data" :key="row.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4"><FormattedDate :value="row.created_at" /></td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ row.field_type }}</td>
                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ row.label ?? '—' }}</td>
                    <td class="px-6 py-4">
                        <StatusBadge :label="row.source" :tone="sourceTone(row.source)" />
                    </td>
                    <td class="px-6 py-4 font-mono text-xs text-slate-500">{{ row.hash?.slice(0, 12) }}…</td>
                </tr>
            </DataTable>
            <Pagination :links="optOuts.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
