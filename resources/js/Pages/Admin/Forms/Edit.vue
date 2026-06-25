<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    form: Object,
    fieldTypes: Array,
    apiSpec: Object,
    specFieldOptions: Array,
});

const defaultThankYou = () => ({
    mode: props.form.config?.thank_you?.mode ?? 'inline',
    title: props.form.config?.thank_you?.title ?? 'Thank you!',
    message: props.form.config?.thank_you?.message ?? 'Your enquiry has been received. We will be in touch shortly.',
    show_reference: props.form.config?.thank_you?.show_reference ?? true,
    button_text: props.form.config?.thank_you?.button_text ?? 'Submit another response',
    confetti: props.form.config?.thank_you?.confetti ?? true,
});

const defaultSteps = () => props.form.config?.steps?.length
    ? JSON.parse(JSON.stringify(props.form.config.steps))
    : [{
        id: 'step-1',
        title: 'Your details',
        description: 'Tell us about yourself',
        fields: [
            { name: 'firstname', label: 'First name', type: 'text', required: true, options: [] },
            { name: 'email', label: 'Email', type: 'email', required: true, options: [] },
        ],
    }];

const f = useForm({
    campaign_id: props.form.campaign_id,
    name: props.form.name,
    is_active: props.form.is_active ?? true,
    config: {
        redirect_url: props.form.config?.redirect_url ?? '',
        multi_step: props.form.config?.multi_step ?? true,
        steps: defaultSteps(),
        css: props.form.config?.css ?? '',
        thank_you: defaultThankYou(),
    },
});

const addStep = () => {
    f.config.steps.push({
        id: `step-${f.config.steps.length + 1}`,
        title: `Step ${f.config.steps.length + 1}`,
        description: '',
        fields: [],
    });
};

const addField = (step) => {
    step.fields.push({ name: `field_${Date.now()}`, label: 'New field', type: 'text', required: false, options: ['Option A', 'Option B'] });
};

const removeField = (step, idx) => step.fields.splice(idx, 1);
const removeStep = (idx) => f.config.steps.splice(idx, 1);

const importFromApiSpec = () => {
    if (!confirm('Replace current form fields with fields from the campaign API spec?')) return;
    router.post(route('campaigns.api-spec.apply-form', props.form.campaign_id), {
        hosted_form_id: props.form.id,
    });
};

const syncFieldFromSpec = (field) => {
    const specField = props.apiSpec?.fields?.find((sf) => sf.name === field.name);
    if (!specField) {
        alert(`"${field.name}" is not in the API spec. Pick a spec field or add it on the API Spec page.`);
        return;
    }
    field.label = specField.label;
    field.type = specField.form_type ?? field.type;
    field.required = specField.required;
    if (specField.enum?.length) field.options = [...specField.enum];
};

const addFieldFromSpec = (step, specName) => {
    const specField = props.specFieldOptions?.find((sf) => sf.name === specName);
    if (!specField) return;
    if (step.fields.some((f) => f.name === specField.name)) {
        alert('Field already on this step.');
        return;
    }
    step.fields.push({
        name: specField.name,
        label: specField.label,
        type: specField.type,
        required: specField.required,
        options: [],
    });
};
</script>

