<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import DeliveryMethodBadge from '@/Components/UI/DeliveryMethodBadge.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import ClickableTableRow from '@/Components/UI/ClickableTableRow.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import PingTreeConfigCard from '@/Components/UI/PingTreeConfigCard.vue';
import CampaignFieldsPreview from '@/Components/Campaign/CampaignFieldsPreview.vue';
import CampaignWorkflowNav from '@/Components/UI/CampaignWorkflowNav.vue';
import GoLiveChecklist from '@/Components/Campaign/GoLiveChecklist.vue';
import TenantContextBanner from '@/Components/UI/TenantContextBanner.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    campaign: Object,
    deliveries: Object,
    tenantHub: Object,
    campaignWorkflow: Object,
    leadsToday: Number,
    goLiveChecklist: { type: Array, default: null },
});

const { formatMoney } = useMoneyFormat(props.campaign?.currency);

const fieldCount = computed(() => props.campaign?.fields?.length ?? 0);
const pingFieldCount = computed(() => props.campaign?.fields?.filter((f) => f.ping_field)?.length ?? 0);
const configCount = computed(() => props.campaign?.distribution_configs?.length ?? 0);
const activeConfig = computed(() => props.campaign?.distribution_configs?.find((c) => c.is_active));

const campaignStatStrip = computed(() => [
    { label: 'Reference', value: props.campaign?.reference ?? '-', accent: 'indigo' },
    { label: 'Status', value: props.campaign?.status ?? '-', accent: 'emerald' },
    { label: 'Floor', value: formatMoney(props.campaign?.floor_price), accent: 'cyan' },
    { label: 'Distribution', value: props.campaign?.use_advanced_distribution ? 'Ping tree' : 'Standard', accent: 'amber' },
    { label: 'Leads today', value: props.leadsToday ?? 0, accent: 'violet' },
]);
</script>

<template>
    <Head :title="campaign.name" />
    <AuthenticatedLayout>
        <PageHeader :title="campaign.name" :description="`Reference: ${campaign.reference} · ${campaign.country}/${campaign.currency}`">
            <template #actions>
                <AppButton :href="route('leads.index', { campaign_id: campaign.id })" variant="secondary">View leads</AppButton>
                <AppButton :href="route('campaigns.edit', campaign.id)">Edit campaign</AppButton>
            </template>
        </PageHeader>

        <TenantContextBanner />

        <GoLiveChecklist v-if="goLiveChecklist" :checklist="goLiveChecklist" />

        <CampaignWorkflowNav
            v-if="campaignWorkflow"
            :campaign="campaignWorkflow.campaign"
            :distribution-config-id="campaignWorkflow.distributionConfigId"
            current="show"
            class="mb-6"
        />

        <CompactStatStrip :items="campaignStatStrip" :columns="5" class="mb-6" />

        <Panel title="Campaign Fields" class="mt-6">
            <template #header>
                <span class="text-xs text-slate-500">{{ fieldCount }} fields · {{ pingFieldCount }} ping</span>
            </template>
            <CampaignFieldsPreview :fields="campaign.fields ?? []" :campaign-id="campaign.id" />
        </Panel>

        <Panel v-if="configCount" title="Ping Tree Configuration" class="mt-6">
            <template #header>
                <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                    <span>{{ configCount }} configuration{{ configCount === 1 ? '' : 's' }}</span>
                    <span v-if="activeConfig" class="rounded-full bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                        Active: {{ activeConfig.name }}
                    </span>
                </div>
            </template>
            <div class="space-y-4">
                <PingTreeConfigCard
                    v-for="config in campaign.distribution_configs"
                    :key="config.id"
                    :config="config"
                />
            </div>
        </Panel>

        <Panel v-else-if="campaign.use_advanced_distribution" title="Ping Tree Configuration" class="mt-6">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                Advanced distribution is enabled but no ping tree exists yet.
            </p>
            <AppButton class="mt-4" :href="route('distribution.create') + '?campaign_id=' + campaign.id">
                Create ping tree
            </AppButton>
        </Panel>

        <Panel title="Deliveries" class="mt-6" :padding="false">
            <template #header>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-sm text-slate-500">{{ deliveries.total }} deliver{{ deliveries.total === 1 ? 'y' : 'ies' }}</span>
                    <AppButton :href="route('deliveries.create') + '?campaign_id=' + campaign.id" variant="secondary" class="!py-1.5 !text-xs">
                        Add delivery
                    </AppButton>
                </div>
            </template>
            <DataTable :empty="!deliveries?.data?.length" empty-message="No deliveries yet. Add buyers to this campaign's ping tree.">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                    <th class="hidden px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 md:table-cell">Buyer</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Method</th>
                    <th class="hidden px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 sm:table-cell">Tier</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="hidden px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 lg:table-cell">Priority</th>
                </template>
                <ClickableTableRow v-for="d in deliveries.data" :key="d.id" :href="route('deliveries.show', d.id)">
                    <td class="px-6 py-4">
                        <p class="font-medium text-slate-900 dark:text-white">{{ d.name }}</p>
                    </td>
                    <td class="hidden px-6 py-4 md:table-cell">
                        <p v-if="d.buyer" class="text-sm text-slate-700 dark:text-slate-300">{{ d.buyer.name }}</p>
                        <p v-if="d.buyer?.reference" class="font-mono text-xs text-slate-500">{{ d.buyer.reference }}</p>
                        <span v-else class="text-sm text-slate-400">-</span>
                    </td>
                    <td class="px-6 py-4">
                        <DeliveryMethodBadge v-if="d.method" :method="d.method" />
                        <span v-else class="text-sm text-slate-400">-</span>
                    </td>
                    <td class="hidden px-6 py-4 text-slate-600 dark:text-slate-400 sm:table-cell">
                        {{ d.tier ?? '-' }}
                    </td>
                    <td class="px-6 py-4"><StatusBadge :status="d.status" /></td>
                    <td class="hidden px-6 py-4 text-slate-600 dark:text-slate-400 lg:table-cell">{{ d.priority }}</td>
                </ClickableTableRow>
            </DataTable>
            <Pagination :links="deliveries.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
