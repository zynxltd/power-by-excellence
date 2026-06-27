<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    forms: Object,
    pendingApprovals: Array,
    approvalStats: Object,
    campaigns: Array,
    verticals: Array,
});

const showCreate = ref(false);
const rejectingId = ref(null);
const rejectionReason = ref('');

const form = useForm({
    campaign_id: props.campaigns[0]?.id ?? '',
    name: '',
    config: { redirect_url: '', allowed_domains: [], css: '' },
});

const campaignsByVertical = computed(() => {
    const groups = {};
    for (const c of props.campaigns ?? []) {
        const key = c.vertical_id || 'other';
        groups[key] ??= { vertical_id: key, label: c.vertical_label || 'Other', campaigns: [] };
        groups[key].campaigns.push(c);
    }
    return Object.values(groups);
});

const submit = () => {
    form.post(route('forms.store'), { onSuccess: () => { showCreate.value = false; form.reset(); } });
};

const approve = (id) => {
    router.post(route('forms.approve', id));
};

const reject = (id) => {
    router.post(route('forms.reject', id), { rejection_reason: rejectionReason.value }, {
        onSuccess: () => {
            rejectingId.value = null;
            rejectionReason.value = '';
        },
    });
};

const approvalLabel = (status) => ({
    draft: 'Draft',
    pending: 'Pending',
    approved: 'Approved',
    rejected: 'Rejected',
}[status] ?? status);
</script>

<template>
    <Head title="Form Builder" />
    <AuthenticatedLayout>
        <PageHeader title="Form Builder" description="Hosted lead capture - direct links, iframe embeds, SID tracking, and supplier approval queue.">
            <template #actions>
                <AppButton @click="showCreate = !showCreate">{{ showCreate ? 'Cancel' : 'New Form' }}</AppButton>
            </template>
        </PageHeader>

        <Panel v-if="pendingApprovals?.length" class="mb-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Supplier form approvals</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ approvalStats.pending }} pending review</p>
                </div>
            </div>
            <div class="space-y-4">
                <div
                    v-for="item in pendingApprovals"
                    :key="item.id"
                    class="rounded-xl border border-amber-200 bg-amber-50/40 p-4 dark:border-amber-500/30 dark:bg-amber-500/5"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-slate-900 dark:text-white">{{ item.name }}</h3>
                                <StatusBadge label="Pending approval" variant="amber" />
                            </div>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                Supplier: <span class="font-medium">{{ item.supplier?.name }}</span>
                                <span v-if="item.campaign"> · Campaign: {{ item.campaign.name }}</span>
                            </p>
                            <p v-if="item.submitted_at" class="mt-1 text-xs text-slate-500">
                                Submitted <FormattedDate :value="item.submitted_at" />
                            </p>
                            <p v-if="item.submission_notes" class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                Supplier notes: {{ item.submission_notes }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Link :href="route('forms.edit', item.id)" class="inline-flex items-center rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-white dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                                Preview
                            </Link>
                            <AppButton variant="secondary" @click="approve(item.id)">Approve</AppButton>
                            <AppButton variant="danger" @click="rejectingId = rejectingId === item.id ? null : item.id">Reject</AppButton>
                        </div>
                    </div>
                    <div v-if="rejectingId === item.id" class="mt-4 border-t border-amber-200 pt-4 dark:border-amber-500/30">
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Rejection reason</label>
                        <textarea v-model="rejectionReason" rows="2" class="form-input mt-1 w-full" placeholder="Explain what the supplier should change..." />
                        <div class="mt-2 flex gap-2">
                            <AppButton variant="danger" :disabled="!rejectionReason.trim()" @click="reject(item.id)">Confirm reject</AppButton>
                            <AppButton variant="secondary" @click="rejectingId = null">Cancel</AppButton>
                        </div>
                    </div>
                </div>
            </div>
        </Panel>

        <Panel v-if="showCreate" class="mb-6">
            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Campaign / vertical</label>
                    <select v-model="form.campaign_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-800">
                        <optgroup v-for="group in campaignsByVertical" :key="group.vertical_id" :label="group.label">
                            <option v-for="c in group.campaigns" :key="c.id" :value="c.id">{{ c.name }} ({{ c.reference }})</option>
                        </optgroup>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Form name</label>
                    <input v-model="form.name" type="text" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-800" required />
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Thank-you redirect URL</label>
                    <input v-model="form.config.redirect_url" type="url" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-800" placeholder="https://yoursite.com/thanks" />
                </div>
                <div class="md:col-span-2">
                    <AppButton type="submit" :disabled="form.processing">Create form</AppButton>
                </div>
            </form>
        </Panel>

        <Panel :padding="false">
            <DataTable :empty="!forms?.data?.length">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Source</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Vertical</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Campaign</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Embed URL</th>
                </template>
                <tr v-for="f in forms.data" :key="f.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ f.name }}</td>
                    <td class="px-6 py-4">
                        <span v-if="f.supplier" class="text-sm text-slate-600 dark:text-slate-400">{{ f.supplier.name }}</span>
                        <span v-else class="text-sm text-slate-400">Admin</span>
                        <StatusBadge
                            v-if="f.approval_status"
                            class="ml-2"
                            :label="approvalLabel(f.approval_status)"
                            :variant="f.approval_status === 'pending' ? 'amber' : f.approval_status === 'approved' ? 'emerald' : 'slate'"
                        />
                    </td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ f.campaign?.vertical_id?.replace('_', ' ') ?? '-' }}</td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ f.campaign?.name }}</td>
                    <td class="px-6 py-4">
                        <a v-if="f.is_active && (!f.approval_status || f.approval_status === 'approved')" :href="route('forms.show', f.slug)" target="_blank" class="text-sm text-indigo-600 dark:text-indigo-400">{{ route('forms.show', f.slug) }}</a>
                        <span v-else class="text-sm text-slate-400">Not live</span>
                        <Link :href="route('forms.edit', f.id)" class="ml-3 text-sm text-slate-500 hover:text-indigo-600">Edit</Link>
                    </td>
                </tr>
            </DataTable>
            <Pagination :links="forms.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
