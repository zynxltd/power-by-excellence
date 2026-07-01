<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    entitlement: Object,
    links: Object,
    linkCaps: Object,
    campaigns: Array,
    suppliers: Array,
    buyers: Array,
    goalOptions: Array,
    postbackMacros: { type: Array, default: () => [] },
    postbackMacroSamples: { type: Object, default: () => ({}) },
});

const capUsage = (linkId) => props.linkCaps?.[linkId];

const form = useForm({
    name: '',
    campaign_id: '',
    supplier_id: '',
    buyer_id: '',
    destination_url: '',
    goal: 'lead',
    status: 'active',
    payout_amount: '',
    revenue_amount: '',
    cap_daily: '',
    cap_monthly: '',
    conversion_cap_daily: '',
    auto_approve_conversions: true,
});

const editingLinkId = ref(null);

const editForm = useForm({
    name: '',
    destination_url: '',
    goal: 'lead',
    status: 'active',
    payout_amount: '',
    revenue_amount: '',
    cap_daily: '',
    cap_monthly: '',
    conversion_cap_daily: '',
    auto_approve_conversions: true,
    conversion_postback_url: '',
    conversion_postback_method: 'get',
});

const macroLabel = (key) => `[${key}]`;

const expandPostbackPreview = (template) => {
    if (!template) {
        return '';
    }

    let preview = template;
    for (const [key, value] of Object.entries(props.postbackMacroSamples ?? {})) {
        preview = preview.replaceAll(`[${key}]`, String(value));
    }

    return preview;
};

const postbackPreview = computed(() => expandPostbackPreview(editForm.conversion_postback_url));

const insertMacro = (macro) => {
    editForm.conversion_postback_url = `${editForm.conversion_postback_url ?? ''}${macroLabel(macro)}`;
};

const startEdit = (link) => {
    editingLinkId.value = link.id;
    editForm.clearErrors();
    editForm.name = link.name;
    editForm.destination_url = link.destination_url;
    editForm.goal = link.goal ?? 'lead';
    editForm.status = link.status;
    editForm.payout_amount = link.payout_amount ?? '';
    editForm.revenue_amount = link.revenue_amount ?? '';
    editForm.cap_daily = link.config?.cap_daily ?? '';
    editForm.cap_monthly = link.config?.cap_monthly ?? '';
    editForm.conversion_cap_daily = link.config?.conversion_cap_daily ?? '';
    editForm.auto_approve_conversions = link.config?.auto_approve_conversions ?? true;
    editForm.conversion_postback_url = link.conversion_postback_url ?? '';
    editForm.conversion_postback_method = link.conversion_postback_macros?.method ?? 'get';
};

const cancelEdit = () => {
    editingLinkId.value = null;
    editForm.reset();
};

const saveEdit = () => {
    editForm.patch(route('click-track.links.update', editingLinkId.value), {
        preserveScroll: true,
        onSuccess: () => cancelEdit(),
    });
};

const submit = () => form.post(route('click-track.links.store'), { onSuccess: () => form.reset('name', 'destination_url') });
const destroy = (id) => { if (confirm('Delete this tracking link?')) router.delete(route('click-track.links.destroy', id)); };
const copyUrl = (token) => navigator.clipboard.writeText(route('click.redirect', token));
</script>

