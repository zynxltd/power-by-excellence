<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import TenantHubPanel from '@/Components/UI/TenantHubPanel.vue';
import TenantContextBanner from '@/Components/UI/TenantContextBanner.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    campaign: Object,
    tenantHub: Object,
    leadsToday: Number,
});

const modeLabel = (mode) => mode?.replace(/_/g, ' ');
</script>

<template>
    <Head :title="campaign.name" />
    <AuthenticatedLayout>
        <PageHeader :title="campaign.name" :description="`Reference: ${campaign.reference} · ${campaign.country}/${campaign.currency}`">
            <template #actions>
                <AppButton :href="route('leads.index', { campaign_id: campaign.id })" variant="secondary">View leads</AppButton>
                <AppButton :href="route('campaigns.api-spec', campaign.id)" variant="secondary">API Spec</AppButton>
                <AppButton :href="route('distribution.create') + '?campaign_id=' + campaign.id" variant="secondary">Ping Tree</AppButton>
                <AppButton :href="route('campaigns.edit', campaign.id)">Edit Campaign</AppButton>
            </template>
        </PageHeader>

        <TenantContextBanner />
        <TenantHubPanel v-if="tenantHub" :tenant-hub="tenantHub" class="mb-6" />

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
            <StatCard label="Reference" :value="campaign.reference" accent="indigo" />
            <StatCard label="Status" :value="campaign.status" accent="emerald" />
            <StatCard label="Floor Price" :value="'£' + campaign.floor_price" accent="cyan" />
            <StatCard label="Distribution" :value="campaign.use_advanced_distribution ? 'Ping Tree' : 'Standard'" accent="amber" />
            <StatCard label="Leads today" :value="leadsToday" accent="violet" />
        </div>

        <Panel title="Campaign Fields" class="mt-6">
            <div class="mb-3 flex justify-between">
                <p class="text-sm text-slate-500">Fields used for API ingest, validation, and form builder.</p>
                <Link :href="route('campaigns.api-spec', campaign.id)" class="text-sm font-semibold text-indigo-600 hover:underline">Edit API spec →</Link>
            </div>
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="f in campaign.fields"
                    :key="f.id"
                    class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                >
                    {{ f.name }}<span v-if="f.ping_field" class="ml-1 text-indigo-600 dark:text-indigo-400">(ping)</span>
                </span>
            </div>
        </Panel>

        <Panel v-if="campaign.distribution_configs?.length" title="Ping Tree Configuration" class="mt-6">
            <div class="space-y-4">
                <div
                    v-for="config in campaign.distribution_configs"
                    :key="config.id"
                    class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                >
                    <div class="flex items-center justify-between">
                        <h4 class="font-medium text-slate-900 dark:text-white">{{ config.name }}</h4>
                        <div class="flex items-center gap-3">
                            <StatusBadge :status="config.is_active ? 'active' : 'inactive'" />
                            <Link :href="route('distribution.edit', config.id)" class="text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Edit</Link>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span
                            v-for="(g, i) in config.config?.groups"
                            :key="i"
                            class="rounded-lg bg-violet-50 px-3 py-1 text-xs font-medium text-violet-700 dark:bg-violet-900/30 dark:text-violet-300"
                        >
                            {{ g.name }} — {{ modeLabel(g.mode) }}
                        </span>
                    </div>
                </div>
            </div>
        </Panel>

        <Panel title="Deliveries" class="mt-6" :padding="false">
            <DataTable :empty="!campaign.deliveries?.length">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Method</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Priority</th>
                </template>
                <tr v-for="d in campaign.deliveries" :key="d.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ d.name }}</td>
                    <td class="px-6 py-4 capitalize text-slate-600 dark:text-slate-400">{{ d.method?.replace(/_/g, ' ') }}</td>
                    <td class="px-6 py-4"><StatusBadge :status="d.status" /></td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ d.priority }}</td>
                </tr>
            </DataTable>
        </Panel>
    </AuthenticatedLayout>
</template>
