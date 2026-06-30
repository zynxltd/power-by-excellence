<script setup>
import BuyerPortalLayout from '@/Layouts/BuyerPortalLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    apiBaseUrl: String,
    tenantHost: String,
    currency: String,
    partner: Object,
    webhooks: { type: Array, default: () => [] },
    webhookRequests: { type: Array, default: () => [] },
    webhookEventOptions: { type: Array, default: () => [] },
    webhookStats: { type: Object, default: () => ({ live: 0, pending: 0, draft: 0 }) },
    helpUrls: { type: Array, default: () => [] },
    guides: { type: Array, default: () => [] },
});

const copied = ref('');
const editingWebhookId = ref(null);
const submitNotes = ref('');

const webhookForm = useForm({
    name: '',
    url: 'https://hooks.example.com/leads',
    events: ['lead.sold'],
});

const editWebhookForm = useForm({
    name: '',
    url: '',
    events: ['lead.sold'],
});

const statusLabel = (status) => ({
    draft: 'Draft',
    pending: 'Pending approval',
    approved: 'Live',
    rejected: 'Rejected',
    pending_deletion: 'Deletion pending',
}[status] ?? status);

const approvalBadgeClass = (status) => ({
    draft: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    rejected: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
    pending_deletion: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
}[status] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300');

const liveWebhookCount = computed(() => props.webhookStats?.live ?? props.webhooks?.length ?? 0);

const webhookHelpUrl = computed(() => props.helpUrls.find((doc) => doc.label.includes('Webhook'))?.href ?? null);

const toggleWebhookEvent = (form, event) => {
    const idx = form.events.indexOf(event);
    if (idx >= 0) form.events.splice(idx, 1);
    else form.events.push(event);
};

const createWebhook = () => {
    webhookForm.post(route('portal.buyer.webhooks.store'), {
        preserveScroll: true,
        onSuccess: () => webhookForm.reset('name'),
    });
};

const startEditWebhook = (request) => {
    editingWebhookId.value = request.id;
    editWebhookForm.name = request.name;
    editWebhookForm.url = request.url;
    editWebhookForm.events = [...(request.events ?? ['lead.sold'])];
    submitNotes.value = '';
};

const saveWebhookEdit = () => {
    editWebhookForm.put(route('portal.buyer.webhooks.update', editingWebhookId.value), {
        preserveScroll: true,
        onSuccess: () => { editingWebhookId.value = null; },
    });
};

const submitWebhookForApproval = (request) => {
    router.post(route('portal.buyer.webhooks.submit', request.id), {
        submission_notes: submitNotes.value || null,
    }, { preserveScroll: true });
};

const deleteWebhookDraft = (request) => {
    if (!confirm('Delete this webhook draft?')) return;
    router.delete(route('portal.buyer.webhooks.destroy', request.id), { preserveScroll: true });
};

const requestWebhookDeletion = (request) => {
    const notes = window.prompt('Optional note for your administrator:', '');
    if (notes === null) return;
    router.post(route('portal.buyer.webhooks.request-deletion', request.id), {
        submission_notes: notes || null,
    }, { preserveScroll: true });
};

const copyText = async (text, key) => {
    await navigator.clipboard.writeText(text);
    copied.value = key;
    setTimeout(() => { copied.value = ''; }, 2000);
};
</script>

<template>
    <Head title="Integrations & API" />
    <BuyerPortalLayout>
        <PageHeader
            title="Integrations & API"
            :description="`Connect webhooks, export data, and integrate with ${partner?.name ?? 'your buyer account'}.`"
        >
            <template #actions>
                <AppButton :href="route('portal.buyer.leads')" variant="secondary">My Leads</AppButton>
                <AppButton :href="route('portal.buyer.leads.download')" variant="secondary" external>Download CSV</AppButton>
            </template>
        </PageHeader>

        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">API base URL</p>
                <p class="mt-1 break-all font-mono text-sm text-indigo-600 dark:text-indigo-400">{{ apiBaseUrl }}</p>
                <button type="button" class="mt-2 text-xs font-medium text-indigo-600 hover:underline" @click="copyText(apiBaseUrl, 'base')">
                    {{ copied === 'base' ? 'Copied' : 'Copy' }}
                </button>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Buyer reference</p>
                <p class="mt-1 font-mono text-sm text-slate-900 dark:text-white">{{ partner?.reference }}</p>
                <p class="mt-1 text-xs text-slate-500">Used in REST paths and delivery config</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Live webhooks</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ liveWebhookCount }}</p>
                <p class="text-xs text-slate-500">Outbound events to your systems</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Pending review</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ webhookStats?.pending ?? 0 }}</p>
                <p class="text-xs text-slate-500">{{ webhookStats?.draft ?? 0 }} draft{{ (webhookStats?.draft ?? 0) === 1 ? '' : 's' }} awaiting submit</p>
            </div>
        </div>

        <Panel title="API documentation" class="mb-6">
            <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                REST endpoints, authentication, and payload examples live in the help centre — not duplicated here.
                Ask your platform administrator for an API key with <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">buyers.manage</code> permission.
            </p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="doc in helpUrls"
                    :key="doc.href"
                    :href="doc.href"
                    class="group rounded-xl border border-slate-200 p-4 transition hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-slate-700 dark:hover:border-indigo-500/40 dark:hover:bg-indigo-950/20"
                >
                    <p class="font-semibold text-slate-900 group-hover:text-indigo-700 dark:text-white dark:group-hover:text-indigo-300">{{ doc.label }}</p>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ doc.description }}</p>
                    <span class="mt-3 inline-block text-sm font-medium text-indigo-600 dark:text-indigo-400">Open guide →</span>
                </Link>
            </div>
        </Panel>

        <Panel title="Your webhooks" class="mb-6">
            <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                Register HTTPS endpoints to receive JSON when leads are sold or updated. Drafts must be approved by your platform administrator before they fire on live traffic.
                <template v-if="webhookHelpUrl">
                    Sample payloads and event types are in the
                    <Link :href="webhookHelpUrl" class="font-semibold text-indigo-600 hover:underline">Webhooks guide</Link>.
                </template>
            </p>

            <form class="mb-6 grid gap-4 rounded-xl border border-slate-200 p-4 dark:border-slate-700 md:grid-cols-2" @submit.prevent="createWebhook">
                <div class="md:col-span-2">
                    <InputLabel value="Name" />
                    <input v-model="webhookForm.name" type="text" class="form-input mt-1 w-full" placeholder="CRM lead sold feed" required />
                </div>
                <div class="md:col-span-2">
                    <InputLabel value="Webhook URL" />
                    <input v-model="webhookForm.url" type="url" class="form-input mt-1 w-full font-mono text-sm" required />
                </div>
                <div class="md:col-span-2">
                    <InputLabel value="Events" />
                    <div class="mt-2 flex flex-wrap gap-2">
                        <label
                            v-for="ev in webhookEventOptions"
                            :key="ev"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-sm dark:border-slate-700"
                        >
                            <input type="checkbox" :checked="webhookForm.events.includes(ev)" @change="toggleWebhookEvent(webhookForm, ev)" />
                            {{ ev }}
                        </label>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <PrimaryButton :disabled="webhookForm.processing || !webhookForm.events.length">Save draft</PrimaryButton>
                </div>
            </form>

            <div v-if="!webhookRequests?.length" class="rounded-lg border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-600">
                No webhook requests yet. Save a draft above, then submit for approval.
            </div>

            <div v-else class="space-y-4">
                <div
                    v-for="request in webhookRequests"
                    :key="request.id"
                    class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-slate-900 dark:text-white">{{ request.name }}</p>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="approvalBadgeClass(request.approval_status)">
                                    {{ statusLabel(request.approval_status) }}
                                </span>
                            </div>
                            <code class="mt-2 block overflow-x-auto text-xs text-slate-600 dark:text-slate-400">{{ request.url }}</code>
                            <p class="mt-1 text-xs text-slate-500">{{ request.events?.join(', ') }}</p>
                            <p v-if="request.rejection_reason" class="mt-2 text-sm text-rose-700 dark:text-rose-300">{{ request.rejection_reason }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <AppButton v-if="['draft', 'rejected'].includes(request.approval_status)" variant="secondary" @click="startEditWebhook(request)">Edit</AppButton>
                            <AppButton v-if="['draft', 'rejected'].includes(request.approval_status)" @click="submitWebhookForApproval(request)">Submit for approval</AppButton>
                            <AppButton v-if="['draft', 'rejected'].includes(request.approval_status)" variant="danger" @click="deleteWebhookDraft(request)">Delete</AppButton>
                            <AppButton v-if="request.approval_status === 'approved'" variant="danger" @click="requestWebhookDeletion(request)">Request removal</AppButton>
                        </div>
                    </div>

                    <div v-if="editingWebhookId === request.id" class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-700">
                        <form class="grid gap-4 md:grid-cols-2" @submit.prevent="saveWebhookEdit">
                            <div class="md:col-span-2">
                                <InputLabel value="Name" />
                                <input v-model="editWebhookForm.name" type="text" class="form-input mt-1 w-full" required />
                            </div>
                            <div class="md:col-span-2">
                                <InputLabel value="URL" />
                                <input v-model="editWebhookForm.url" type="url" class="form-input mt-1 w-full font-mono text-sm" required />
                            </div>
                            <div class="md:col-span-2">
                                <InputLabel value="Events" />
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <label
                                        v-for="ev in webhookEventOptions"
                                        :key="`edit-${ev}`"
                                        class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-sm dark:border-slate-700"
                                    >
                                        <input type="checkbox" :checked="editWebhookForm.events.includes(ev)" @change="toggleWebhookEvent(editWebhookForm, ev)" />
                                        {{ ev }}
                                    </label>
                                </div>
                            </div>
                            <div class="md:col-span-2 flex gap-2">
                                <AppButton type="submit" :disabled="editWebhookForm.processing || !editWebhookForm.events.length">Save changes</AppButton>
                                <AppButton variant="secondary" type="button" @click="editingWebhookId = null">Cancel</AppButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Panel>

        <Panel v-if="webhooks?.length" title="Active webhooks (admin + approved)" class="mb-6">
            <ul class="space-y-3">
                <li v-for="webhook in webhooks" :key="webhook.id ?? webhook.name" class="rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/50">
                    <p class="font-medium text-slate-900 dark:text-white">{{ webhook.name }}</p>
                    <p class="font-mono text-xs text-slate-500">{{ webhook.url_host }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        Events: {{ webhook.events?.join(', ') || '—' }}
                        <span v-if="webhook.scoped_to_you && webhook.managed_by_admin" class="ml-1 text-amber-600">· managed by admin</span>
                        <span v-else-if="webhook.scoped_to_you" class="ml-1 text-indigo-600">· yours</span>
                    </p>
                </li>
            </ul>
        </Panel>

        <Panel title="Guides">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div v-for="guide in guides" :key="guide.title">
                    <dt class="text-sm font-semibold text-slate-900 dark:text-white">{{ guide.title }}</dt>
                    <dd class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ guide.body }}</dd>
                </div>
            </dl>
        </Panel>

        <p class="mt-6 text-sm text-slate-500">
            Prefer the UI?
            <Link :href="route('portal.buyer.leads')" class="font-semibold text-indigo-600 hover:underline">Report feedback and returns on My Leads</Link>
            without an API key.
        </p>
    </BuyerPortalLayout>
</template>
