<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import DeliveryMethodBadge from '@/Components/UI/DeliveryMethodBadge.vue';
import PingTreeTierTable from '@/Components/UI/PingTreeTierTable.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import CampaignWorkflowNav from '@/Components/UI/CampaignWorkflowNav.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { routingModeLabel } from '@/utils/routingModes';
import { hasActiveRules, rulesSummaryText } from '@/utils/ruleFormat';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    config: Object,
    tiers: Array,
    campaign: Object,
    campaignWorkflow: { type: Object, default: null },
});

const { formatMoney } = useMoneyFormat(props.campaign?.currency);

const viewMode = ref((props.tiers?.length ?? 0) > 10 ? 'table' : 'flow');

const tierGroups = computed(() =>
    (props.tiers ?? []).map((tier) => ({
        name: tier.name,
        mode: tier.mode,
        floor_price: tier.floor_price,
        delivery_ids: tier.deliveries?.map((d) => d.id) ?? [],
        rules: tier.rules,
    })),
);
</script>

<template>
    <Head :title="config.name" />
    <AuthenticatedLayout>
        <PageHeader :title="config.name" description="Visual ping tree — tiered routing with delivery nodes.">
            <template #actions>
                <StatusBadge :status="config.is_active ? 'active' : 'inactive'" />
                <StatusBadge v-if="config.is_locked" status="locked" />
                <AppButton variant="secondary" :href="route('distribution.index')">All configs</AppButton>
                <AppButton :href="route('distribution.edit', config.id)">
                    {{ config.is_locked ? 'View (locked)' : 'Edit' }}
                </AppButton>
            </template>
        </PageHeader>

        <div
            v-if="config.is_locked"
            class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
        >
            This ping tree is <strong>locked</strong> to prevent accidental changes. Unlock from the edit page to modify tiers.
        </div>

        <CampaignWorkflowNav
            v-if="campaignWorkflow"
            :campaign="campaignWorkflow.campaign"
            :distribution-config-id="campaignWorkflow.distributionConfigId"
            current="ping-tree"
            class="mb-6"
        />

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <Panel class="!p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</p>
                <Link
                    v-if="campaign"
                    :href="route('campaigns.show', campaign.id)"
                    class="mt-2 block text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                >
                    {{ campaign.name }} →
                </Link>
            </Panel>
            <Panel class="!p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Reference</p>
                <p class="mt-2 font-mono text-sm text-slate-700 dark:text-slate-300">{{ campaign?.reference }}</p>
            </Panel>
            <Panel class="!p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Tiers</p>
                <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">{{ tiers?.length ?? 0 }} configured</p>
            </Panel>
        </div>

        <Panel title="Ping tree">
            <template #header>
                <div v-if="(tiers?.length ?? 0) > 4" class="flex rounded-lg border border-slate-200 p-0.5 text-xs dark:border-slate-700">
                    <button
                        type="button"
                        :class="[
                            'rounded-md px-3 py-1.5 font-medium transition',
                            viewMode === 'table'
                                ? 'bg-indigo-600 text-white'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white',
                        ]"
                        @click="viewMode = 'table'"
                    >
                        Table
                    </button>
                    <button
                        type="button"
                        :class="[
                            'rounded-md px-3 py-1.5 font-medium transition',
                            viewMode === 'flow'
                                ? 'bg-indigo-600 text-white'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white',
                        ]"
                        @click="viewMode = 'flow'"
                    >
                        Visual flow
                    </button>
                </div>
            </template>

            <div v-if="viewMode === 'table' && tiers?.length">
                <PingTreeTierTable :groups="tierGroups" :collapsed-limit="15" />
            </div>

            <div v-else-if="viewMode === 'flow' && tiers?.length" class="relative mx-auto max-w-3xl py-4">
                <div class="flex flex-col items-center">
                    <div class="rounded-xl border-2 border-indigo-300 bg-indigo-50 px-6 py-3 text-center dark:border-indigo-700 dark:bg-indigo-950/40">
                        <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Lead arrives</p>
                        <p class="mt-1 text-sm font-medium text-slate-900 dark:text-white">{{ campaign?.name }}</p>
                    </div>
                    <div class="my-2 h-8 w-0.5 bg-slate-300 dark:bg-slate-600" />
                </div>

                <div v-for="(tier, ti) in tiers" :key="tier.tier" class="flex flex-col items-center">
                    <div class="w-full max-w-2xl rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <div class="mb-3 flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
                            <div>
                                <span class="rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-bold text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">
                                    Tier {{ tier.tier }}
                                </span>
                                <h3 class="mt-1 text-base font-semibold text-slate-900 dark:text-white">{{ tier.name }}</h3>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-semibold uppercase text-slate-500">Mode</p>
                                <p class="text-sm text-slate-700 dark:text-slate-300">{{ routingModeLabel(tier.mode) }}</p>
                                <p v-if="tier.floor_price" class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
                                    Floor {{ formatMoney(tier.floor_price) }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="hasActiveRules(tier.rules)"
                            class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 dark:border-amber-900/50 dark:bg-amber-950/30"
                        >
                            <p class="text-[10px] font-bold uppercase tracking-wide text-amber-700 dark:text-amber-400">
                                Entry filter
                            </p>
                            <p class="mt-0.5 text-sm text-amber-900 dark:text-amber-100">
                                {{ rulesSummaryText(tier.rules) }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Link
                                v-for="(d, di) in tier.deliveries"
                                :key="d.id"
                                :href="d.missing ? '#' : route('deliveries.show', d.id)"
                                :class="[
                                    'flex items-center justify-between rounded-lg border px-4 py-3 transition',
                                    d.missing
                                        ? 'border-rose-200 bg-rose-50 dark:border-rose-900 dark:bg-rose-950/30'
                                        : 'border-slate-200 bg-slate-50 hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-indigo-600 dark:hover:bg-indigo-950/20',
                                ]"
                            >
                                <div class="flex items-center gap-3">
                                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-200 text-xs font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                        {{ di + 1 }}
                                    </span>
                                    <div>
                                        <p :class="['font-medium', d.missing ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-white']">
                                            {{ d.name }}
                                        </p>
                                        <p v-if="d.buyer" class="text-xs text-slate-500">{{ d.buyer }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <DeliveryMethodBadge v-if="d.method" :method="d.method" />
                                    <StatusBadge v-if="d.status" :status="d.status" />
                                </div>
                            </Link>
                        </div>
                    </div>

                    <div v-if="ti < tiers.length - 1" class="my-2 flex flex-col items-center">
                        <div class="h-6 w-0.5 bg-slate-300 dark:bg-slate-600" />
                        <span class="my-1 rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                            {{ hasActiveRules(tiers[ti + 1]?.rules) ? 'if filter fails' : 'fallback' }}
                        </span>
                        <div class="h-6 w-0.5 bg-slate-300 dark:bg-slate-600" />
                    </div>
                </div>

                <div v-if="tiers?.length" class="mt-2 flex flex-col items-center">
                    <div class="h-8 w-0.5 bg-slate-300 dark:bg-slate-600" />
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-6 py-3 text-center dark:border-slate-700 dark:bg-slate-800/50">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">No tier accepts</p>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Lead marked unsold</p>
                    </div>
                </div>
            </div>

            <p v-else class="text-center text-sm text-slate-600 dark:text-slate-400">
                No tiers configured. Edit this ping tree to add routing groups.
            </p>
        </Panel>
    </AuthenticatedLayout>
</template>
