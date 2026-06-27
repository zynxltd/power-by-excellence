<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FlashMessage from '@/Components/UI/FlashMessage.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    supplier: Object,
    sources: Array,
    iframeEmbedAllowed: Boolean,
    forms: Array,
    campaigns: Array,
    requests: Array,
    trackingParams: Array,
});

const showCreate = ref(false);
const editingId = ref(null);
const previewSidByForm = ref({});

const createForm = useForm({
    campaign_id: props.campaigns[0]?.id ?? '',
    source_id: props.sources[0]?.id ?? '',
    name: '',
    redirect_url: '',
    allowed_domains: [''],
});

const editForm = useForm({
    campaign_id: '',
    source_id: '',
    name: '',
    redirect_url: '',
    allowed_domains: [''],
});

const submitNotes = ref('');

const statusLabel = (status) => ({
    draft: 'Draft',
    pending: 'Pending approval',
    approved: 'Approved',
    rejected: 'Rejected',
}[status] ?? status);

const statusVariant = (status) => ({
    draft: 'slate',
    pending: 'amber',
    approved: 'emerald',
    rejected: 'rose',
}[status] ?? 'slate');

const canCreate = computed(() => (props.campaigns?.length ?? 0) > 0);
const hasSources = computed(() => (props.sources?.length ?? 0) > 0);

const embedStrip = computed(() => [
    { label: 'Live forms', value: props.forms?.length ?? 0, accent: 'indigo' },
    { label: 'Tracking IDs', value: props.sources?.length ?? 0, accent: 'cyan' },
    { label: 'Pending requests', value: props.requests?.filter((r) => r.approval_status === 'pending').length ?? 0, accent: 'amber' },
    { label: 'Iframe embed', value: props.iframeEmbedAllowed ? 'Enabled' : 'Disabled', accent: props.iframeEmbedAllowed ? 'emerald' : 'rose' },
]);

const formPreviewSid = (form) => previewSidByForm.value[form.id] ?? form.default_sid ?? props.sources[0]?.sid ?? '';

const setFormPreviewSid = (formId, sid) => {
    previewSidByForm.value = { ...previewSidByForm.value, [formId]: sid };
};

const withSid = (url, sid) => {
    if (!url || !sid) return url;
    try {
        const parsed = new URL(url);
        parsed.searchParams.set('sid', sid);
        return parsed.toString();
    } catch {
        return url;
    }
};

const previewEmbed = (form, kind) => {
    const sid = formPreviewSid(form);
    if (kind === 'html') {
        const iframeUrl = withSid(form.embed.iframeUrl, sid);
        return form.embed.iframeHtml.replace(/src="[^"]+"/, `src="${iframeUrl}"`);
    }
    const base = kind === 'direct' ? form.embed.directUrl : form.embed.iframeUrl;
    return withSid(base, sid);
};

const startCreate = () => {
    editingId.value = null;
    createForm.reset();
    createForm.campaign_id = props.campaigns[0]?.id ?? '';
    createForm.source_id = props.sources[0]?.id ?? '';
    createForm.allowed_domains = [''];
    showCreate.value = true;
};

const startEdit = (request) => {
    editingId.value = request.id;
    showCreate.value = false;
    editForm.campaign_id = request.campaign?.id ?? '';
    editForm.source_id = request.config?.default_source_id ?? props.sources.find((s) => s.sid === request.config?.default_sid)?.id ?? props.sources[0]?.id ?? '';
    editForm.name = request.name;
    editForm.redirect_url = request.config?.redirect_url ?? '';
    editForm.allowed_domains = request.config?.allowed_domains?.length
        ? [...request.config.allowed_domains]
        : [''];
};

const cancelEdit = () => {
    editingId.value = null;
    editForm.reset();
};

const normalizedDomains = (form) => form.allowed_domains.filter((domain) => domain.trim() !== '');

const saveCreate = () => {
    createForm
        .transform((data) => ({ ...data, allowed_domains: normalizedDomains(createForm) }))
        .post(route('portal.supplier.forms.store'), {
            onSuccess: () => {
                showCreate.value = false;
                createForm.reset();
            },
        });
};

const saveEdit = () => {
    editForm
        .transform((data) => ({ ...data, allowed_domains: normalizedDomains(editForm) }))
        .put(route('portal.supplier.forms.update', editingId.value), {
            onSuccess: () => cancelEdit(),
        });
};

const submitForApproval = (request) => {
    router.post(route('portal.supplier.forms.submit', request.id), {
        submission_notes: submitNotes.value || request.submission_notes || '',
    }, {
        onSuccess: () => { submitNotes.value = ''; },
    });
};

const copyText = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        // ignore
    }
};
</script>

