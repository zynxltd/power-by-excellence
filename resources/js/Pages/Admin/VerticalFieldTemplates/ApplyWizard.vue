<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import CampaignWorkflowNav from '@/Components/UI/CampaignWorkflowNav.vue';
import { useFormSteps } from '@/Composables/useFormSteps';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    campaign: { type: Object, default: null },
    templates: { type: Array, default: () => [] },
    verticals: { type: Array, default: () => [] },
    preselectedTemplateId: { type: Number, default: null },
    campaignWorkflow: { type: Object, default: null },
    defaultStrategy: { type: String, default: 'replace-all' },
});

const steps = [
    { id: 'template', label: 'Template', num: 1 },
    { id: 'preview', label: 'Preview', num: 2 },
    { id: 'confirm', label: 'Confirm', num: 3 },
];

const { currentStep, goStep, stepStatus, nextStep, prevStep } = useFormSteps(steps);

const selectedTemplateId = ref(props.preselectedTemplateId || props.templates?.[0]?.id || null);
const selectedCampaignId = ref(props.campaign?.id ? String(props.campaign.id) : '');
const strategy = ref(props.defaultStrategy);
const preview = ref(null);
const previewError = ref('');
const previewLoading = ref(false);

const applyForm = useForm({
    campaign_id: props.campaign?.id ?? '',
    strategy: props.defaultStrategy,
});

const selectedTemplate = computed(() => props.templates.find((template) => template.id === selectedTemplateId.value) ?? null);

const verticalLabel = (verticalId) => props.verticals.find((vertical) => vertical.id === verticalId)?.label ?? verticalId;

const canContinueFromTemplate = computed(() => Boolean(selectedTemplateId.value && selectedCampaignId.value));

watch(selectedCampaignId, (value) => {
    applyForm.campaign_id = value ? Number(value) : '';
});

watch(strategy, (value) => {
    applyForm.strategy = value;
});

const loadPreview = async () => {
    previewError.value = '';
    previewLoading.value = true;
    preview.value = null;

    try {
        const response = await fetch(route('vertical-field-templates.preview', selectedTemplateId.value), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                campaign_id: Number(selectedCampaignId.value),
                strategy: strategy.value,
            }),
        });

        const payload = await response.json();

        if (!response.ok) {
            previewError.value = payload?.message
                ?? Object.values(payload?.errors ?? {}).flat().join(' ')
                ?? 'Unable to load preview.';
            return;
        }

        preview.value = payload;
        goStep('preview');
    } catch {
        previewError.value = 'Unable to load preview.';
    } finally {
        previewLoading.value = false;
    }
};

const goToConfirm = () => {
    if (!preview.value) {
        return;
    }

    applyForm.campaign_id = Number(selectedCampaignId.value);
    applyForm.strategy = strategy.value;
    goStep('confirm');
};

