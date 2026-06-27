<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useApprovalHighlight } from '@/Composables/useApprovalHighlight';

const props = defineProps({
    webhooks: Array,
    buyers: Array,
    eventOptions: Array,
    pendingApprovals: { type: Array, default: () => [] },
    approvalStats: { type: Object, default: () => ({}) },
});

const rejectingId = ref(null);
const rejectionReason = ref('');

const approve = (id) => router.post(route('webhooks.approve', id));
const approveDeletion = (id) => {
    if (!confirm('Permanently remove this webhook?')) return;
    router.post(route('webhooks.approve-deletion', id));
};
const reject = (id) => {
    if (!rejectionReason.value.trim()) return;
    router.post(route('webhooks.reject', id), { rejection_reason: rejectionReason.value }, {
        onSuccess: () => { rejectingId.value = null; rejectionReason.value = ''; },
    });
};
const rejectDeletion = (id) => {
    router.post(route('webhooks.reject-deletion', id), {
        rejection_reason: rejectionReason.value || null,
    }, { onSuccess: () => { rejectingId.value = null; rejectionReason.value = ''; } });
};

const approvalLabel = (status) => ({
    pending: 'New webhook',
    pending_deletion: 'Deletion request',
}[status] ?? status);

const form = useForm({
    name: '',
    url: '',
    events: ['lead.sold'],
    buyer_id: '',
    is_active: true,
});

const submit = () => form.post(route('webhooks.store'), {
    onSuccess: () => form.reset('name', 'url', 'buyer_id'),
});

const destroy = (webhook) => {
    if (webhook.config?.synced_from === 'buyer_sold_webhook') {
        return;
    }
    if (confirm('Delete this webhook?')) {
        router.delete(route('webhooks.destroy', webhook.id));
    }
};

const toggleEvent = (event) => {
    const idx = form.events.indexOf(event);
    if (idx >= 0) form.events.splice(idx, 1);
    else form.events.push(event);
};

const scopeLabel = (webhook) => {
    if (webhook.buyer) {
        return `Buyer: ${webhook.buyer.name}`;
    }
    return 'Account-wide';
};

const isManaged = (webhook) => webhook.config?.synced_from === 'buyer_sold_webhook';

const approvalBadge = (webhook) => {
    if (!webhook.approval_status) return null;
    return webhook.approval_status;
};

useApprovalHighlight();
</script>

<template>
    <Head title="Webhooks" />
    <AuthenticatedLayout>
        <PageHeader
            title="Webhooks"
            description="Outbound JSON notifications to your CRM or buyer systems. Account-wide hooks fire for every matching event; buyer-scoped hooks only fire when that buyer wins the lead."
        />

        <Panel class="mb-6" title="How this differs from postbacks">
            <ul class="list-inside list-disc space-y-1 text-sm text-slate-600 dark:text-slate-400">
                <li><strong class="font-medium text-slate-800 dark:text-slate-200">Webhooks</strong> - JSON POST to your endpoints (CRM, data warehouse, buyer integrations).</li>
                <li><strong class="font-medium text-slate-800 dark:text-slate-200">Postbacks</strong> - tracking pixels / affiliate URLs for <Link :href="route('postbacks.index')" class="text-indigo-600 hover:underline">suppliers</Link> (GET query strings with <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">[lead_uuid]</code> tags).</li>
                <li>Buyers can submit webhooks from the <strong class="font-medium text-slate-800 dark:text-slate-200">buyer portal Integrations</strong> page for your approval. Optional sold webhook URL can also be set on each <Link :href="route('buyers.index')" class="text-indigo-600 hover:underline">buyer</Link>.</li>
            </ul>
        </Panel>

        <div class="space-y-6">
            <Panel v-if="pendingApprovals?.length" title="Buyer approval queue" class="border-amber-200 bg-amber-50/50 dark:border-amber-500/30 dark:bg-amber-500/5">
                <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                    Buyers submit webhooks for review before they fire on live traffic.
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
                                    {{ item.buyer?.name }} · {{ item.events?.join(', ') }}
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

            <Panel title="Add Webhook">
                <form @submit.prevent="submit" class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <InputLabel value="Name" />
                        <TextInput v-model="form.name" class="mt-1 block w-full" placeholder="CRM lead sold feed" required />
                    </div>
                    <div class="md:col-span-2">
                        <InputLabel value="Endpoint URL" />
                        <TextInput v-model="form.url" type="url" class="mt-1 block w-full font-mono text-sm" placeholder="https://hooks.example.com/leads" required />
                        <p class="mt-1 text-xs text-slate-500">Receives JSON POST with event, lead_uuid, buyer_id, revenue, and field_data.</p>
                    </div>
                    <div>
                        <InputLabel value="Scope - buyer (optional)" />
                        <select v-model="form.buyer_id" class="form-select mt-1 w-full">
                            <option value="">All buyers (account-wide)</option>
                            <option v-for="b in buyers" :key="b.id" :value="b.id">{{ b.name }}</option>
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
                        <PrimaryButton :disabled="form.processing || !form.events.length">Add Webhook</PrimaryButton>
                    </div>
                </form>
            </Panel>

            <Panel title="Configured Webhooks">
                <div v-if="!webhooks?.length" class="py-8 text-center text-sm text-slate-500">No webhooks configured yet.</div>
                <div v-for="w in webhooks" :key="w.id" class="flex flex-col gap-1.5 border-b border-slate-100 py-3 last:border-0 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-slate-900 dark:text-white">{{ w.name }}</p>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ scopeLabel(w) }}</span>
                            <span v-if="isManaged(w)" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Buyer form</span>
                            <StatusBadge
                                v-if="approvalBadge(w)"
                                :label="approvalBadge(w)"
                                :variant="w.approval_status === 'approved' || w.is_active ? 'emerald' : 'amber'"
                            />
                        </div>
                        <p class="mt-1 truncate font-mono text-xs text-slate-500">{{ w.url }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ (w.events || []).join(', ') }}</p>
                    </div>
                    <AppButton
                        v-if="!isManaged(w) && !approvalBadge(w)"
                        variant="danger"
                        class="shrink-0"
                        @click="destroy(w)"
                    >
                        Delete
                    </AppButton>
                    <Link
                        v-else-if="isManaged(w) && w.buyer"
                        :href="route('buyers.edit', w.buyer.id)"
                        class="shrink-0 text-sm font-medium text-indigo-600 hover:underline"
                    >
                        Edit buyer
                    </Link>
                </div>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