<template>
    <Head title="Form embeds" />
    <AuthenticatedLayout>
        <FlashMessage />

        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Form embeds</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Create hosted lead forms and request tenant approval before embedding on your sites.
                </p>
            </div>
            <AppButton v-if="canCreate" variant="secondary" @click="startCreate">
                {{ showCreate ? 'Cancel' : 'Create form' }}
            </AppButton>
        </div>

        <CompactStatStrip :items="embedStrip" :columns="4" class="mb-6" />

        <div
            v-if="!iframeEmbedAllowed"
            class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
        >
            <p class="font-semibold">Iframe embed not enabled for your account</p>
            <p class="mt-1">Your platform operator has not enabled supplier iframe embeds. Contact them to request access, or use direct links below if available.</p>
        </div>

        <Panel v-if="showCreate" title="Create a hosted form" class="mb-6">
            <p class="mb-4 text-sm text-slate-500">
                A draft is saved to your account. Submit it for tenant approval when you are ready to go live.
            </p>
            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="saveCreate">
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Campaign</label>
                    <select v-model="createForm.campaign_id" class="form-select mt-1 w-full" required>
                        <option v-for="campaign in campaigns" :key="campaign.id" :value="campaign.id">
                            {{ campaign.name }} ({{ campaign.reference }})
                        </option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Form name</label>
                    <input v-model="createForm.name" type="text" class="form-input mt-1 w-full" required placeholder="e.g. Google Search landing form" />
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Tracking ID (SID)</label>
                    <select v-model="createForm.source_id" class="form-select mt-1 w-full md:max-w-md" :disabled="!hasSources">
                        <option v-for="source in sources" :key="`create-source-${source.id}`" :value="source.id">
                            {{ source.sid }}<template v-if="source.name"> — {{ source.name }}</template>
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">SID identifies traffic source for reporting. It is not auto-linked to the campaign — pick the source that matches this form's traffic.</p>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Thank-you redirect URL (optional)</label>
                    <input v-model="createForm.redirect_url" type="url" class="form-input mt-1 w-full" placeholder="https://yoursite.com/thanks" />
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Allowed domains (optional)</label>
                    <p class="mt-1 text-xs text-slate-500">Restrict where the form can be embedded. Leave blank to allow any domain when iframe embed is enabled.</p>
                    <div class="mt-2 space-y-2">
                        <input
                            v-for="(_, index) in createForm.allowed_domains"
                            :key="`create-domain-${index}`"
                            v-model="createForm.allowed_domains[index]"
                            type="text"
                            class="form-input w-full"
                            placeholder="example.com"
                        />
                    </div>
                    <button type="button" class="mt-2 text-xs text-indigo-600" @click="createForm.allowed_domains.push('')">+ Add domain</button>
                </div>
                <div class="md:col-span-2">
                    <AppButton type="submit" :disabled="createForm.processing">Save draft</AppButton>
                </div>
            </form>
        </Panel>

        <Panel v-if="!canCreate" class="mb-6">
            <p class="text-sm text-slate-500">You are not linked to any campaigns yet. Ask your platform administrator to assign campaigns before creating forms.</p>
        </Panel>

        <Panel v-if="requests?.length" title="Your form requests" class="mb-6">
            <div class="space-y-4">
                <div
                    v-for="request in requests"
                    :key="request.id"
                    class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-slate-900 dark:text-white">{{ request.name }}</h3>
                                <StatusBadge :label="statusLabel(request.approval_status)" :variant="statusVariant(request.approval_status)" />
                            </div>
                            <p v-if="request.campaign" class="mt-1 text-sm text-slate-500">
                                Campaign: {{ request.campaign.name }}
                                <span class="font-mono text-xs">({{ request.campaign.reference }})</span>
                            </p>
                            <p v-if="request.submitted_at" class="mt-1 text-xs text-slate-500">
                                Submitted <FormattedDate :value="request.submitted_at" />
                            </p>
                            <p v-if="request.submission_notes" class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                Notes: {{ request.submission_notes }}
                            </p>
                            <p v-if="request.rejection_reason" class="mt-2 text-sm text-rose-700 dark:text-rose-300">
                                Rejection reason: {{ request.rejection_reason }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <AppButton
                                v-if="['draft', 'rejected'].includes(request.approval_status)"
                                variant="secondary"
                                @click="startEdit(request)"
                            >
                                Edit
                            </AppButton>
                            <AppButton
                                v-if="['draft', 'rejected'].includes(request.approval_status)"
                                @click="submitForApproval(request)"
                            >
                                Submit for approval
                            </AppButton>
                        </div>
                    </div>

                    <div v-if="editingId === request.id" class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-700">
                        <form class="grid gap-4 md:grid-cols-2" @submit.prevent="saveEdit">
                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Campaign</label>
                                <select v-model="editForm.campaign_id" class="form-select mt-1 w-full" required>
                                    <option v-for="campaign in campaigns" :key="campaign.id" :value="campaign.id">
                                        {{ campaign.name }} ({{ campaign.reference }})
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Tracking ID (SID)</label>
                                <select v-model="editForm.source_id" class="form-select mt-1 w-full" :disabled="!hasSources">
                                    <option v-for="source in sources" :key="`edit-source-${source.id}`" :value="source.id">
                                        {{ source.sid }}<template v-if="source.name"> — {{ source.name }}</template>
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Form name</label>
                                <input v-model="editForm.name" type="text" class="form-input mt-1 w-full" required />
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Notes for reviewer (optional)</label>
                                <textarea v-model="submitNotes" rows="2" class="form-input mt-1 w-full" placeholder="Explain where you plan to host this form..." />
                            </div>
                            <div class="flex gap-2 md:col-span-2">
                                <AppButton type="submit" :disabled="editForm.processing">Save changes</AppButton>
                                <AppButton variant="secondary" type="button" @click="cancelEdit">Cancel</AppButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Panel>

        <Panel v-if="sources?.length" title="Your tracking IDs" class="mb-6">
            <p class="mb-3 text-sm text-slate-600 dark:text-slate-400">
                SIDs are configured on your supplier account and apply across campaigns. Assign the correct SID when creating each form — embed URLs bake in your choice.
            </p>
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="source in sources"
                    :key="source.id"
                    class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800/50"
                >
                    <span class="font-mono font-semibold text-indigo-600 dark:text-indigo-400">{{ source.sid }}</span>
                    <span v-if="source.name" class="ml-2 text-slate-500">{{ source.name }}</span>
                </span>
            </div>
            <p class="mt-2 text-xs text-slate-500">Append <code class="rounded bg-slate-100 px-1 font-mono dark:bg-slate-800">click_id</code>, UTM params, or <code class="rounded bg-slate-100 px-1 font-mono dark:bg-slate-800">ssid</code> to the embed URL as needed.</p>
        </Panel>

        <div v-if="!forms?.length" class="rounded-xl border border-dashed border-slate-300 px-6 py-10 text-center text-sm text-slate-500 dark:border-slate-600">
            No live embed forms are available yet. Your platform administrator can assign tenant forms to you, or you can create a form above and submit it for approval.
        </div>

        <div v-else class="space-y-6">
            <Panel v-for="form in forms" :key="form.id" :title="form.name">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p v-if="form.campaign" class="text-sm text-slate-500">
                            Campaign: <span class="font-medium text-slate-700 dark:text-slate-300">{{ form.campaign.name }}</span>
                            <span class="font-mono text-xs">({{ form.campaign.reference }})</span>
                        </p>
                        <p v-if="form.default_sid" class="mt-1 text-xs text-slate-500">
                            Default SID: <span class="font-mono font-semibold text-indigo-600 dark:text-indigo-400">{{ form.default_sid }}</span>
                        </p>
                    </div>
                    <div v-if="sources?.length > 1" class="min-w-[220px]">
                        <label class="text-xs font-semibold uppercase text-slate-500">Preview SID</label>
                        <select
                            :value="formPreviewSid(form)"
                            class="form-select mt-1 w-full text-sm"
                            @change="setFormPreviewSid(form.id, $event.target.value)"
                        >
                            <option v-for="source in sources" :key="`preview-${form.id}-${source.id}`" :value="source.sid">
                                {{ source.sid }}<template v-if="source.name"> — {{ source.name }}</template>
                            </option>
                        </select>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <div class="mb-1 flex items-center justify-between gap-2">
                            <label class="text-xs font-semibold uppercase text-slate-500">Direct link</label>
                            <button type="button" class="text-xs text-indigo-600" @click="copyText(previewEmbed(form, 'direct'))">Copy</button>
                        </div>
                        <code class="block overflow-x-auto rounded-xl bg-slate-50 p-3 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ previewEmbed(form, 'direct') }}</code>
                    </div>

                    <template v-if="iframeEmbedAllowed">
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <label class="text-xs font-semibold uppercase text-slate-500">Iframe URL</label>
                                <button type="button" class="text-xs text-indigo-600" @click="copyText(previewEmbed(form, 'iframe'))">Copy</button>
                            </div>
                            <code class="block overflow-x-auto rounded-xl bg-slate-50 p-3 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ previewEmbed(form, 'iframe') }}</code>
                        </div>
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <label class="text-xs font-semibold uppercase text-slate-500">Iframe HTML</label>
                                <button type="button" class="text-xs text-indigo-600" @click="copyText(previewEmbed(form, 'html'))">Copy</button>
                            </div>
                            <code class="block overflow-x-auto rounded-xl bg-slate-50 p-3 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ previewEmbed(form, 'html') }}</code>
                            <p class="mt-2 text-xs text-slate-500">Append <code class="rounded bg-slate-100 px-1 font-mono dark:bg-slate-800">click_id</code>, UTM params, or <code class="rounded bg-slate-100 px-1 font-mono dark:bg-slate-800">ssid</code> to the URL as needed.</p>
                        </div>
                    </template>
                </div>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
