<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import ClickableTableRow from '@/Components/UI/ClickableTableRow.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    configs: Array,
    campaigns: Array,
    routingModes: Array,
    filters: Object,
    filterOptions: Object,
});

const localFilters = ref({ ...props.filters });

const applyFilters = () => {
    router.get(route('distribution.index'), localFilters.value, { preserveState: true, replace: true });
};

const clearFilters = () => {
    localFilters.value = {};
    applyFilters();
};

const modeLabel = (mode) => mode?.replace(/_/g, ' ');

watch(() => props.filters, (f) => { localFilters.value = { ...f }; });
</script>

<template>
    <Head title="Ping Tree" />
    <AuthenticatedLayout>
        <PageHeader
            title="Ping Tree & Distribution"
            description="Configure tiered routing: waterfall, ping-post auction, round-robin, and hybrid groups."
        >
            <template #actions>
                <AppButton :href="route('distribution.create')">New Configuration</AppButton>
            </template>
        </PageHeader>

        <Panel title="Filters" class="mb-6">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Campaign</label>
                    <select v-model="localFilters.campaign_id" class="form-select">
                        <option value="">All campaigns</option>
                        <option v-for="c in filterOptions?.campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Status</label>
                    <select v-model="localFilters.active" class="form-select">
                        <option value="">All</option>
                        <option value="1">Active only</option>
                        <option value="0">Inactive only</option>
                    </select>
                </div>
            </div>
            <div class="mt-4 flex gap-3">
                <AppButton @click="applyFilters">Apply filters</AppButton>
                <AppButton variant="secondary" @click="clearFilters">Clear</AppButton>
            </div>
        </Panel>

        <Panel title="Active Ping Tree Configurations" :padding="false">
            <DataTable :empty="!configs?.length">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tiers</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                </template>
                <ClickableTableRow v-for="c in configs" :key="c.id" :href="route('distribution.show', c.id)">
                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ c.name }}</td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ c.campaign?.name }}</td>
                    <td class="px-6 py-4">
                        <div class="flex flex-wrap gap-1">
                            <span
                                v-for="(g, i) in c.config?.groups"
                                :key="i"
                                class="rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300"
                            >
                                {{ g.name }} ({{ modeLabel(g.mode) }})
                            </span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <StatusBadge :status="c.is_active ? 'active' : 'inactive'" />
                    </td>
                    <td class="px-6 py-4 text-right" @click.stop>
                        <Link :href="route('distribution.edit', c.id)" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                            Edit →
                        </Link>
                    </td>
                </ClickableTableRow>
            </DataTable>
        </Panel>

        <Panel title="Campaigns" class="mt-6" :padding="false">
            <DataTable :empty="!campaigns?.length">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Reference</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Mode</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Configs</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                </template>
                <tr v-for="camp in campaigns" :key="camp.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ camp.name }}</td>
                    <td class="px-6 py-4 font-mono text-xs text-slate-500">{{ camp.reference }}</td>
                    <td class="px-6 py-4">
                        <span
                            :class="[
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                camp.use_advanced_distribution
                                    ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'
                                    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
                            ]"
                        >
                            {{ camp.use_advanced_distribution ? 'Advanced (Ping Tree)' : 'Standard' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ camp.distribution_configs_count }}</td>
                    <td class="px-6 py-4 text-right space-x-3">
                        <Link :href="route('campaigns.show', camp.id)" class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">View</Link>
                        <Link :href="route('distribution.create') + '?campaign_id=' + camp.id" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">+ Config</Link>
                    </td>
                </tr>
            </DataTable>
        </Panel>
    </AuthenticatedLayout>
</template>
