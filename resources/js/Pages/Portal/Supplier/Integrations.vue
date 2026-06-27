<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
    campaigns: { type: Array, default: () => [] },
    apiKeys: { type: Array, default: () => [] },
    postbacks: { type: Array, default: () => [] },
    postbackRequests: { type: Array, default: () => [] },
    postbackEventOptions: { type: Array, default: () => [] },
    postbackStats: { type: Object, default: () => ({ live: 0, pending: 0, draft: 0 }) },
    defaultPostbackUrlExample: { type: String, default: '' },
    defaultPostbackUrl: String,
    helpUrls: { type: Array, default: () => [] },
    guides: { type: Array, default: () => [] },
});

const copied = ref('');
const editingPostbackId = ref(null);
const submitNotes = ref('');

const postbackForm = useForm({
    name: '',
    url: props.defaultPostbackUrlExample || 'https://powerbyexcellence.test/api/mock/postback',
    method: 'get',
    events: ['lead.sold'],
    campaign_id: '',
});

const editPostbackForm = useForm({
    name: '',
    url: '',
    method: 'get',
    events: ['lead.sold'],
    campaign_id: '',
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

const livePostbackCount = computed(() => props.postbackStats?.live ?? props.postbacks?.length ?? 0);

const togglePostbackEvent = (form, event) => {
    const idx = form.events.indexOf(event);
    if (idx >= 0) form.events.splice(idx, 1);
    else form.events.push(event);
};

const createPostback = () => {
    postbackForm.post(route('portal.supplier.postbacks.store'), {
        preserveScroll: true,
        onSuccess: () => postbackForm.reset('name'),
    });
};

const startEditPostback = (request) => {
    editingPostbackId.value = request.id;
    editPostbackForm.name = request.name;
    editPostbackForm.url = request.url;
    editPostbackForm.method = request.method ?? 'get';
    editPostbackForm.events = [...(request.events ?? ['lead.sold'])];
    editPostbackForm.campaign_id = request.campaign?.id ?? '';
    submitNotes.value = '';
};

const savePostbackEdit = () => {
    editPostbackForm.put(route('portal.supplier.postbacks.update', editingPostbackId.value), {
        preserveScroll: true,
        onSuccess: () => { editingPostbackId.value = null; },
    });
};

const submitPostbackForApproval = (request) => {
    router.post(route('portal.supplier.postbacks.submit', request.id), {
        submission_notes: submitNotes.value || null,
    }, { preserveScroll: true });
};

const deletePostbackDraft = (request) => {
    if (!confirm('Delete this postback draft?')) return;
    router.delete(route('portal.supplier.postbacks.destroy', request.id), { preserveScroll: true });
};

const requestPostbackDeletion = (request) => {
    const notes = window.prompt('Optional note for your administrator:', '');
    if (notes === null) return;
    router.post(route('portal.supplier.postbacks.request-deletion', request.id), {
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
    <AuthenticatedLayout>
        <PageHeader
            title="Integrations & API"
            :description="`Submit leads, poll status, receive postbacks, and export data for ${partner?.name ?? 'your supplier account'}.`"
        >
            <template #actions>
                <AppButton :href="route('portal.supplier.embeds')" variant="secondary">Form embeds</AppButton>
                <AppButton :href="route('portal.supplier.leads.download')" variant="secondary" external>Download CSV</AppButton>
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
                <p class="text-xs font-semibold uppercase text-slate-500">Supplier reference</p>
                <p class="mt-1 font-mono text-sm text-slate-900 dark:text-white">{{ partner?.reference }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ apiKeys.length }} active API key{{ apiKeys.length === 1 ? '' : 's' }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Live postbacks</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ livePostbackCount }}</p>
                <p class="text-xs text-slate-500">Outbound conversion callbacks</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Pending review</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ postbackStats?.pending ?? 0 }}</p>
                <p class="text-xs text-slate-500">{{ postbackStats?.draft ?? 0 }} draft{{ (postbackStats?.draft ?? 0) === 1 ? '' : 's' }} awaiting submit</p>
            </div>
        </div>

        <Panel title="API documentation" class="mb-6">
            <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                REST endpoints, authentication, field schemas, and payload examples live in the help centre — not duplicated here.
                Ask your platform administrator for a supplier API key with
                <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">leads.create</code> and
                <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">leads.read</code> permissions.
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

        <Panel v-if="postbackEventOptions?.length" title="Your postbacks" class="mb-6">
            <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                Create conversion postbacks for your affiliate tracker. Drafts must be approved by your platform administrator before they fire on live leads.
                REST examples, sample payloads, and URL macros are in the help guides above — configure drafts here once you know your endpoint.
            </p>

            <form class="mb-6 grid gap-4 rounded-xl border border-slate-200 p-4 dark:border-slate-700 md:grid-cols-2" @submit.prevent="createPostback">
                <div class="md:col-span-2">
                    <InputLabel value="Name" />
                    <input v-model="postbackForm.name" type="text" class="form-input mt-1 w-full" placeholder="Main conversion pixel" required />
                </div>
                <div class="md:col-span-2">
                    <InputLabel value="Postback URL" />
                    <input v-model="postbackForm.url" type="url" class="form-input mt-1 w-full font-mono text-sm" required />
                </div>
                <div>
                    <InputLabel value="HTTP method" />
                    <select v-model="postbackForm.method" class="form-select mt-1 w-full">
                        <option value="get">GET</option>
                        <option value="post">POST</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Campaign scope (optional)" />
                    <select v-model="postbackForm.campaign_id" class="form-select mt-1 w-full">
                        <option value="">All campaigns</option>
                        <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <InputLabel value="Events" />
                    <div class="mt-2 flex flex-wrap gap-2">
                        <label
                            v-for="ev in postbackEventOptions"
                            :key="ev"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-sm dark:border-slate-700"
                        >
                            <input type="checkbox" :checked="postbackForm.events.includes(ev)" @change="togglePostbackEvent(postbackForm, ev)" />
                            {{ ev }}
                        </label>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <PrimaryButton :disabled="postbackForm.processing || !postbackForm.events.length">Save draft</PrimaryButton>
                </div>
            </form>

            <div v-if="!postbackRequests?.length" class="rounded-lg border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-600">
                No postback requests yet. Save a draft above, then submit for approval.
            </div>

            <div v-else class="space-y-4">
                <div
                    v-for="request in postbackRequests"
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
                                <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">{{ request.method?.toUpperCase() }}</span>
                            </div>
                            <code class="mt-2 block overflow-x-auto text-xs text-slate-600 dark:text-slate-400">{{ request.url }}</code>
                            <p class="mt-1 text-xs text-slate-500">{{ request.events?.join(', ') }}</p>
                            <p v-if="request.campaign" class="mt-1 text-xs text-slate-500">Campaign: {{ request.campaign.name }}</p>
                            <p v-if="request.rejection_reason" class="mt-2 text-sm text-rose-700 dark:text-rose-300">{{ request.rejection_reason }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <AppButton v-if="['draft', 'rejected'].includes(request.approval_status)" variant="secondary" @click="startEditPostback(request)">Edit</AppButton>
                            <AppButton v-if="['draft', 'rejected'].includes(request.approval_status)" @click="submitPostbackForApproval(request)">Submit for approval</AppButton>
                            <AppButton v-if="['draft', 'rejected'].includes(request.approval_status)" variant="danger" @click="deletePostbackDraft(request)">Delete</AppButton>
                            <AppButton v-if="request.approval_status === 'approved'" variant="danger" @click="requestPostbackDeletion(request)">Request removal</AppButton>
                        </div>
                    </div>

                    <div v-if="editingPostbackId === request.id" class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-700">
                        <form class="grid gap-4 md:grid-cols-2" @submit.prevent="savePostbackEdit">
                            <div class="md:col-span-2">
                                <InputLabel value="Name" />
                                <input v-model="editPostbackForm.name" type="text" class="form-input mt-1 w-full" required />
                            </div>
                            <div class="md:col-span-2">
                                <InputLabel value="URL" />
                                <input v-model="editPostbackForm.url" type="url" class="form-input mt-1 w-full font-mono text-sm" required />
                            </div>
                            <div class="md:col-span-2 flex gap-2">
                                <AppButton type="submit" :disabled="editPostbackForm.processing">Save changes</AppButton>
                                <AppButton variant="secondary" type="button" @click="editingPostbackId = null">Cancel</AppButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Panel>

        <Panel v-if="postbacks?.length || defaultPostbackUrl" title="Active postbacks (admin + approved)" class="mb-6">
            <ul class="space-y-3">
                <li v-if="defaultPostbackUrl" class="rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/50">
                    <span class="font-medium text-slate-900 dark:text-white">Default supplier URL</span>
                    <code class="mt-1 block overflow-x-auto text-xs text-indigo-600 dark:text-indigo-400">{{ defaultPostbackUrl }}</code>
                </li>
                <li v-for="postback in postbacks" :key="postback.name + (postback.method ?? '')" class="rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/50">
                    <span class="font-medium text-slate-900 dark:text-white">{{ postback.name }}</span>
                    <span class="ml-2 text-xs uppercase text-slate-500">{{ postback.method }}</span>
                    <span class="ml-2 text-xs text-slate-500">{{ postback.events?.join(', ') }}</span>
                    <span v-if="postback.scoped_to_you" class="ml-1 text-xs text-indigo-600">· yours</span>
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
            Prefer hosted forms?
            <Link :href="route('portal.supplier.embeds')" class="font-semibold text-indigo-600 hover:underline">Get embed codes on Form embeds</Link>
            with your supplier ID and SID pre-filled.
        </p>
    </AuthenticatedLayout>
</template>
