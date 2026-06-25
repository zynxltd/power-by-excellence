<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import MetaStatCard from '@/Components/UI/MetaStatCard.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import DeliveryMethodBadge from '@/Components/UI/DeliveryMethodBadge.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import ClickableTableRow from '@/Components/UI/ClickableTableRow.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import PingTreeConfigCard from '@/Components/UI/PingTreeConfigCard.vue';
import CampaignWorkflowNav from '@/Components/UI/CampaignWorkflowNav.vue';
import TenantContextBanner from '@/Components/UI/TenantContextBanner.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    campaign: Object,
    deliveries: Object,
    tenantHub: Object,
    leadsToday: Number,
});

const { formatMoney } = useMoneyFormat(props.campaign?.currency);

const fieldCount = computed(() => props.campaign?.fields?.length ?? 0);
const pingFieldCount = computed(() => props.campaign?.fields?.filter((f) => f.ping_field)?.length ?? 0);
const configCount = computed(() => props.campaign?.distribution_configs?.length ?? 0);
const activeConfig = computed(() => props.campaign?.distribution_configs?.find((c) => c.is_active));
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

        <CampaignWorkflowNav
            :campaign="{ id: campaign.id, name: campaign.name, reference: campaign.reference }"
            current="show"
            :distribution-config-id="activeConfig?.id"
            :tenant-hub="tenantHub"
            class="mb-6"
        />

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <MetaStatCard label="Reference" :value="campaign.reference" mono accent="indigo" />
            <MetaStatCard label="Status" accent="emerald">
                <StatusBadge :status="campaign.status" />
            </MetaStatCard>
            <MetaStatCard label="Floor price" :value="formatMoney(campaign.floor_price)" accent="cyan" />
            <MetaStatCard label="Distribution" accent="amber">
                <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                    {{ campaign.use_advanced_distribution ? 'Ping tree' : 'Standard' }}
                </span>
            </MetaStatCard>
            <MetaStatCard label="Leads today" :value="leadsToday" accent="violet" />
        </div>

        <Panel title="Campaign Fields" class="mt-6">
            <template #header>
                <span class="text-xs text-slate-500">{{ fieldCount }} fields · {{ pingFieldCount }} ping</span>
            </template>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm text-slate-500">Fields used for API ingest, validation, and form builder.</p>
                <Link :href="route('campaigns.api-spec', campaign.id)" class="text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-400">
                    Edit API spec →
                </Link>
            </div>
            <div class="flex max-h-32 flex-wrap gap-2 overflow-y-auto pr-1">
                <span
                    v-for="f in campaign.fields"
                    :key="f.id"
                    class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                >
                    {{ f.name }}<span v-if="f.ping_field" class="ml-1 text-indigo-600 dark:text-indigo-400">(ping)</span>
                </span>
            </div>
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
                        <span v-else class="text-sm text-slate-400">—</span>
                    </td>
                    <td class="px-6 py-4">
                        <DeliveryMethodBadge v-if="d.method" :method="d.method" />
                        <span v-else class="text-sm text-slate-400">—</span>
                    </td>
                    <td class="hidden px-6 py-4 text-slate-600 dark:text-slate-400 sm:table-cell">
                        {{ d.tier ?? '—' }}
                    </td>
                    <td class="px-6 py-4"><StatusBadge :status="d.status" /></td>
                    <td class="hidden px-6 py-4 text-slate-600 dark:text-slate-400 lg:table-cell">{{ d.priority }}</td>
                </ClickableTableRow>
            </DataTable>
            <Pagination :links="deliveries.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
