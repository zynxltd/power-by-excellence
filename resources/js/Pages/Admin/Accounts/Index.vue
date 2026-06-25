<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({ accounts: Array, currentAccountId: Number });

const switchAccount = (accountId) => router.post(route('accounts.switch'), { account_id: accountId });
</script>

<template>
    <Head title="Partner Platforms" />
    <AuthenticatedLayout>
        <PageHeader
            title="Partner Platforms (Tenants)"
            description="Each tenant runs on its own subdomain. Switch context, open the tenant portal, or impersonate the tenant admin."
        />

        <Panel :padding="false">
            <DataTable :empty="!accounts?.length">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Platform</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Domain</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaigns</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Leads</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                </template>
                <tr
                    v-for="a in accounts"
                    :key="a.id"
                    :class="[
                        'transition',
                        a.id === currentAccountId ? 'bg-indigo-50/80 dark:bg-indigo-500/10' : 'hover:bg-slate-50 dark:hover:bg-slate-800/50',
                    ]"
                >
                    <td class="px-6 py-4">
                        <p class="font-medium text-slate-900 dark:text-white">{{ a.name }}</p>
                        <p class="text-xs text-slate-500">{{ a.slug }}</p>
                    </td>
                    <td class="px-6 py-4 font-mono text-xs text-slate-500">{{ a.domain }}</td>
                    <td class="px-6 py-4 text-slate-600">{{ a.campaigns_count }} · {{ a.leads_count }} leads</td>
                    <td class="px-6 py-4 text-slate-600">{{ a.buyers_count }} buyers · {{ a.suppliers_count }} suppliers</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex flex-wrap justify-end gap-2">
                            <span v-if="a.id === currentAccountId" class="text-sm font-semibold text-indigo-600">Active</span>
                            <AppButton v-else variant="secondary" @click="switchAccount(a.id)">Switch</AppButton>
                            <AppButton
                                :href="route('accounts.visit', a.id)"
                                method="post"
                                variant="secondary"
                            >
                                Open portal ↗
                            </AppButton>
                            <AppButton
                                v-if="a.admin_user"
                                :href="route('impersonate.start', a.admin_user.id)"
                                method="post"
                                variant="secondary"
                            >
                                Login as admin
                            </AppButton>
                        </div>
                    </td>
                </tr>
            </DataTable>
        </Panel>
    </AuthenticatedLayout>
</template>
