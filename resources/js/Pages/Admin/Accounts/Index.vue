<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ accounts: Array, currentAccountId: Number });

const page = usePage();
const isSuperAdmin = computed(() => page.props.auth?.isSuperAdmin ?? false);

const switchAccount = (accountId) => router.post(route('accounts.switch'), { account_id: accountId });

const clearTenantContext = () => router.post(route('accounts.clear'));
</script>

<template>
    <Head title="Partner Platforms" />
    <AuthenticatedLayout>
        <PageHeader
            title="Partner Platforms (Tenants)"
            description="Each tenant is fully self-serviced on its own subdomain — tenant admins run day-to-day ops. Provision platforms here, then switch context or open a portal only when support is needed."
        >
            <template #actions>
                <AppButton :href="route('accounts.billing.index')" variant="secondary">Tenant billing</AppButton>
                <AppButton :href="route('accounts.create')" variant="primary">New platform</AppButton>
                <AppButton v-if="currentAccountId" variant="secondary" @click="clearTenantContext">
                    Exit tenant · central admin
                </AppButton>
            </template>
        </PageHeader>

        <div
            v-if="isSuperAdmin && !currentAccountId"
            class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
        >
            <strong>Central admin mode.</strong>
            Each partner platform is self-serviced by its tenant admin. Use this view to provision tenants and monitor the network — switch into a platform only when support is needed.
        </div>

        <div
            v-if="currentAccountId"
            class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900 dark:border-indigo-800 dark:bg-indigo-950/30 dark:text-indigo-100"
        >
            <p>
                Viewing data scoped to one platform. Exit to see <strong>all tenants</strong> on Command Center, cross-tenant reports, and global settings.
            </p>
            <button
                type="button"
                class="shrink-0 font-semibold text-indigo-700 underline decoration-indigo-300 underline-offset-2 hover:text-indigo-900 dark:text-indigo-300"
                @click="clearTenantContext"
            >
                All platforms (central admin) →
            </button>
        </div>

        <Panel :padding="false">
            <DataTable :empty="!accounts?.length">
                <template #head>
                    <th class="text-left">Platform</th>
                    <th class="text-left">Domain</th>
                    <th class="text-left">Campaigns</th>
                    <th class="text-left">Leads</th>
                    <th class="text-right">Actions</th>
                </template>
                <tr
                    v-for="a in accounts"
                    :key="a.id"
                    :class="[
                        'transition',
                        a.id === currentAccountId ? 'bg-indigo-50/80 dark:bg-indigo-500/10' : 'hover:bg-slate-50 dark:hover:bg-slate-800/50',
                    ]"
                >
                    <td class="">
                        <p class="font-medium text-slate-900 dark:text-white">{{ a.name }}</p>
                        <p class="text-xs text-slate-500">{{ a.slug }}</p>
                    </td>
                    <td class="font-mono text-xs text-slate-500">{{ a.domain }}</td>
                    <td class="text-slate-600">{{ a.campaigns_count }} · {{ a.leads_count }} leads</td>
                    <td class="text-slate-600">{{ a.buyers_count }} buyers · {{ a.suppliers_count }} suppliers</td>
                    <td class="text-right">
                        <div class="flex flex-wrap justify-end gap-2">
                            <template v-if="a.id === currentAccountId">
                                <span class="text-sm font-semibold text-indigo-600">Active</span>
                                <AppButton variant="secondary" @click="clearTenantContext">Exit to central</AppButton>
                            </template>
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
