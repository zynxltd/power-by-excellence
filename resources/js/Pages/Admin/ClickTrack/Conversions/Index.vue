<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({ entitlement: Object, conversions: Object, pendingQueue: Object, campaigns: Array, suppliers: Array, filters: Object, statusOptions: Array });
const selected = ref([]);
const { formatMoney } = useMoneyFormat();

const apply = (key, value) => router.get(route('click-track.conversions.index'), { ...props.filters, [key]: value || undefined }, { preserveState: true });
const approve = (id) => router.post(route('click-track.conversions.approve', id));
const reject = (id) => router.post(route('click-track.conversions.reject', id), { reason: 'Rejected by admin' });
const bulkApprove = () => router.post(route('click-track.conversions.bulk-approve'), { ids: selected.value }, { onSuccess: () => { selected.value = []; } });
const toggle = (id) => { const i = selected.value.indexOf(id); if (i >= 0) selected.value.splice(i, 1); else selected.value.push(id); };
</script>

<template>
    <Head title="Conversions" />
    <AuthenticatedLayout>
        <PageHeader title="Conversions" description="Pending / approved / rejected conversion queue with payout and revenue.">
            <template #actions>
                <AppButton v-if="selected.length" @click="bulkApprove">Approve {{ selected.length }} selected</AppButton>
                <AppButton :href="route('click-track.conversions.export')" variant="secondary" external>Export CSV</AppButton>
            </template>
        </PageHeader>

        <Panel v-if="pendingQueue?.count && filters.status !== 'pending'" title="Pending approval queue" class="mb-6">
            <p class="mb-3 text-sm text-slate-600 dark:text-slate-300">{{ pendingQueue.count }} conversion(s) waiting for review.</p>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <div v-for="item in pendingQueue.items" :key="item.id" class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                    <div>
                        <p class="font-semibold">{{ item.tracking_link?.name ?? item.campaign?.name }}</p>
                        <p class="text-xs text-slate-500">{{ item.supplier?.name ?? '—' }} · {{ item.goal }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-500">{{ formatMoney(item.payout) }}</span>
                        <button type="button" class="text-xs font-semibold text-emerald-600" @click="approve(item.id)">Approve</button>
                    </div>
                </div>
            </div>
            <AppButton :href="route('click-track.conversions.index', { status: 'pending' })" variant="secondary" class="mt-3">View all pending</AppButton>
        </Panel>

        <Panel>
            <div class="mb-4 flex flex-wrap gap-2">
                <select class="form-select text-sm" :value="filters.status" @change="apply('status', $event.target.value)">
                    <option value="">All statuses</option>
                    <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead><tr class="border-b text-left text-xs uppercase text-slate-500">
                        <th class="px-3 py-2"></th><th class="px-3 py-2">Date</th><th class="px-3 py-2">Offer</th><th class="px-3 py-2">Affiliate</th><th class="px-3 py-2">Goal</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Payout</th><th class="px-3 py-2">Revenue</th><th class="px-3 py-2">ID</th><th class="px-3 py-2"></th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="c in conversions.data" :key="c.id" class="border-b border-slate-100 dark:border-slate-800" :class="{ 'bg-amber-50/50 dark:bg-amber-950/20': c.status === 'pending' }">
                            <td class="px-3 py-2"><input type="checkbox" :checked="selected.includes(c.id)" @change="toggle(c.id)" /></td>
                            <td class="px-3 py-2"><FormattedDate :date="c.created_at" /></td>
                            <td class="px-3 py-2">{{ c.tracking_link?.name ?? c.campaign?.name }}</td>
                            <td class="px-3 py-2">{{ c.supplier?.name ?? '—' }}</td>
                            <td class="px-3 py-2">{{ c.goal }}</td>
                            <td class="px-3 py-2"><StatusBadge :status="c.status" /></td>
                            <td class="px-3 py-2">{{ formatMoney(c.payout) }}</td>
                            <td class="px-3 py-2">{{ formatMoney(c.revenue) }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ c.conversion_uuid.slice(0, 8) }}…</td>
                            <td class="px-3 py-2">
                                <button v-if="c.status === 'pending'" type="button" class="text-xs font-semibold text-emerald-600" @click="approve(c.id)">Approve</button>
                                <button v-if="c.status === 'pending'" type="button" class="ml-2 text-xs font-semibold text-red-600" @click="reject(c.id)">Reject</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="conversions.links" class="mt-4" />
        </Panel>
    </AuthenticatedLayout>
</template>