<template>
    <Head :title="`Edit Form — ${form.name}`" />
    <AuthenticatedLayout>
        <PageHeader :title="form.name" description="Multi-step form builder — sync fields from your API spec.">
            <template #actions>
                <Link :href="route('campaigns.api-spec', form.campaign_id)" class="text-sm text-violet-600 hover:text-violet-500">API Spec →</Link>
                <a :href="route('forms.show', form.slug)" target="_blank" class="ml-4 text-sm text-indigo-600">Preview ↗</a>
            </template>
        </PageHeader>

        <form class="space-y-6" @submit.prevent="f.put(route('forms.update', form.id))">
            <Panel title="Thank you & submission">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium">After submit</label>
                        <select v-model="f.config.thank_you.mode" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-800">
                            <option value="inline">Show thank you message on this page</option>
                            <option value="redirect">Redirect to external URL</option>
                        </select>
                    </div>
                    <div v-if="f.config.thank_you.mode === 'redirect'">
                        <label class="text-sm font-medium">Redirect URL</label>
                        <input v-model="f.config.redirect_url" type="url" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-800" placeholder="https://yoursite.com/thanks" required />
                    </div>
                    <template v-if="f.config.thank_you.mode === 'inline'">
                        <div>
                            <label class="text-sm font-medium">Thank you title</label>
                            <input v-model="f.config.thank_you.title" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-800" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm font-medium">Thank you message</label>
                            <textarea v-model="f.config.thank_you.message" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-800" />
                        </div>
                        <label class="flex items-center gap-2 text-sm"><input v-model="f.config.thank_you.show_reference" type="checkbox" class="rounded" /> Show queue reference ID</label>
                        <label class="flex items-center gap-2 text-sm"><input v-model="f.config.thank_you.confetti" type="checkbox" class="rounded" /> Celebration animation</label>
                        <div>
                            <label class="text-sm font-medium">Submit another button</label>
                            <input v-model="f.config.thank_you.button_text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-800" />
                        </div>
                    </template>
                </div>
            </Panel>

            <Panel title="API spec integration">
                <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                    Form fields must match the campaign API spec so hosted forms and API ingest validate the same data.
                    Use <strong>↻ Sync from spec</strong> on a row to pull label, type, and required flag from the spec field with the same name.
                </p>
                <div class="flex flex-wrap gap-3">
                    <AppButton type="button" @click="importFromApiSpec">Replace all fields from API spec</AppButton>
                    <Link :href="route('campaigns.api-spec', form.campaign_id)" class="self-center text-sm text-indigo-600 hover:underline">Edit API spec</Link>
                </div>
                <div v-if="specFieldOptions?.length" class="mt-4">
                    <p class="mb-2 text-xs font-semibold uppercase text-slate-500">Available spec fields</p>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="sf in specFieldOptions"
                            :key="sf.name"
                            class="rounded-lg border border-violet-200 bg-violet-50 px-2 py-1 font-mono text-xs text-violet-800 dark:border-violet-900 dark:bg-violet-950/40 dark:text-violet-300"
                            :title="sf.required ? 'Required in API' : 'Optional in API'"
                        >
                            {{ sf.name }}<span v-if="sf.required" class="text-rose-500">*</span>
                        </span>
                    </div>
                </div>
            </Panel>

            <Panel title="Settings">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium">Form name</label>
                        <input v-model="f.name" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-800" required />
                    </div>
                    <label class="flex items-center gap-2 text-sm md:col-span-2">
                        <input v-model="f.config.multi_step" type="checkbox" class="rounded" />
                        Multi-step flow (progress bar + Next/Back)
                    </label>
                </div>
            </Panel>

            <div v-for="(step, si) in f.config.steps" :key="step.id" class="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                    <div class="grid flex-1 gap-2 md:grid-cols-2">
                        <input v-model="step.title" class="rounded-lg border border-slate-200 px-3 py-2 font-semibold dark:border-slate-700 dark:bg-slate-800" placeholder="Step title" />
                        <input v-model="step.description" class="rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800" placeholder="Step description" />
                    </div>
                    <button v-if="f.config.steps.length > 1" type="button" class="ml-4 text-sm text-rose-500" @click="removeStep(si)">Remove</button>
                </div>
                <div class="space-y-4 p-6">
                    <div v-for="(field, fi) in step.fields" :key="fi" class="grid gap-3 rounded-xl border p-4 dark:border-slate-800 md:grid-cols-6" :class="!specFieldOptions?.some((sf) => sf.name === field.name) && field.name ? 'border-amber-300 bg-amber-50/50 dark:border-amber-800' : 'border-slate-100'">
                        <input v-model="field.label" class="rounded-lg border px-3 py-2 text-sm md:col-span-2 dark:border-slate-700 dark:bg-slate-800" placeholder="Label" />
                        <input v-model="field.name" class="rounded-lg border px-3 py-2 font-mono text-sm dark:border-slate-700 dark:bg-slate-800" placeholder="field_name" list="spec-field-names" />
                        <datalist id="spec-field-names">
                            <option v-for="sf in specFieldOptions" :key="sf.name" :value="sf.name" />
                        </datalist>
                        <select v-model="field.type" class="rounded-lg border px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                            <option v-for="t in fieldTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <label class="flex items-center gap-2 text-sm"><input v-model="field.required" type="checkbox" /> Required</label>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="text-xs text-violet-600 underline" title="Copy label, type, and required from API spec field with same name" @click="syncFieldFromSpec(field)">↻ Sync from spec</button>
                            <button type="button" class="text-sm text-rose-500" @click="removeField(step, fi)">Remove</button>
                        </div>
                        <p v-if="field.name && !specFieldOptions?.some((sf) => sf.name === field.name)" class="md:col-span-6 text-xs text-amber-700 dark:text-amber-400">Not in API spec — submissions may fail validation.</p>
                        <div v-if="['radio', 'select', 'checkbox'].includes(field.type)" class="md:col-span-6">
                            <label class="text-xs text-slate-500">Options (one per line)</label>
                            <textarea :value="(field.options || []).join('\n')" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2 font-mono text-sm dark:border-slate-700 dark:bg-slate-800" @input="field.options = $event.target.value.split('\n').filter(Boolean)" />
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <AppButton type="button" variant="secondary" @click="addField(step)">+ Add blank field</AppButton>
                        <select class="form-select text-sm" @change="(e) => { if (e.target.value) { addFieldFromSpec(step, e.target.value); e.target.value = ''; } }">
                            <option value="">+ Add from API spec…</option>
                            <option v-for="sf in specFieldOptions" :key="sf.name" :value="sf.name">{{ sf.label }} ({{ sf.name }})</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <AppButton type="button" @click="addStep">+ Add step</AppButton>
                <PrimaryButton :disabled="f.processing">Save form</PrimaryButton>
                <Link :href="route('forms.index')" class="self-center text-sm text-slate-500">← Back</Link>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
