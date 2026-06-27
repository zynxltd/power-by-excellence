<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    campaigns: Array,
    recentLeads: Array,
    filters: Object,
    simulation: Object,
    simulationInput: Object,
});

const selectedCampaign = computed(() =>
    props.campaigns?.find((c) => c.id === Number(form.campaign_id))
);

const { formatMoney } = useMoneyFormat();
const simCurrency = computed(() => selectedCampaign.value?.currency ?? props.simulation?.campaign?.currency);

const form = useForm({
    campaign_id: props.simulationInput?.campaign_id ?? props.filters?.campaign_id ?? '',
    lead_id: props.simulationInput?.lead_id ?? '',
    field_data: { ...(props.simulationInput?.field_data ?? {}) },
});

const fieldInputs = ref({});

const initFieldInputs = () => {
    const defaults = {
        firstname: 'Test',
        lastname: 'Lead',
        email: 'simulator@demo.test',
        phone1: '07700900123',
        zipcode: 'SW1A 1AA',
    };
    const fields = selectedCampaign.value?.fields ?? [];
    const next = {};
    for (const f of fields) {
        next[f.name] = form.field_data[f.name] ?? defaults[f.name] ?? '';
    }
    if (!fields.length) {
        Object.assign(next, form.field_data, defaults);
    }
    fieldInputs.value = next;
};

watch(selectedCampaign, initFieldInputs, { immediate: true });

const loadLead = () => {
    const lead = props.recentLeads?.find((l) => l.id === Number(form.lead_id));
    if (lead?.field_data) {
        fieldInputs.value = { ...fieldInputs.value, ...lead.field_data };
    }
};

watch(() => form.lead_id, loadLead);

const runSimulation = () => {
    form.field_data = { ...fieldInputs.value };
    form.post(route('routing.simulator.run'), { preserveScroll: true });
};

const modeLabel = (mode) => mode?.replace(/_/g, ' ');

const activeTier = ref(null);
const animating = ref(false);

watch(() => props.simulation, (sim) => {
    if (sim?.mode === 'advanced' && sim.steps?.length) {
        activeTier.value = 0;
        animating.value = true;
        let i = 0;
        const interval = setInterval(() => {
            activeTier.value = i;
            i++;
            if (i >= sim.steps.length) {
                clearInterval(interval);
                animating.value = false;
            }
        }, 600);
    }
}, { immediate: true });
</script>

