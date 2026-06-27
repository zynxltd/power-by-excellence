<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useApprovalHighlight } from '@/Composables/useApprovalHighlight';

const props = defineProps({
    postbacks: Object,
    recentLogs: Array,
    suppliers: Array,
    campaigns: Array,
    eventOptions: Array,
    pendingApprovals: { type: Array, default: () => [] },
    approvalStats: { type: Object, default: () => ({}) },
});

const rejectingId = ref(null);
const rejectionReason = ref('');

const approve = (id) => router.post(route('postbacks.approve', id));
const approveDeletion = (id) => {
    if (!confirm('Permanently remove this postback?')) return;
    router.post(route('postbacks.approve-deletion', id));
};
const reject = (id) => {
    if (!rejectionReason.value.trim()) return;
    router.post(route('postbacks.reject', id), { rejection_reason: rejectionReason.value }, {
        onSuccess: () => { rejectingId.value = null; rejectionReason.value = ''; },
    });
};
const rejectDeletion = (id) => {
    router.post(route('postbacks.reject-deletion', id), {
        rejection_reason: rejectionReason.value || null,
    }, { onSuccess: () => { rejectingId.value = null; rejectionReason.value = ''; } });
};

const approvalLabel = (status) => ({
    pending: 'New postback',
    pending_deletion: 'Deletion request',
}[status] ?? status);

const form = useForm({
    name: '',
    url: 'https://tracker.example.com/pixel?lead_id=[lead_uuid]&revenue=[revenue]',
    method: 'get',
    events: ['lead.sold'],
    supplier_id: '',
    campaign_id: '',
    is_active: true,
});

const submit = () => form.post(route('postbacks.store'), { onSuccess: () => form.reset('name', 'url') });
const destroy = (id) => { if (confirm('Delete this postback?')) router.delete(route('postbacks.destroy', id)); };

const toggleEvent = (event) => {
    const idx = form.events.indexOf(event);
    if (idx >= 0) form.events.splice(idx, 1);
    else form.events.push(event);
};

const statusClass = (status) => ({
    success: 'text-emerald-600 dark:text-emerald-400',
    failed: 'text-red-600 dark:text-red-400',
    pending: 'text-amber-600 dark:text-amber-400',
}[status] ?? 'text-slate-500');

useApprovalHighlight();
</script>

