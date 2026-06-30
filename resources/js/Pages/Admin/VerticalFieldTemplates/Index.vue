<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    templates: Object,
    verticals: Array,
});

const showCreate = ref(false);

const createForm = useForm({
    vertical_id: props.verticals?.[0]?.id ?? '',
    name: '',
    fields: [{ name: 'email', label: 'Email', type: 'email', required: true }],
});

const addField = () => {
    createForm.fields.push({ name: '', label: '', type: 'text', required: false });
};

const removeField = (index) => {
    createForm.fields.splice(index, 1);
};

const submitCreate = () => {
    createForm.post(route('vertical-field-templates.store'), {
        onSuccess: () => {
            createForm.reset();
            createForm.fields = [{ name: 'email', label: 'Email', type: 'email', required: true }];
            showCreate.value = false;
        },
    });
};

const wizardUrl = (template) => route('vertical-field-templates.apply-wizard', { template_id: template.id });
</script>

<template>
    <Head title="Field templates" />
    <AuthenticatedLayout>
        <PageHeader title="Vertical field templates" description="Reusable campaign field sets — apply via the wizard with preview and confirmation.">
            <template #actions>
                <AppButton @click="showCreate = !showCreate">{{ showCreate ? 'Cancel' : 'New template' }}</AppButton>
            </template>
        </PageHeader>

        <Panel v-if="showCreate" title="Create template" class="mb-6">
            <form class="space-y-4" @submit.prevent="submitCreate">
                <FormErrorSummary :errors="createForm.errors" />

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel value="Vertical" />
                        <select v-model="createForm.vertical_id" class="form-select mt-1 w-full" required>
                            <option v-for="v in verticals" :key="v.id" :value="v.id">{{ v.label }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Template name" />
                        <input v-model="createForm.name" type="text" class="form-input mt-1 w-full" required />
                    </div>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <InputLabel value="Fields" />
                        <button type="button" class="text-xs font-medium text-indigo-600 hover:underline" @click="addField">+ Add field</button>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="(field, index) in createForm.fields"
                            :key="index"
                            class="grid gap-2 rounded-lg border border-slate-200 p-3 md:grid-cols-[1fr_1fr_8rem_5rem_auto] dark:border-slate-700"
                        >
                            <input v-model="field.name" type="text" class="form-input" placeholder="name" required />
                            <input v-model="field.label" type="text" class="form-input" placeholder="Label" />
                            <input v-model="field.type" type="text" class="form-input" placeholder="type" />
                            <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                                <input v-model="field.required" type="checkbox" class="rounded border-slate-300" />
                                Required
                            </label>
                            <button v-if="createForm.fields.length > 1" type="button" class="text-xs text-rose-600 hover:underline" @click="removeField(index)">Remove</button>
                        </div>
                    </div>
                </div>

                <PrimaryButton :disabled="createForm.processing">Create template</PrimaryButton>
            </form>
        </Panel>

        <Panel title="Templates" :padding="false">
            <DataTable :empty="!templates?.data?.length">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Vertical</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Fields</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                </template>
                <tr v-for="template in templates.data" :key="template.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ template.name }}</td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ template.vertical_id }}</td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ template.fields?.length ?? 0 }} fields</td>
                    <td class="px-6 py-4 text-right">
                        <AppButton variant="ghost" :href="wizardUrl(template)">Apply wizard</AppButton>
                    </td>
                </tr>
            </DataTable>
            <Pagination :links="templates.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
