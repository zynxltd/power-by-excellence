<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import ClickableTableRow from '@/Components/UI/ClickableTableRow.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import TenantContextBanner from '@/Components/UI/TenantContextBanner.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const isSuperAdmin = page.props.auth.isSuperAdmin;
const canCreateCampaign = computed(() => Boolean(page.props.auth.account));

defineProps({
    campaigns: Object,
    showTenantColumn: Boolean,
});
</script>

<template>
    <Head title="Campaigns" />
    <AuthenticatedLayout>
        <PageHeader title="All Campaigns" description="Lead capture campaigns, API references, and distribution rules. Scoped to the active tenant when one is selected.">
            <template #actions>
                <AppButton v-if="isSuperAdmin" :href="route('accounts.index')" variant="secondary">Platforms</AppButton>
                <AppButton v-else :href="route('settings.edit')" variant="secondary">Platform settings</AppButton>
                <AppButton v-if="canCreateCampaign" :href="route('campaigns.create')">New Campaign</AppButton>
            </template>
        </PageHeader>

        <TenantContextBanner />

        <Panel :padding="false">
            <template #header>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-xs text-slate-500">{{ campaigns.total }} campaigns</span>
                    <span v-if="showTenantColumn" class="text-xs text-amber-600 dark:text-amber-400">Showing campaigns from all platforms</span>
                </div>
            </template>
            <DataTable :empty="!campaigns?.data?.length" empty-message="No campaigns yet. Select a tenant or create your first campaign.">
                <template #head>
                    <th class="text-left">Campaign</th>
                    <th v-if="showTenantColumn" class="text-left">Platform</th>
                    <th class="text-left">Reference</th>
                    <th class="text-left">Market</th>
                    <th class="text-left">Status</th>
                    <th class="text-left">Leads</th>
                    <th class="text-right">Actions</th>
                </template>
                <ClickableTableRow v-for="c in campaigns.data" :key="c.id" :href="route('campaigns.show', c.id)">
                    <td>
                        <p class="font-medium text-slate-900 dark:text-white">{{ c.name }}</p>
                        <p v-if="c.vertical_id" class="text-xs text-slate-500">{{ c.vertical_id }}</p>
                    </td>
                    <td v-if="showTenantColumn" class="text-xs text-slate-600 dark:text-slate-400">
                        {{ c.account?.brand_name || c.account?.name || '—' }}
                    </td>
                    <td class="font-mono text-xs text-slate-500">{{ c.reference }}</td>
                    <td class="text-xs text-slate-600 dark:text-slate-400">{{ c.country }} / {{ c.currency }}</td>
                    <td><StatusBadge :status="c.status" /></td>
                    <td class="text-slate-600 dark:text-slate-400">{{ c.leads_count }}</td>
                    <td class="text-right" @click.stop>
                        <div class="flex justify-end gap-3">
                            <Link :href="route('leads.index', { campaign_id: c.id })" class="text-xs text-slate-500 hover:text-indigo-600">Leads</Link>
                            <Link :href="route('campaigns.api-spec', c.id)" class="text-xs text-slate-500 hover:text-indigo-600">API</Link>
                            <Link :href="route('campaigns.edit', c.id)" class="text-xs text-slate-500 hover:text-indigo-600">Edit</Link>
                        </div>
                    </td>
                </ClickableTableRow>
            </DataTable>
            <Pagination :links="campaigns.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
