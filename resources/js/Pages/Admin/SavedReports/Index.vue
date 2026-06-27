<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    reports: Object,
});

const showCreate = ref(false);
const editingId = ref(null);

const defaultFilters = () => ({
    campaign_id: '',
    status: '',
    from_date: '',
    to_date: '',
});

const createForm = useForm({
    name: '',
    filters: defaultFilters(),
    schedule_cron: '',
    email_recipients: [''],
    status: 'active',
});

const editForm = useForm({
    name: '',
    filters: defaultFilters(),
    schedule_cron: '',
    email_recipients: [''],
    status: 'active',
});

const filtersJson = (filters) => JSON.stringify(filters ?? {}, null, 2);

const parseFiltersJson = (form, json) => {
    try {
        form.filters = JSON.parse(json);
        form.clearErrors('filters');
    } catch {
        form.setError('filters', 'Invalid JSON');
    }
};

const submitCreate = () => {
    createForm.email_recipients = createForm.email_recipients.filter(Boolean);
    createForm.post(route('saved-reports.store'), {
        onSuccess: () => {
            createForm.reset();
            createForm.email_recipients = [''];
            createForm.filters = defaultFilters();
            showCreate.value = false;
        },
    });
};

const startEdit = (report) => {
    editingId.value = report.id;
    editForm.name = report.name;
    editForm.filters = { ...defaultFilters(), ...(report.filters ?? {}) };
    editForm.schedule_cron = report.schedule_cron ?? '';
    editForm.email_recipients = report.email_recipients?.length ? [...report.email_recipients] : [''];
    editForm.status = report.status ?? 'active';
};

const submitEdit = () => {
    editForm.email_recipients = editForm.email_recipients.filter(Boolean);
    editForm.put(route('saved-reports.update', editingId.value), {
        onSuccess: () => { editingId.value = null; },
    });
};

const runReport = (id) => router.post(route('saved-reports.run', id));
const exportReport = (id) => { window.location.href = route('saved-reports.export', id); };
const destroy = (id) => {
    if (confirm('Delete this saved report?')) {
        router.delete(route('saved-reports.destroy', id));
    }
};
</script>

<template>
    <Head title="Saved reports" />
    <AuthenticatedLayout>
        <PageHeader title="Saved reports" description="Reusable lead reports with filter presets and optional email scheduling.">
            <template #actions>
                <AppButton @click="showCreate = !showCreate">{{ showCreate ? 'Cancel' : 'New report' }}</AppButton>
            </template>
        </PageHeader>

        <Panel v-if="showCreate" title="Create saved report" class="mb-6">
            <form class="space-y-4" @submit.prevent="submitCreate">
                <FormErrorSummary :errors="createForm.errors" />

                <div>
                    <InputLabel value="Report name" />
                    <input v-model="createForm.name" type="text" class="form-input mt-1 w-full" required />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel value="Campaign ID" />
                        <input v-model="createForm.filters.campaign_id" type="number" class="form-input mt-1 w-full" />
                    </div>
                    <div>
                        <InputLabel value="Lead status" />
                        <input v-model="createForm.filters.status" type="text" class="form-input mt-1 w-full" placeholder="sold, pending…" />
                    </div>
                    <div>
                        <InputLabel value="From date" />
                        <input v-model="createForm.filters.from_date" type="date" class="form-input mt-1 w-full" />
                    </div>
                    <div>
                        <InputLabel value="To date" />
                        <input v-model="createForm.filters.to_date" type="date" class="form-input mt-1 w-full" />
                    </div>
                </div>

                <div>
                    <InputLabel value="Filters JSON (advanced)" />
                    <textarea
                        :value="filtersJson(createForm.filters)"
                        class="form-input mt-1 w-full font-mono text-xs"
                        rows="4"
                        @input="parseFiltersJson(createForm, $event.target.value)"
                    />
                </div>

                <div>
                    <InputLabel value="Schedule cron (optional)" />
                    <input v-model="createForm.schedule_cron" type="text" class="form-input mt-1 w-full font-mono text-sm" placeholder="0 7 * * 1" />
                </div>

                <div>
                    <InputLabel value="Email recipients" />
                    <div v-for="(_, i) in createForm.email_recipients" :key="`create-email-${i}`" class="mt-2 flex gap-2">
                        <input v-model="createForm.email_recipients[i]" type="email" class="form-input w-full" placeholder="reports@company.com" />
                    </div>
                    <button type="button" class="mt-2 text-xs font-medium text-indigo-600 hover:underline" @click="createForm.email_recipients.push('')">+ Add recipient</button>
                </div>

                <PrimaryButton :disabled="createForm.processing">Save report</PrimaryButton>
            </form>
        </Panel>

        <Panel v-if="editingId" title="Edit report" class="mb-6">
            <form class="space-y-4" @submit.prevent="submitEdit">
                <FormErrorSummary :errors="editForm.errors" />

                <div>
                    <InputLabel value="Report name" />
                    <input v-model="editForm.name" type="text" class="form-input mt-1 w-full" required />
                </div>

                <div>
                    <InputLabel value="Filters JSON" />
                    <textarea
                        :value="filtersJson(editForm.filters)"
                        class="form-input mt-1 w-full font-mono text-xs"
                        rows="4"
                        @input="parseFiltersJson(editForm, $event.target.value)"
                    />
                </div>

                <div class="flex gap-2">
                    <PrimaryButton :disabled="editForm.processing">Update report</PrimaryButton>
                    <AppButton type="button" variant="secondary" @click="editingId = null">Cancel</AppButton>
                </div>
            </form>
        </Panel>

        <Panel title="Saved reports" :padding="false">
            <DataTable :empty="!reports?.data?.length">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Filters</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Last run</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                </template>
                <tr v-for="report in reports.data" :key="report.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ report.name }}</td>
                    <td class="px-6 py-4">
                        <code class="block max-w-xs truncate text-xs text-slate-500">{{ JSON.stringify(report.filters ?? {}) }}</code>
                    </td>
                    <td class="px-6 py-4"><StatusBadge :status="report.status" /></td>
                    <td class="px-6 py-4"><FormattedDate :value="report.last_run_at" /></td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex flex-wrap justify-end gap-1">
                            <AppButton variant="ghost" @click="exportReport(report.id)">Export</AppButton>
                            <AppButton variant="ghost" @click="runReport(report.id)">Run</AppButton>
                            <AppButton variant="ghost" @click="startEdit(report)">Edit</AppButton>
                            <AppButton variant="ghost" @click="destroy(report.id)">Delete</AppButton>
                        </div>
                    </td>
                </tr>
            </DataTable>
            <Pagination :links="reports.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