const submitApply = () => {
    applyForm.post(route('vertical-field-templates.apply', selectedTemplateId.value), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Apply field template" />
    <AuthenticatedLayout>
        <PageHeader
            title="Apply field template"
            :description="campaign ? `Update fields on ${campaign.name}` : 'Pick a template and campaign, preview changes, then confirm.'"
        >
            <template #actions>
                <AppButton
                    v-if="campaign"
                    variant="secondary"
                    :href="route('campaigns.show', campaign.id)"
                >
                    Back to campaign
                </AppButton>
                <AppButton variant="secondary" :href="route('vertical-field-templates.index')">All templates</AppButton>
            </template>
        </PageHeader>

        <CampaignWorkflowNav
            v-if="campaignWorkflow"
            :campaign="campaignWorkflow.campaign"
            :distribution-config-id="campaignWorkflow.distributionConfigId"
            current="show"
            class="mb-6"
        />

        <div class="mb-6 flex flex-wrap gap-2">
            <button
                v-for="step in steps"
                :key="step.id"
                type="button"
                class="rounded-full px-3 py-1 text-xs font-semibold transition"
                :class="currentStep === step.id
                    ? 'bg-indigo-600 text-white'
                    : stepStatus(step.id) === 'complete'
                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'
                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                @click="goStep(step.id)"
            >
                {{ step.num }}. {{ step.label }}
            </button>
        </div>

        <Panel v-show="currentStep === 'template'" title="1. Choose template and campaign">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <InputLabel value="Campaign ID" />
                    <input
                        v-model="selectedCampaignId"
                        type="number"
                        class="form-input mt-1 w-full"
                        :readonly="Boolean(campaign)"
                        required
                    />
                    <p v-if="campaign" class="mt-1 text-xs text-slate-500">
                        {{ campaign.name }} · {{ campaign.reference }} · {{ verticalLabel(campaign.vertical_id) }}
                    </p>
                </div>
                <div>
                    <InputLabel value="Merge strategy" />
                    <select v-model="strategy" class="form-select mt-1 w-full">
                        <option value="replace-all">Replace all fields (default)</option>
                        <option value="merge-by-name">Merge by field name</option>
                    </select>
                </div>
            </div>

            <div class="mt-6">
                <InputLabel value="Template" />
                <div v-if="!templates.length" class="mt-2 rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-500 dark:border-slate-700">
                    No templates match this campaign vertical. Create one first.
                </div>
                <div v-else class="mt-2 grid gap-3 md:grid-cols-2">
                    <label
                        v-for="template in templates"
                        :key="template.id"
                        class="cursor-pointer rounded-xl border p-4 transition"
                        :class="selectedTemplateId === template.id
                            ? 'border-indigo-500 bg-indigo-50/60 dark:border-indigo-400 dark:bg-indigo-500/10'
                            : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'"
                    >
                        <input v-model="selectedTemplateId" class="sr-only" type="radio" :value="template.id" />
                        <p class="font-medium text-slate-900 dark:text-white">{{ template.name }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ verticalLabel(template.vertical_id) }} · {{ template.fields?.length ?? 0 }} fields</p>
                    </label>
                </div>
            </div>

            <p v-if="previewError" class="mt-4 text-sm text-rose-600">{{ previewError }}</p>

            <div class="mt-6 flex justify-end">
                <PrimaryButton :disabled="!canContinueFromTemplate || previewLoading" @click="loadPreview">
                    {{ previewLoading ? 'Loading preview…' : 'Preview changes →' }}
                </PrimaryButton>
            </div>
        </Panel>

        <Panel v-show="currentStep === 'preview'" title="2. Preview diff">
            <p v-if="!preview" class="text-sm text-slate-500">Load a preview from step 1 first.</p>
            <template v-else>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Strategy: <strong>{{ preview.strategy === 'merge-by-name' ? 'Merge by name' : 'Replace all' }}</strong>
                    for <strong>{{ selectedTemplate?.name }}</strong>.
                </p>

                <div class="mt-4 grid gap-4 lg:grid-cols-3">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-800 dark:bg-emerald-950/20">
                        <h4 class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">Add ({{ preview.to_add?.length ?? 0 }})</h4>
                        <ul class="mt-2 space-y-1 text-xs text-slate-700 dark:text-slate-300">
                            <li v-for="field in preview.to_add ?? []" :key="`add-${field.name}`" class="font-mono">{{ field.name }}</li>
                            <li v-if="!(preview.to_add?.length)" class="text-slate-500">None</li>
                        </ul>
                    </div>
                    <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-800 dark:bg-amber-950/20">
                        <h4 class="text-sm font-semibold text-amber-800 dark:text-amber-300">Replace ({{ preview.to_replace?.length ?? 0 }})</h4>
                        <ul class="mt-2 space-y-1 text-xs text-slate-700 dark:text-slate-300">
                            <li v-for="item in preview.to_replace ?? []" :key="`replace-${item.before.name}`" class="font-mono">{{ item.before.name }}</li>
                            <li v-if="!(preview.to_replace?.length)" class="text-slate-500">None</li>
                        </ul>
                    </div>
                    <div class="rounded-xl border border-rose-200 bg-rose-50/50 p-4 dark:border-rose-800 dark:bg-rose-950/20">
                        <h4 class="text-sm font-semibold text-rose-800 dark:text-rose-300">Remove ({{ preview.to_remove?.length ?? 0 }})</h4>
                        <ul class="mt-2 space-y-1 text-xs text-slate-700 dark:text-slate-300">
                            <li v-for="field in preview.to_remove ?? []" :key="`remove-${field.name}`" class="font-mono">{{ field.name }}</li>
                            <li v-if="!(preview.to_remove?.length)" class="text-slate-500">None</li>
                        </ul>
                    </div>
                </div>

                <div v-if="preview.to_replace?.length" class="mt-4 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50">
                            <tr>
                                <th class="px-4 py-2">Field</th>
                                <th class="px-4 py-2">Before</th>
                                <th class="px-4 py-2">After</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in preview.to_replace" :key="`detail-${item.before.name}`" class="border-t border-slate-100 dark:border-slate-800">
                                <td class="px-4 py-2 font-mono text-xs">{{ item.before.name }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ item.before.label }} · {{ item.before.type }}</td>
                                <td class="px-4 py-2 text-xs text-slate-700 dark:text-slate-300">{{ item.after.label }} · {{ item.after.type }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <div class="mt-6 flex justify-between gap-3">
                <AppButton type="button" variant="secondary" @click="prevStep">← Back</AppButton>
                <PrimaryButton :disabled="!preview" @click="goToConfirm">Continue to confirm →</PrimaryButton>
            </div>
        </Panel>

        <Panel v-show="currentStep === 'confirm'" title="3. Confirm apply">
            <FormErrorSummary :errors="applyForm.errors" />
            <p class="text-sm text-slate-600 dark:text-slate-400">
                Apply <strong>{{ selectedTemplate?.name }}</strong> to campaign
                <strong>#{{ selectedCampaignId }}</strong> using
                <strong>{{ strategy === 'merge-by-name' ? 'merge by name' : 'replace all fields' }}</strong>.
            </p>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-600 dark:text-slate-400">
                <li>{{ preview?.to_add?.length ?? 0 }} field(s) to add</li>
                <li>{{ preview?.to_replace?.length ?? 0 }} field(s) to replace</li>
                <li>{{ preview?.to_remove?.length ?? 0 }} field(s) to remove</li>
            </ul>

            <div class="mt-6 flex justify-between gap-3">
                <AppButton type="button" variant="secondary" @click="goStep('preview')">← Back to preview</AppButton>
                <PrimaryButton :disabled="applyForm.processing" @click="submitApply">
                    Apply template
                </PrimaryButton>
            </div>
        </Panel>
    </AuthenticatedLayout>
</template>