<template>
    <Head title="Tracking Links" />
    <AuthenticatedLayout>
        <PageHeader title="Tracking links" description="Generate /c/{token} URLs for affiliates. Clicks append click_id to your landing page or hosted form." />

        <div class="grid gap-6 xl:grid-cols-5">
            <Panel title="Create link" class="xl:col-span-2">
                <form class="space-y-4" @submit.prevent="submit">
                    <div><InputLabel value="Name" /><TextInput v-model="form.name" class="mt-1 block w-full" required /></div>
                    <div>
                        <InputLabel value="Campaign (offer)" />
                        <select v-model="form.campaign_id" class="form-select mt-1 w-full" required>
                            <option value="">Select campaign</option>
                            <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Affiliate (optional)" />
                        <select v-model="form.supplier_id" class="form-select mt-1 w-full">
                            <option value="">Any affiliate</option>
                            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div><InputLabel value="Destination URL" /><TextInput v-model="form.destination_url" type="url" class="mt-1 block w-full" placeholder="https://yoursite.com/apply" required /></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><InputLabel value="Payout" /><TextInput v-model="form.payout_amount" type="number" step="0.01" class="mt-1 block w-full" /></div>
                        <div><InputLabel value="Revenue" /><TextInput v-model="form.revenue_amount" type="number" step="0.01" class="mt-1 block w-full" /></div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div><InputLabel value="Daily click cap" /><TextInput v-model="form.cap_daily" type="number" min="0" class="mt-1 block w-full" placeholder="0 = unlimited" /></div>
                        <div><InputLabel value="Monthly click cap" /><TextInput v-model="form.cap_monthly" type="number" min="0" class="mt-1 block w-full" placeholder="0 = unlimited" /></div>
                        <div><InputLabel value="Daily conversion cap" /><TextInput v-model="form.conversion_cap_daily" type="number" min="0" class="mt-1 block w-full" placeholder="0 = unlimited" /></div>
                    </div>
                    <div><InputLabel value="Goal" /><select v-model="form.goal" class="form-select mt-1 w-full"><option v-for="g in goalOptions" :key="g" :value="g">{{ g }}</option></select></div>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.auto_approve_conversions" type="checkbox" /> Auto-approve conversions on lead sold</label>
                    <PrimaryButton :disabled="form.processing">Create link</PrimaryButton>
                </form>
            </Panel>

            <Panel title="Configured links" class="xl:col-span-3">
                <div class="space-y-3">
                    <div v-for="link in links.data" :key="link.id" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold text-slate-900 dark:text-white">{{ link.name }}</p>
                                <p class="text-xs text-slate-500">{{ link.campaign?.name }} · {{ link.supplier?.name ?? 'All affiliates' }}</p>
                            </div>
                            <StatusBadge :status="link.status" />
                        </div>
                        <p class="mt-2 break-all font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ route('click.redirect', link.token) }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ link.clicks_count }} clicks · {{ link.conversions_count }} conversions · {{ link.impressions_count }} impressions</p>
                        <p v-if="link.conversion_postback_url" class="mt-1 truncate font-mono text-xs text-slate-500">Postback: {{ link.conversion_postback_url }}</p>
                        <div v-if="capUsage(link.id)" class="mt-2 space-y-1 text-xs text-slate-500">
                            <div v-if="capUsage(link.id).caps.daily" class="flex items-center gap-2">
                                <span class="w-24">Daily clicks</span>
                                <div class="h-1.5 flex-1 overflow-hidden rounded bg-slate-200 dark:bg-slate-700"><div class="h-full bg-indigo-500" :style="{ width: `${capUsage(link.id).click_daily_pct ?? 0}%` }" /></div>
                                <span>{{ capUsage(link.id).clicks_today }} / {{ capUsage(link.id).caps.daily }}</span>
                            </div>
                            <div v-if="capUsage(link.id).caps.monthly" class="flex items-center gap-2">
                                <span class="w-24">Monthly clicks</span>
                                <div class="h-1.5 flex-1 overflow-hidden rounded bg-slate-200 dark:bg-slate-700"><div class="h-full bg-indigo-500" :style="{ width: `${capUsage(link.id).click_monthly_pct ?? 0}%` }" /></div>
                                <span>{{ capUsage(link.id).clicks_month }} / {{ capUsage(link.id).caps.monthly }}</span>
                            </div>
                            <p v-if="capUsage(link.id).click_cap_reached || capUsage(link.id).conversion_cap_reached" class="font-semibold text-amber-600">Cap reached — redirects may be blocked</p>
                        </div>

                        <form v-if="editingLinkId === link.id" class="mt-4 space-y-4 border-t border-slate-100 pt-4 dark:border-slate-800" @submit.prevent="saveEdit">
                            <div><InputLabel value="Name" /><TextInput v-model="editForm.name" class="mt-1 block w-full" required /></div>
                            <div><InputLabel value="Destination URL" /><TextInput v-model="editForm.destination_url" type="url" class="mt-1 block w-full" required /></div>
                            <div class="grid grid-cols-2 gap-3">
                                <div><InputLabel value="Payout" /><TextInput v-model="editForm.payout_amount" type="number" step="0.01" class="mt-1 block w-full" /></div>
                                <div><InputLabel value="Revenue" /><TextInput v-model="editForm.revenue_amount" type="number" step="0.01" class="mt-1 block w-full" /></div>
                            </div>
                            <div>
                                <InputLabel value="Conversion postback URL" />
                                <p class="mb-2 text-xs text-slate-500">Fired when a conversion is approved. Leave blank to inherit the affiliate default postback URL.</p>
                                <TextInput v-model="editForm.conversion_postback_url" class="mt-1 block w-full font-mono text-sm" placeholder="https://affiliate.example/postback?click_id=[click_id]&payout=[payout]" />
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button
                                        v-for="macro in postbackMacros"
                                        :key="macro"
                                        type="button"
                                        class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 font-mono text-xs text-slate-700 hover:bg-indigo-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                        @click="insertMacro(macro)"
                                    >
                                        {{ macroLabel(macro) }}
                                    </button>
                                </div>
                                <div class="mt-3">
                                    <InputLabel value="HTTP method" />
                                    <select v-model="editForm.conversion_postback_method" class="form-select mt-1 w-full max-w-xs">
                                        <option value="get">GET</option>
                                        <option value="post">POST</option>
                                    </select>
                                </div>
                                <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/40">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Live preview</p>
                                    <p class="mt-1 break-all font-mono text-xs text-slate-800 dark:text-slate-200">{{ postbackPreview || 'Enter a postback URL to preview macro expansion.' }}</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <AppButton type="submit" size="sm" :disabled="editForm.processing" :loading="editForm.processing">Save changes</AppButton>
                                <AppButton type="button" size="sm" variant="secondary" @click="cancelEdit">Cancel</AppButton>
                            </div>
                        </form>

                        <div v-else class="mt-3 flex gap-2">
                            <AppButton variant="secondary" class="!px-3 !py-1.5" @click="startEdit(link)">Edit</AppButton>
                            <AppButton variant="secondary" class="!px-3 !py-1.5" @click="copyUrl(link.token)">Copy URL</AppButton>
                            <AppButton variant="secondary" class="!px-3 !py-1.5" @click="destroy(link.id)">Delete</AppButton>
                        </div>
                    </div>
                </div>
                <Pagination :links="links.links" class="mt-4" />
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
