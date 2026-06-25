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
import EligibilityRulesEditor from '@/Components/UI/EligibilityRulesEditor.vue';
import { fieldOptionsFromCampaign } from '@/utils/campaignFields';
import { useFormSteps } from '@/Composables/useFormSteps';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    config: Object,
    campaigns: Array,
    routingModes: Array,
    filterFieldOptions: { type: Array, default: () => [] },
    campaignWorkflow: { type: Object, default: null },
});

const steps = [
    { id: 'config', label: 'Configuration', num: 1 },
    { id: 'tiers', label: 'Tiers', num: 2 },
];

const { currentStep, goStep, stepStatus, nextStep, prevStep } = useFormSteps(steps, {
    isEdit: !!props.config,
});

const defaultGroup = () => ({
    name: 'Tier 1',
    mode: 'waterfall',
    floor_price: null,
    delivery_ids: [],
    rules: { operator: 'and', conditions: [] },
});

const initialGroups = props.config?.config?.groups?.length
    ? props.config.config.groups.map((g) => ({
        name: g.name,
        mode: g.mode,
        floor_price: g.floor_price ?? null,
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
    props.campaigns.find((c) => String(c.id) === String(form.campaign_id))
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

const availableDeliveries = computed(() => selectedCampaign.value?.deliveries ?? []);

const tierFilterFieldOptions = computed(() => {
    const fromCampaign = fieldOptionsFromCampaign(selectedCampaign.value);
    return fromCampaign.length ? fromCampaign : (props.filterFieldOptions ?? []);
});

const addGroup = () => {
    form.groups.push({
        name: `Tier ${form.groups.length + 1}`,
        mode: 'waterfall',
        floor_price: null,
        delivery_ids: [],
        rules: { operator: 'and', conditions: [] },
    });
};

const removeGroup = (index) => {
    if (form.groups.length > 1) form.groups.splice(index, 1);
};

const toggleDelivery = (groupIndex, deliveryId) => {
    const ids = form.groups[groupIndex].delivery_ids;
    const idx = ids.indexOf(deliveryId);
    if (idx >= 0) ids.splice(idx, 1);
    else ids.push(deliveryId);
};

const submit = () => {
    if (props.config) {
        form.put(route('distribution.update', props.config.id));
    } else {
        form.post(route('distribution.store'));
    }
};
</script>

<template>
    <Head :title="config ? 'Edit Ping Tree' : 'New Ping Tree'" />
    <AuthenticatedLayout>
        <PageHeader
            :title="config ? 'Edit Ping Tree' : 'New Ping Tree'"
            description="Step-by-step setup — link a campaign, then configure unlimited tiers with routing and filters."
        />

        <CampaignWorkflowNav
            v-if="navWorkflow"
            :campaign="navWorkflow.campaign"
            :distribution-config-id="navWorkflow.distributionConfigId"
            :tenant-hub="navWorkflow.tenantHub"
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
                            <dd class="font-medium">{{ form.is_active ? 'Active' : 'Inactive' }}</dd>
                        </div>
                    </dl>
                </Panel>
            </template>

            <form class="space-y-6" @submit.prevent="submit">
                <FormErrorSummary :errors="form.errors" />

                <Panel v-show="currentStep === 'config'" title="1. Configuration">
                    <div class="space-y-4">
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
                    </div>
                    <div class="mt-4 flex justify-end">
                        <AppButton type="button" @click="nextStep">Next: Tiers →</AppButton>
                    </div>
                </Panel>

                <template v-if="currentStep === 'tiers'">
                    <Panel
                        v-for="(group, index) in form.groups"
                        :key="index"
                        :title="`Tier ${index + 1}: ${group.name}`"
                    >
                        <div v-if="form.groups.length > 1" class="mb-4 flex justify-end">
                            <button type="button" class="text-sm text-rose-500 hover:text-rose-400" @click="removeGroup(index)">Remove tier</button>
                        </div>

                        <div class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <InputLabel value="Tier name" />
                                    <TextInput v-model="group.name" class="mt-1 w-full" required />
                                </div>
                                <div>
                                    <InputLabel value="Routing mode" />
                                    <select v-model="group.mode" class="form-select mt-1 w-full">
                                        <option v-for="m in routingModes" :key="m.value" :value="m.value">{{ m.label }}</option>
                                    </select>
                                </div>
                            </div>
                            <div v-if="group.mode === 'parallel_auction'" class="max-w-xs">
                                <InputLabel value="Floor price" />
                                <TextInput v-model="group.floor_price" type="number" step="0.01" min="0" class="mt-1 w-full" />
                            </div>
                            <div>
                                <InputLabel value="Deliveries in this tier" />
                                <p v-if="!form.campaign_id" class="mt-1 text-sm text-slate-500">Select a campaign first.</p>
                                <p v-else-if="!availableDeliveries.length" class="mt-1 text-sm text-amber-600">No deliveries for this campaign. Create deliveries first.</p>
                                <div v-else class="mt-2 flex flex-wrap gap-2">
                                    <button
                                        v-for="d in availableDeliveries"
                                        :key="d.id"
                                        type="button"
                                        :class="[
                                            'rounded-lg border px-3 py-1.5 text-sm font-medium transition',
                                            group.delivery_ids.includes(d.id)
                                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                                                : 'border-slate-200 text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-400',
                                        ]"
                                        @click="toggleDelivery(index, d.id)"
                                    >
                                        {{ d.name }}
                                        <span class="ml-1 text-xs opacity-70">({{ d.method?.replace(/_/g, ' ') }})</span>
                                    </button>
                                </div>
                            </div>
                            <div class="border-t border-slate-200 pt-4 dark:border-slate-700">
                                <InputLabel value="Tier entry filters" />
                                <div class="mt-2">
                                    <EligibilityRulesEditor
                                        v-model="group.rules"
                                        scope="tier"
                                        :field-options="tierFilterFieldOptions"
                                    />
                                </div>
                            </div>
                        </div>
                    </Panel>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap gap-3">
                            <AppButton type="button" variant="secondary" @click="prevStep">← Back</AppButton>
                            <AppButton type="button" variant="secondary" @click="addGroup">+ Add tier</AppButton>
                        </div>
                        <PrimaryButton :disabled="form.processing" :loading="form.processing">
                            {{ config ? 'Update' : 'Create' }} Configuration
                        </PrimaryButton>
                    </div>
                </template>
            </form>
        </FormSetupLayout>
    </AuthenticatedLayout>
</template>