<template>
    <Head title="Routing Simulator" />
    <AuthenticatedLayout>
        <PageHeader
            title="Routing Simulator"
            description="Dry-run routing decisions without sending real pings or posts."
        >
            <template #actions>
                <Link :href="route('features.routing')" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300">
                    ← Routing hub
                </Link>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-2">
            <Panel title="Simulation input">
                <div class="space-y-4">
                    <div>
                        <InputLabel for="campaign_id" value="Campaign" />
                        <select id="campaign_id" v-model="form.campaign_id" class="form-select mt-1 w-full" required>
                            <option value="">Select campaign</option>
                            <option v-for="c in campaigns" :key="c.id" :value="c.id">
                                {{ c.name }} ({{ c.use_advanced_distribution ? 'Ping Tree' : 'Standard' }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <InputLabel for="lead_id" value="Existing lead (optional)" />
                        <select id="lead_id" v-model="form.lead_id" class="form-select mt-1 w-full">
                            <option value="">Synthetic test lead</option>
                            <option v-for="l in recentLeads" :key="l.id" :value="l.id">
                                {{ l.uuid?.slice(0, 12) }}… - {{ l.campaign?.name }}
                            </option>
                        </select>
                    </div>

                    <div v-if="Object.keys(fieldInputs).length">
                        <p class="mb-2 text-xs font-semibold uppercase text-slate-500">Lead fields</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div v-for="(value, key) in fieldInputs" :key="key">
                                <label class="mb-1 block text-xs font-medium text-slate-500">{{ key }}</label>
                                <input v-model="fieldInputs[key]" type="text" class="form-input w-full" />
                            </div>
                        </div>
                    </div>

                    <AppButton :disabled="!form.campaign_id || form.processing" @click="runSimulation">
                        Run simulation
                    </AppButton>
                </div>
            </Panel>

            <Panel title="Results">
                <div v-if="simulation" class="space-y-4">
                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50">
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">Mode</p>
                            <p class="font-medium capitalize text-slate-900 dark:text-white">{{ simulation.mode }}</p>
                            <p v-if="simulation.config_name" class="text-sm text-slate-500">{{ simulation.config_name }}</p>
                        </div>
                        <span
                            :class="[
                                'rounded-full px-3 py-1 text-xs font-semibold',
                                simulation.would_sell
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                    : 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
                            ]"
                        >
                            {{ simulation.would_sell ? 'Would sell' : 'Would not sell' }}
                        </span>
                    </div>

                    <!-- Standard mode steps -->
                    <div v-if="simulation.mode === 'standard'" class="space-y-2">
                        <div
                            v-for="(step, i) in simulation.steps"
                            :key="i"
                            :class="[
                                'rounded-lg border px-4 py-3',
                                step.eligible
                                    ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/30'
                                    : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900',
                            ]"
                        >
                            <div class="flex items-center justify-between">
                                <p class="font-medium text-slate-900 dark:text-white">{{ step.delivery_name }}</p>
                                <StatusBadge :status="step.eligible ? 'active' : 'inactive'" />
                            </div>
                            <ul v-if="step.skip_reasons?.length" class="mt-2 list-inside list-disc text-xs text-slate-500">
                                <li v-for="(reason, ri) in step.skip_reasons" :key="ri">{{ reason }}</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Advanced mode tiers -->
                    <div v-else class="space-y-4">
                        <div class="flex gap-2 overflow-x-auto pb-2">
                            <button
                                v-for="(tier, ti) in simulation.steps"
                                :key="'tab-'+tier.tier"
                                type="button"
                                :class="[
                                    'shrink-0 rounded-full px-3 py-1 text-xs font-semibold transition',
                                    activeTier === ti
                                        ? 'bg-violet-600 text-white shadow-lg shadow-violet-500/30'
                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
                                ]"
                                @click="activeTier = ti"
                            >
                                T{{ tier.tier }}
                            </button>
                        </div>
                        <div
                            v-for="(tier, ti) in simulation.steps"
                            :key="tier.tier"
                            v-show="activeTier === null || activeTier === ti"
                            :class="[
                                'rounded-lg border transition-all duration-500',
                                tier.would_win
                                    ? 'border-emerald-300 ring-2 ring-emerald-400/40 dark:border-emerald-800'
                                    : 'border-slate-200 dark:border-slate-700',
                                animating && activeTier === ti ? 'scale-[1.01] shadow-lg' : '',
                            ]"
                        >
                            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                                <div>
                                    <span class="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-bold text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">
                                        Tier {{ tier.tier }}
                                    </span>
                                    <p class="mt-1 font-medium text-slate-900 dark:text-white">{{ tier.name }}</p>
                                </div>
                                <div class="text-right text-xs text-slate-500">
                                    <p class="capitalize">{{ modeLabel(tier.mode) }}</p>
                                    <p v-if="tier.floor_price" class="text-emerald-600 dark:text-emerald-400">Floor {{ formatMoney(tier.floor_price, { currency: simCurrency }) }}</p>
                                    <p v-if="tier.would_win" class="font-semibold text-emerald-600">Winner tier</p>
                                </div>
                            </div>
                            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                                <div
                                    v-for="(step, si) in tier.deliveries"
                                    :key="si"
                                    :class="[
                                        'px-4 py-3 transition',
                                        step.eligible ? 'bg-emerald-50/50 dark:bg-emerald-950/20' : '',
                                    ]"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ step.delivery_name }}</p>
                                            <p class="text-xs text-slate-500">{{ step.buyer }} · {{ step.method }}</p>
                                        </div>
                                        <div class="text-right">
                                            <span
                                                :class="[
                                                    'rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase',
                                                    step.eligible
                                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
                                                ]"
                                            >
                                                {{ step.eligible ? 'eligible' : 'skipped' }}
                                            </span>
                                            <p class="mt-1 text-xs font-semibold text-cyan-600 dark:text-cyan-400">
                                                Est. {{ formatMoney(step.estimated_revenue ?? 0, { currency: simCurrency }) }}
                                            </p>
                                        </div>
                                    </div>
                                    <ul v-if="step.skip_reasons?.length" class="mt-1 list-inside list-disc text-xs text-rose-500">
                                        <li v-for="(reason, ri) in step.skip_reasons" :key="ri">{{ reason }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-slate-600 dark:text-slate-400">
                    Select a campaign and run a simulation to see routing steps.
                </p>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
