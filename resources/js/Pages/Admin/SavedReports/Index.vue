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
import { computed, ref, watch } from 'vue';

const props = defineProps({
    reports: Object,
    schedulePresets: Array,
});

const showCreate = ref(false);
const editingId = ref(null);
const createRecipientInput = ref('');
const editRecipientInput = ref('');

const defaultFilters = () => ({
    campaign_id: '',
    status: '',
    from_date: '',
    to_date: '',
});

const createForm = useForm({
    name: '',
    filters: defaultFilters(),
    schedule_preset: 'none',
    schedule_cron: '',
    email_recipients: [],
    status: 'active',
});

const editForm = useForm({
    name: '',
    filters: defaultFilters(),
    schedule_preset: 'none',
    schedule_cron: '',
    email_recipients: [],
    status: 'active',
});

const presetOptions = computed(() => props.schedulePresets ?? []);

const applyPresetCron = (form) => {
    const preset = presetOptions.value.find((item) => item.value === form.schedule_preset);
    if (preset && form.schedule_preset !== 'custom') {
        form.schedule_cron = preset.cron ?? '';
    }
};

watch(() => createForm.schedule_preset, () => applyPresetCron(createForm));
watch(() => editForm.schedule_preset, () => applyPresetCron(editForm));

const filtersJson = (filters) => JSON.stringify(filters ?? {}, null, 2);

const parseFiltersJson = (form, json) => {
    try {
        form.filters = JSON.parse(json);
        form.clearErrors('filters');
    } catch {
        form.setError('filters', 'Invalid JSON');
    }
};

const addCreateRecipient = () => {
    const email = createRecipientInput.value.trim();
    if (!email || createForm.email_recipients.includes(email)) {
        return;
    }
    createForm.email_recipients.push(email);
    createRecipientInput.value = '';
};

const addEditRecipient = () => {
    const email = editRecipientInput.value.trim();
    if (!email || editForm.email_recipients.includes(email)) {
        return;
    }
    editForm.email_recipients.push(email);
    editRecipientInput.value = '';
};

const removeRecipient = (form, email) => {
    form.email_recipients = form.email_recipients.filter((item) => item !== email);
};

const scheduleLabel = (report) => {
    if (!report.schedule_cron) {
        return 'Manual only';
    }
    const preset = presetOptions.value.find((item) => item.value === report.schedule_preset);
    return preset?.label ?? report.schedule_cron;
};

const lastRunTone = (status) => ({
    success: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-400',
    failed: 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-400',
}[status] ?? 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300');

const submitCreate = () => {
    applyPresetCron(createForm);
    createForm.post(route('saved-reports.store'), {
        onSuccess: () => {
            createForm.reset();
            createForm.filters = defaultFilters();
            createForm.schedule_preset = 'none';
            createForm.email_recipients = [];
            createRecipientInput.value = '';
            showCreate.value = false;
        },
    });
};

const startEdit = (report) => {
    editingId.value = report.id;
    editForm.name = report.name;
    editForm.filters = { ...defaultFilters(), ...(report.filters ?? {}) };
    editForm.schedule_preset = report.schedule_preset ?? 'none';
    editForm.schedule_cron = report.schedule_cron ?? '';
    editForm.email_recipients = report.email_recipients?.length ? [...report.email_recipients] : [];
    editForm.status = report.status ?? 'active';
    editRecipientInput.value = '';
};

const submitEdit = () => {
    applyPresetCron(editForm);
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
                    <InputLabel value="Schedule" />
                    <select v-model="createForm.schedule_preset" class="form-select mt-1 w-full max-w-md">
                        <option v-for="preset in presetOptions" :key="preset.value" :value="preset.value">{{ preset.label }}</option>
                    </select>
                    <input
                        v-if="createForm.schedule_preset === 'custom'"
                        v-model="createForm.schedule_cron"
                        type="text"
                        class="form-input mt-2 w-full max-w-md font-mono text-sm"
                        placeholder="0 7 * * 1"
                    />
                    <p class="mt-1 text-xs text-slate-500">Scheduled reports email a CSV to recipients below.</p>
                </div>

                <div>
                    <InputLabel value="Email recipients" />
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span
                            v-for="email in createForm.email_recipients"
                            :key="`create-chip-${email}`"
                            class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-3 py-1 text-xs font-medium text-indigo-800 dark:bg-indigo-500/15 dark:text-indigo-300"
                        >
                            {{ email }}
                            <button type="button" class="text-indigo-600 hover:text-rose-600" @click="removeRecipient(createForm, email)">×</button>
                        </span>
                    </div>
                    <div class="mt-2 flex max-w-md gap-2">
                        <input
                            v-model="createRecipientInput"
                            type="email"
                            class="form-input w-full"
                            placeholder="reports@company.com"
                            @keydown.enter.prevent="addCreateRecipient"
                        />
                        <AppButton type="button" variant="secondary" @click="addCreateRecipient">Add</AppButton>
                    </div>
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

                <div>
                    <InputLabel value="Schedule" />
                    <select v-model="editForm.schedule_preset" class="form-select mt-1 w-full max-w-md">
                        <option v-for="preset in presetOptions" :key="`edit-${preset.value}`" :value="preset.value">{{ preset.label }}</option>
                    </select>
                    <input
                        v-if="editForm.schedule_preset === 'custom'"
                        v-model="editForm.schedule_cron"
                        type="text"
                        class="form-input mt-2 w-full max-w-md font-mono text-sm"
                    />
                </div>

                <div>
                    <InputLabel value="Email recipients" />
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span
                            v-for="email in editForm.email_recipients"
                            :key="`edit-chip-${email}`"
                            class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-3 py-1 text-xs font-medium text-indigo-800 dark:bg-indigo-500/15 dark:text-indigo-300"
                        >
                            {{ email }}
                            <button type="button" class="text-indigo-600 hover:text-rose-600" @click="removeRecipient(editForm, email)">×</button>
                        </span>
                    </div>
                    <div class="mt-2 flex max-w-md gap-2">
                        <input
                            v-model="editRecipientInput"
                            type="email"
                            class="form-input w-full"
                            placeholder="reports@company.com"
                            @keydown.enter.prevent="addEditRecipient"
                        />
                        <AppButton type="button" variant="secondary" @click="addEditRecipient">Add</AppButton>
                    </div>
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
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Schedule</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Recipients</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Last run</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                </template>
                <tr v-for="report in reports.data" :key="report.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ report.name }}</td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                        <p>{{ scheduleLabel(report) }}</p>
                        <p v-if="report.next_run_at" class="mt-1 text-xs text-slate-500">Next: <FormattedDate :value="report.next_run_at" /></p>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex flex-wrap gap-1">
                            <span
                                v-for="email in report.email_recipients ?? []"
                                :key="`${report.id}-${email}`"
                                class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700 dark:bg-slate-700 dark:text-slate-300"
                            >
                                {{ email }}
                            </span>
                            <span v-if="!(report.email_recipients?.length)" class="text-xs text-slate-400">None</span>
                        </div>
                    </td>
                    <td class="px-6 py-4"><StatusBadge :status="report.status" /></td>
                    <td class="px-6 py-4">
                        <FormattedDate :value="report.last_run_at" />
                        <span
                            v-if="report.last_run_status"
                            class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold capitalize"
                            :class="lastRunTone(report.last_run_status)"
                        >
                            {{ report.last_run_status }}
                        </span>
                    </td>
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