<template>
    <Head title="Postback Manager" />
    <AuthenticatedLayout>
        <PageHeader
            title="Postback Manager"
            description="Fire tracking pixels and affiliate postbacks to suppliers on lead events. Supplier default URLs from the supplier form sync here automatically."
        />

        <div class="space-y-6">
            <Panel v-if="pendingApprovals?.length" title="Supplier approval queue" class="border-amber-200 bg-amber-50/50 dark:border-amber-500/30 dark:bg-amber-500/5">
                <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                    Suppliers submit postbacks for review before they fire on live traffic.
                    <span v-if="approvalStats?.pending"> {{ approvalStats.pending }} new</span>
                    <span v-if="approvalStats?.pending_deletion"> · {{ approvalStats.pending_deletion }} deletion</span>
                </p>
                <div class="space-y-4">
                    <div
                        v-for="item in pendingApprovals"
                        :id="`approval-${item.id}`"
                        :key="item.id"
                        class="rounded-xl border border-amber-200 bg-white p-4 dark:border-amber-500/30 dark:bg-slate-900"
                    >
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-slate-900 dark:text-white">{{ item.name }}</p>
                                    <StatusBadge :label="approvalLabel(item.approval_status)" variant="amber" />
                                </div>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                    {{ item.supplier?.name }} · {{ item.method?.toUpperCase() }} · {{ item.events?.join(', ') }}
                                </p>
                                <code class="mt-2 block overflow-x-auto text-xs text-slate-600 dark:text-slate-400">{{ item.url }}</code>
                                <p v-if="item.submission_notes" class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ item.submission_notes }}</p>
                                <p v-if="item.submitted_at" class="mt-1 text-xs text-slate-500">Submitted <FormattedDate :value="item.submitted_at" /></p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <template v-if="item.approval_status === 'pending'">
                                    <AppButton @click="approve(item.id)">Approve</AppButton>
                                    <AppButton variant="danger" @click="rejectingId = rejectingId === item.id ? null : item.id">Reject</AppButton>
                                </template>
                                <template v-else-if="item.approval_status === 'pending_deletion'">
                                    <AppButton variant="danger" @click="approveDeletion(item.id)">Confirm delete</AppButton>
                                    <AppButton variant="secondary" @click="rejectDeletion(item.id)">Keep active</AppButton>
                                </template>
                            </div>
                        </div>
                        <div v-if="rejectingId === item.id" class="mt-4 border-t border-amber-200 pt-4 dark:border-amber-500/30">
                            <label class="text-sm font-medium">Rejection reason</label>
                            <textarea v-model="rejectionReason" rows="2" class="form-input mt-1 w-full" />
                            <div class="mt-2 flex gap-2">
                                <AppButton variant="danger" :disabled="!rejectionReason.trim()" @click="reject(item.id)">Confirm reject</AppButton>
                                <AppButton variant="secondary" @click="rejectingId = null">Cancel</AppButton>
                            </div>
                        </div>
                    </div>
                </div>
            </Panel>

            <Panel title="Add Postback">
                <form @submit.prevent="submit" class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <InputLabel value="Name" />
                        <TextInput v-model="form.name" class="mt-1 block w-full" placeholder="Google Ads conversion pixel" required />
                    </div>
                    <div class="md:col-span-2">
                        <InputLabel value="URL template" />
                        <TextInput
                            v-model="form.url"
                            class="mt-1 block w-full font-mono text-sm"
                            placeholder="https://tracker.com/pixel?click=[sid]&amount=[revenue]"
                            required
                        />
                        <p class="mt-1 text-xs text-slate-500">Use [field] tags: lead_uuid, email, revenue, payout, sid, ssid, campaign_id, status</p>
                    </div>
                    <div>
                        <InputLabel value="HTTP method" />
                        <select v-model="form.method" class="form-select mt-1 w-full">
                            <option value="get">GET (pixel / query string)</option>
                            <option value="post">POST</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Scope - supplier (optional)" />
                        <select v-model="form.supplier_id" class="form-select mt-1 w-full">
                            <option value="">All suppliers</option>
                            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Scope - campaign (optional)" />
                        <select v-model="form.campaign_id" class="form-select mt-1 w-full">
                            <option value="">All campaigns</option>
                            <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Events" />
                        <div class="mt-2 flex flex-wrap gap-2">
                            <label
                                v-for="ev in eventOptions"
                                :key="ev"
                                class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-sm dark:border-slate-700"
                            >
                                <input type="checkbox" :checked="form.events.includes(ev)" @change="toggleEvent(ev)" />
                                {{ ev }}
                            </label>
                        </div>
                    </div>
                    <div class="flex items-end md:col-span-2">
                        <PrimaryButton :disabled="form.processing || !form.events.length">Add Postback</PrimaryButton>
                    </div>
                </form>
            </Panel>

            <Panel title="Configured Postbacks">
                <div v-if="!postbacks?.data?.length" class="py-8 text-center text-sm text-slate-500">No postbacks yet.</div>
                <div v-for="p in postbacks.data" :key="p.id" class="flex flex-col gap-1.5 border-b border-slate-100 py-2.5 last:border-0 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-slate-900 dark:text-white">{{ p.name }}</p>
                            <span :class="p.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800'" class="rounded-full px-2 py-0.5 text-xs font-medium">
                                {{ p.is_active ? 'Active' : 'Paused' }}
                            </span>
                            <StatusBadge
                                v-if="p.approval_status"
                                :label="p.approval_status"
                                :variant="p.approval_status === 'approved' ? 'emerald' : p.approval_status === 'pending' ? 'amber' : 'slate'"
                                class="ml-1"
                            />
                            <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">{{ p.method?.toUpperCase() }}</span>
                            <span
                                v-if="p.config?.synced_from === 'supplier_default_postback'"
                                class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                            >
                                Supplier form
                            </span>
                        </div>
                        <p class="mt-1 truncate font-mono text-xs text-slate-500">{{ p.url }}</p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ (p.events || []).join(', ') }}
                            <span v-if="p.supplier"> · {{ p.supplier.name }}</span>
                            <span v-if="p.campaign"> · {{ p.campaign.name }}</span>
                            · {{ p.logs_count }} fires
                        </p>
                    </div>
                    <AppButton variant="danger" class="shrink-0" @click="destroy(p.id)">Delete</AppButton>
                </div>
                <Pagination :links="postbacks.links" />
            </Panel>

            <Panel title="Recent Postback Log">
                <div v-if="!recentLogs?.length" class="py-6 text-center text-sm text-slate-500">No postbacks fired yet.</div>
                <div v-for="log in recentLogs" :key="log.id" class="border-b border-slate-100 py-3 text-sm last:border-0 dark:border-slate-800">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-slate-900 dark:text-white">{{ log.postback?.name }}</span>
                        <span class="text-xs text-slate-500">{{ log.event }}</span>
                        <span :class="statusClass(log.status)" class="text-xs font-medium">{{ log.status }}</span>
                        <span v-if="log.http_status" class="text-xs text-slate-400">HTTP {{ log.http_status }}</span>
                        <span v-if="log.duration_ms" class="text-xs text-slate-400">{{ log.duration_ms }}ms</span>
                    </div>
                    <p class="mt-1 truncate font-mono text-xs text-slate-500">{{ log.url_fired }}</p>
                </div>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
