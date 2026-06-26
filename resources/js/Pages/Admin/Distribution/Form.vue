<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import FormSetupLayout from '@/Components/UI/FormSetupLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import TextInput from '@/Components/TextInput.vue';
import CampaignWorkflowNav from '@/Components/UI/CampaignWorkflowNav.vue';
import PingTreeBuilder from '@/Components/UI/PingTreeBuilder.vue';
import { useFormSteps } from '@/Composables/useFormSteps';
import { fieldOptionsFromCampaign } from '@/utils/campaignFields';
import { pushToast } from '@/Composables/useToast';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    config: Object,
    campaigns: Array,
    routingModes: Array,
    filterFieldOptions: { type: Array, default: () => [] },
    campaignWorkflow: { type: Object, default: null },
});

const isLocked = computed(() => Boolean(props.config?.is_locked));

const lockForm = useForm({ locked: false });

const toggleLock = () => {
    if (!props.config) {
        return;
    }

    const nextLocked = !isLocked.value;

    if (!nextLocked) {
        const unlock = [
            'Unlock this ping tree for editing?',
            '',
            'Changing tiers, routing modes, or delivery order may affect live lead distribution immediately.',
        ].join('\n');

        if (!window.confirm(unlock)) {
            return;
        }
    }

    lockForm.locked = nextLocked;
    lockForm.post(route('distribution.lock', props.config.id), {
        preserveScroll: true,
    });
};

const steps = [
    { id: 'config', label: 'Configuration', num: 1 },
    { id: 'tiers', label: 'Tiers', num: 2 },
];

const { currentStep, goStep, stepStatus, nextStep, prevStep } = useFormSteps(steps, {
    isEdit: !!props.config,
});

if (props.config) {
    currentStep.value = 'tiers';
}

const defaultGroup = () => ({
    name: 'Tier 1',
    mode: 'waterfall',
    floor_price: null,
    redirect_url: null,
    delivery_ids: [],
    rules: { operator: 'and', conditions: [] },
});

const initialGroups = props.config?.config?.groups?.length
    ? props.config.config.groups.map((g) => ({
        name: g.name,
        mode: g.mode,
        floor_price: g.floor_price ?? null,
        redirect_url: g.redirect_url ?? null,
        delivery_ids: g.delivery_ids ?? [],
        rules: g.rules ?? { operator: 'and', conditions: [] },
    }))
    : [defaultGroup()];

const form = useForm({
    campaign_id: props.config?.campaign_id ?? new URLSearchParams(window.location.search).get('campaign_id') ?? '',
    name: props.config?.name ?? '',
    is_active: props.config?.is_active ?? true,
    groups: initialGroups,
});

const selectedCampaign = computed(() =>
    props.campaigns.find((c) => String(c.id) === String(form.campaign_id)),
);

const navWorkflow = computed(() => {
    if (props.campaignWorkflow?.campaign) {
        return props.campaignWorkflow;
    }

    const c = selectedCampaign.value;
    if (!c) {
        return null;
    }

    return {
        campaign: { id: c.id, name: c.name, reference: c.reference },
        distributionConfigId: props.config?.id ?? null,
        tenantHub: null,
    };
});

const availableDeliveries = computed(() =>
    (selectedCampaign.value?.deliveries ?? []).map((d) => ({
        id: d.id,
        name: d.name,
        method: typeof d.method === 'object' ? d.method?.value ?? d.method : d.method,
        buyer: d.buyer?.name ?? d.buyer_name ?? null,
    })),
);

const tierFilterFieldOptions = computed(() => {
    const fromCampaign = fieldOptionsFromCampaign(selectedCampaign.value);
    return fromCampaign.length ? fromCampaign : (props.filterFieldOptions ?? []);
});

const submit = () => {
    if (isLocked.value) {
        return;
    }

    const successMessage = props.config
        ? 'Ping tree configuration updated.'
        : 'Ping tree configuration created.';

    const options = {
        onSuccess: () => pushToast(successMessage, 'success'),
        onError: () => pushToast('Could not save ping tree — check the highlighted fields.', 'error'),
    };

    if (props.config) {
        form.put(route('distribution.update', props.config.id), options);
    } else {
        form.post(route('distribution.store'), options);
    }
};
</script>

