<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ supplier: Object, portalUser: Object });

const steps = [
    { id: 'basics', label: 'Basics' },
    { id: 'affiliate', label: 'Affiliate' },
    { id: 'sources', label: 'Traffic sources' },
    { id: 'portal', label: 'Portal access' },
];
const currentStep = ref('basics');

const initialSources = props.supplier?.sources?.length
    ? props.supplier.sources.map((s) => ({
        sid: s.sid,
        name: s.name ?? '',
        payout_override: s.payout_override ?? '',
        sub_suppliers: (s.sub_suppliers ?? []).map((sub) => ({
            ssid: sub.ssid,
            name: sub.name ?? '',
        })),
    }))
    : [{ sid: '', name: '', payout_override: '', sub_suppliers: [] }];

const affiliate = props.supplier?.affiliate_settings ?? {};

const form = useForm({
    reference: props.supplier?.reference ?? '',
    name: props.supplier?.name ?? '',
    status: props.supplier?.status ?? 'active',
    rev_share_percent: affiliate.rev_share_percent ?? '',
    default_postback_url: affiliate.default_postback_url ?? '',
    enable_sub_suppliers: affiliate.enable_sub_suppliers ?? true,
    sources: initialSources,
    portal_email: props.portalUser?.email ?? '',
    portal_name: props.portalUser?.name ?? '',
    portal_password: '',
});

const addSource = () => form.sources.push({ sid: '', name: '', payout_override: '', sub_suppliers: [] });
const addSubSupplier = (sourceIndex) => {
    if (!form.sources[sourceIndex].sub_suppliers) {
        form.sources[sourceIndex].sub_suppliers = [];
    }
    form.sources[sourceIndex].sub_suppliers.push({ ssid: '', name: '' });
};
const removeSource = (i) => { if (form.sources.length > 1) form.sources.splice(i, 1); };

const submit = () => {
    form.reference = String(form.reference).toLowerCase().replace(/[^a-z0-9_-]/g, '');
    form.sources = form.sources
        .map((s) => ({ ...s, sid: String(s.sid).toLowerCase().replace(/[^a-z0-9_-]/g, '') }))
        .filter((s) => s.sid);
    props.supplier ? form.put(route('suppliers.update', props.supplier.id)) : form.post(route('suppliers.store'));
};
</script>

<template>
    <Head :title="supplier ? 'Edit Supplier' : 'New Supplier'" />
    <AuthenticatedLayout>
        <PageHeader :title="supplier ? 'Edit Supplier' : 'New Supplier'" description="Affiliate profile, SIDs for API attribution, and supplier portal login.">
            <template v-if="supplier" #actions>
                <a :href="route('suppliers.show', supplier.id)" class="text-sm text-indigo-600 hover:underline">← Back to supplier</a>
            </template>
        </PageHeader>

        <div class="mb-6 flex flex-wrap gap-2">
            <button
                v-for="s in steps"
                :key="s.id"
                type="button"
                :class="['rounded-lg px-3 py-1.5 text-sm font-medium', currentStep === s.id ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300']"
                @click="currentStep = s.id"
            >
                {{ s.label }}
            </button>
        </div>

        <Panel class="max-w-2xl">
            <FormErrorSummary :errors="form.errors" />
            <form @submit.prevent="submit" class="space-y-6">
                <div v-show="currentStep === 'basics'" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Reference" />
                            <TextInput v-model="form.reference" class="mt-1 font-mono" required placeholder="affiliate-main" />
                            <InputError class="mt-1" :message="form.errors.reference" />
                        </div>
                        <div>
                            <InputLabel value="Status" />
                            <select v-model="form.status" class="form-select mt-1 w-full">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Display name" />
                        <TextInput v-model="form.name" class="mt-1" required />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>
                </div>

                <div v-show="currentStep === 'affiliate'" class="space-y-4">
                    <p class="text-sm text-slate-600 dark:text-slate-400">Advanced affiliate settings — rev-share, postbacks, and sub-ID (SSID) tracking for publisher hierarchies.</p>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Rev-share %" />
                            <TextInput v-model="form.rev_share_percent" type="number" min="0" max="100" step="0.1" class="mt-1" placeholder="35" />
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                                <input v-model="form.enable_sub_suppliers" type="checkbox" class="rounded" />
                                Enable sub-affiliate SSID tracking
                            </label>
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Default postback URL" />
                        <TextInput v-model="form.default_postback_url" class="mt-1 font-mono text-sm" placeholder="https://affiliate.example/postback?click_id=[lead_uuid]" />
                        <p class="mt-1 text-xs text-slate-500">Fired on sold/accepted when no campaign postback overrides. Tags: [lead_uuid], [sid], [ssid], [revenue], [payout]</p>
                    </div>
                </div>

                <div v-show="currentStep === 'sources'" class="space-y-4">
                    <p class="text-sm text-slate-600 dark:text-slate-400">Each SID is passed in the <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">source</code> field when submitting leads via API.</p>
                    <div
                        v-for="(source, i) in form.sources"
                        :key="i"
                        class="grid gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700 sm:grid-cols-3"
                    >
                        <div>
                            <InputLabel value="SID" />
                            <TextInput v-model="source.sid" class="mt-1 font-mono" placeholder="google_search" />
                        </div>
                        <div>
                            <InputLabel value="Label" />
                            <TextInput v-model="source.name" class="mt-1" placeholder="Google Search" />
                        </div>
                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <InputLabel value="Payout override (£)" />
                                <TextInput v-model="source.payout_override" type="number" step="0.01" min="0" class="mt-1" placeholder="Campaign default" />
                            </div>
                            <button v-if="form.sources.length > 1" type="button" class="pb-2 text-sm text-rose-500" @click="removeSource(i)">×</button>
                        </div>
                        <div v-if="form.enable_sub_suppliers" class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-700">
                            <p class="mb-2 text-xs font-semibold uppercase text-slate-500">Sub-affiliates (SSID)</p>
                            <div v-for="(sub, si) in source.sub_suppliers ?? []" :key="si" class="mb-2 grid gap-2 sm:grid-cols-2">
                                <TextInput v-model="sub.ssid" class="font-mono" placeholder="sub_publisher_1" />
                                <TextInput v-model="sub.name" placeholder="Sub-affiliate label" />
                            </div>
                            <button type="button" class="text-xs font-medium text-indigo-600 hover:underline" @click="addSubSupplier(i)">+ Add SSID</button>
                        </div>
                    </div>
                    <button type="button" class="text-sm font-medium text-indigo-600 hover:underline" @click="addSource">+ Add SID</button>
                </div>

                <div v-show="currentStep === 'portal'" class="space-y-4">
                    <p class="text-sm text-slate-600 dark:text-slate-400">Suppliers log in at <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">/portal/supplier</code> to view submissions and payouts.</p>
                    <div>
                        <InputLabel value="Portal login email" />
                        <TextInput v-model="form.portal_email" type="email" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Portal display name" />
                        <TextInput v-model="form.portal_name" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel :value="supplier ? 'New portal password (optional)' : 'Portal password'" />
                        <TextInput v-model="form.portal_password" type="password" class="mt-1" />
                        <InputError class="mt-1" :message="form.errors.portal_password" />
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-slate-200 pt-4 dark:border-slate-700">
                    <p class="text-xs text-slate-500">Issue API keys under Tools → API Keys for this supplier.</p>
                    <PrimaryButton :disabled="form.processing">{{ supplier ? 'Update' : 'Create' }} supplier</PrimaryButton>
                </div>
            </form>
        </Panel>
    </AuthenticatedLayout>
</template>