<template>
    <Head :title="config ? 'Edit Ping Tree' : 'New Ping Tree'" />
    <AuthenticatedLayout>
        <PageHeader
            :title="config ? 'Edit Ping Tree' : 'New Ping Tree'"
            description="Step-by-step setup — link a campaign, then visually build tiers with drag-and-drop."
        >
            <template v-if="config" #actions>
                <button
                    type="button"
                    class="rounded-lg border px-3 py-2 text-sm font-medium transition"
                    :class="isLocked
                        ? 'border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100'
                        : 'border-slate-200 text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'"
                    :disabled="lockForm.processing"
                    @click="toggleLock"
                >
                    {{ isLocked ? 'Unlock ping tree' : 'Lock ping tree' }}
                </button>
            </template>
        </PageHeader>

        <div
            v-if="isLocked"
            class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
        >
            <strong>Ping tree is locked.</strong> Tier layout and settings are read-only. Unlock to edit or save changes.
        </div>

        <CampaignWorkflowNav
            v-if="navWorkflow"
            :campaign="navWorkflow.campaign"
            :distribution-config-id="navWorkflow.distributionConfigId"
            current="ping-tree"
            class="mb-6"
        />

        <FormSetupLayout :steps="steps" :current-step="currentStep" :step-status="stepStatus" @go="goStep">
            <template #sidebar>
                <Panel title="Summary" class="mt-4">
                    <dl class="space-y-2 text-sm">
                        <div v-if="selectedCampaign">
                            <dt class="text-slate-500">Campaign</dt>
                            <dd class="font-medium">{{ selectedCampaign.name }}</dd>
                        </div>
                        <div v-if="form.name">
                            <dt class="text-slate-500">Config name</dt>
                            <dd class="font-medium">{{ form.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Tiers</dt>
                            <dd class="font-medium">{{ form.groups.length }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Status</dt>
                            <dd class="font-medium">{{ form.is_active ? 'Active' : 'Inactive' }}{{ isLocked ? ' · Locked' : '' }}</dd>
                        </div>
                    </dl>
                </Panel>

                <Panel
                    v-if="currentStep === 'tiers' && form.campaign_id"
                    title="Buyers & deliveries"
                    class="mt-4"
                >
                    <p class="mb-3 text-xs text-slate-500">
                        Drag buyers into tiers on the right. Drop back here to unassign.
                    </p>
                    <div id="ping-tree-deliveries-sidebar" />
                </Panel>
            </template>

            <form class="space-y-6" @submit.prevent="submit">
                <FormErrorSummary :errors="form.errors" />

                <Panel v-if="currentStep === 'config'" title="1. Configuration">
                    <fieldset :disabled="isLocked" class="space-y-4 disabled:opacity-60">
                        <div>
                            <InputLabel value="Campaign" />
                            <select v-model="form.campaign_id" class="form-select mt-1 w-full" required>
                                <option value="" disabled>Select campaign</option>
                                <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }} ({{ c.reference }})</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.campaign_id" />
                        </div>
                        <div>
                            <InputLabel value="Configuration name" />
                            <TextInput v-model="form.name" class="mt-1 w-full" placeholder="e.g. Hybrid Ping Tree" required />
                            <InputError class="mt-1" :message="form.errors.name" />
                        </div>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-indigo-600" />
                            Active (use when campaign has advanced distribution enabled)
                        </label>
                    </fieldset>
                    <div class="mt-4 flex justify-end">
                        <AppButton type="button" :disabled="isLocked" @click="nextStep">Next: Tiers →</AppButton>
                    </div>
                </Panel>

                <template v-if="currentStep === 'tiers'">
                    <Panel title="2. Build ping tree" overflow-visible>
                        <p v-if="!form.campaign_id" class="text-sm text-slate-500">Select a campaign in step 1 first.</p>
                        <PingTreeBuilder
                            v-else
                            v-model:groups="form.groups"
                            :deliveries="availableDeliveries"
                            :routing-modes="routingModes"
                            :filter-field-options="tierFilterFieldOptions"
                            :campaign-name="selectedCampaign?.name ?? ''"
                            :readonly="isLocked"
                            deliveries-teleport="#ping-tree-deliveries-sidebar"
                        />
                    </Panel>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <AppButton type="button" variant="secondary" @click="prevStep">← Back</AppButton>
                        <PrimaryButton :disabled="form.processing || isLocked" :loading="form.processing">
                            {{ config ? 'Update' : 'Create' }} Configuration
                        </PrimaryButton>
                    </div>
                </template>
            </form>
        </FormSetupLayout>
    </AuthenticatedLayout>
</template>
