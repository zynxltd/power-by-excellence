<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import FormSetupLayout from '@/Components/UI/FormSetupLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useFormSteps } from '@/Composables/useFormSteps';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ supplier: Object, portalUser: Object });

const { currency } = useMoneyFormat();

const steps = [
    { id: 'basics', label: 'Basics', num: 1 },
    { id: 'affiliate', label: 'Affiliate', num: 2 },
    { id: 'sources', label: 'Traffic sources', num: 3 },
    { id: 'portal', label: 'Portal access', num: 4 },
];

const { currentStep, goStep, stepStatus, nextStep, prevStep } = useFormSteps(steps, {
    isEdit: !!props.supplier,
});

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

    if (!form.reference?.trim() || !form.name?.trim()) {
        goStep('basics');
        return;
    }

    const options = {
        preserveScroll: true,
        onError: () => {
            const errors = form.errors ?? {};
            if (errors.reference || errors.name || errors.status) {
                goStep('basics');
            } else if (errors.rev_share_percent || errors.default_postback_url || errors.enable_sub_suppliers) {
                goStep('affiliate');
            } else if (Object.keys(errors).some((k) => k.startsWith('sources'))) {
                goStep('sources');
            } else {
                goStep('portal');
            }
        },
    };

    props.supplier ? form.put(route('suppliers.update', props.supplier.id), options) : form.post(route('suppliers.store'), options);
};
</script>

<template>
    <Head :title="supplier ? 'Edit Supplier' : 'New Supplier'" />
    <AuthenticatedLayout>
        <PageHeader :title="supplier ? 'Edit Supplier' : 'New Supplier'" description="Step-by-step setup - affiliate profile, SIDs, and portal login.">
            <template v-if="supplier" #actions>
                <AppButton :href="route('suppliers.show', supplier.id)" variant="secondary">View supplier</AppButton>
            </template>
        </PageHeader>

        <FormSetupLayout :steps="steps" :current-step="currentStep" :step-status="stepStatus" @go="goStep">
            <template #sidebar>
                <Panel title="Summary" class="mt-4">
                    <dl class="space-y-2 text-sm">
                        <div v-if="form.name">
                            <dt class="text-slate-500">Name</dt>
                            <dd class="font-medium">{{ form.name }}</dd>
                        </div>
                        <div v-if="form.reference">
                            <dt class="text-slate-500">Reference</dt>
                            <dd class="font-mono text-xs font-medium">{{ form.reference }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">SIDs</dt>
                            <dd class="font-medium">{{ form.sources.filter((s) => s.sid).length || '-' }}</dd>
                        </div>
                    </dl>
                </Panel>
            </template>

            <form class="space-y-6" novalidate @submit.prevent="submit">
                <FormErrorSummary :errors="form.errors" />

                <Panel v-show="currentStep === 'basics'" title="1. Supplier profile">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Reference" />
                            <TextInput v-model="form.reference" class="mt-1 w-full font-mono" required placeholder="affiliate-main" />
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
                    <div class="mt-4">
                        <InputLabel value="Display name" />
                        <TextInput v-model="form.name" class="mt-1 w-full" required />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>
                    <div class="mt-4 flex justify-end">
                        <AppButton type="button" @click="nextStep">Next: Affiliate →</AppButton>
                    </div>
                </Panel>

                <Panel v-show="currentStep === 'affiliate'" title="2. Affiliate settings">
                    <p class="text-sm text-slate-600 dark:text-slate-400">Rev-share, postbacks, and sub-ID (SSID) tracking for publisher hierarchies.</p>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Rev-share %" />
                            <TextInput v-model="form.rev_share_percent" type="number" min="0" max="100" step="0.1" class="mt-1 w-full" placeholder="35" />
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                                <input v-model="form.enable_sub_suppliers" type="checkbox" class="rounded" />
                                Enable sub-affiliate SSID tracking
                            </label>
                        </div>
                    </div>
                    <div class="mt-4">
                        <InputLabel value="Default postback URL" />
                        <TextInput v-model="form.default_postback_url" class="mt-1 w-full font-mono text-sm" placeholder="https://affiliate.example/postback?click_id=[lead_uuid]" />
                        <p class="mt-1 text-xs text-slate-500">Synced to <a :href="route('postbacks.index')" class="text-indigo-600 hover:underline">Postback Manager</a> for sold, accepted, rejected, and unsold events. Tags: [lead_uuid], [sid], [ssid], [revenue], [payout]</p>
                    </div>
                    <div class="mt-4 flex justify-between">
                        <AppButton type="button" variant="secondary" @click="prevStep">← Back</AppButton>
                        <AppButton type="button" @click="nextStep">Next: Traffic sources →</AppButton>
                    </div>
                </Panel>

                <Panel v-show="currentStep === 'sources'" title="3. Traffic sources (SIDs)">
                    <p class="text-sm text-slate-600 dark:text-slate-400">Each SID is passed in the <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">source</code> field when submitting leads via API.</p>
                    <div
                        v-for="(source, i) in form.sources"
                        :key="i"
                        class="mt-4 grid gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700 sm:grid-cols-3"
                    >
                        <div>
                            <InputLabel value="SID" />
                            <TextInput v-model="source.sid" class="mt-1 w-full font-mono" placeholder="google_search" />
                        </div>
                        <div>
                            <InputLabel value="Label" />
                            <TextInput v-model="source.name" class="mt-1 w-full" placeholder="Google Search" />
                        </div>
                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <InputLabel :value="`Payout override (${currency})`" />
                                <TextInput v-model="source.payout_override" type="number" step="0.01" min="0" class="mt-1 w-full" placeholder="Campaign default" />
                            </div>
                            <button v-if="form.sources.length > 1" type="button" class="pb-2 text-sm text-rose-500" @click="removeSource(i)">×</button>
                        </div>
                        <div v-if="form.enable_sub_suppliers" class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-700 sm:col-span-3">
                            <p class="mb-2 text-xs font-semibold uppercase text-slate-500">Sub-affiliates (SSID)</p>
                            <div v-for="(sub, si) in source.sub_suppliers ?? []" :key="si" class="mb-2 grid gap-2 sm:grid-cols-2">
                                <TextInput v-model="sub.ssid" class="font-mono" placeholder="sub_publisher_1" />
                                <TextInput v-model="sub.name" placeholder="Sub-affiliate label" />
                            </div>
                            <button type="button" class="text-xs font-medium text-indigo-600 hover:underline" @click="addSubSupplier(i)">+ Add SSID</button>
                        </div>
                    </div>
                    <button type="button" class="mt-3 text-sm font-medium text-indigo-600 hover:underline" @click="addSource">+ Add SID</button>
                    <div class="mt-4 flex justify-between">
                        <AppButton type="button" variant="secondary" @click="prevStep">← Back</AppButton>
                        <AppButton type="button" @click="nextStep">Next: Portal access →</AppButton>
                    </div>
                </Panel>

                <Panel v-show="currentStep === 'portal'" title="4. Portal access">
                    <p class="text-sm text-slate-600 dark:text-slate-400">Suppliers log in at <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">/portal/supplier</code> to view submissions and payouts.</p>
                    <div class="mt-4">
                        <InputLabel value="Portal login email" />
                        <TextInput v-model="form.portal_email" type="email" class="mt-1 w-full" />
                    </div>
                    <div class="mt-4">
                        <InputLabel value="Portal display name" />
                        <TextInput v-model="form.portal_name" class="mt-1 w-full" />
                    </div>
                    <div class="mt-4">
                        <InputLabel :value="supplier ? 'New portal password (optional)' : 'Portal password'" />
                        <TextInput v-model="form.portal_password" type="password" class="mt-1 w-full" />
                        <InputError class="mt-1" :message="form.errors.portal_password" />
                    </div>
                    <p class="mt-4 text-xs text-slate-500">Issue API keys under Tools → API Keys for this supplier.</p>
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <AppButton type="button" variant="secondary" @click="prevStep">← Back</AppButton>
                        <PrimaryButton :disabled="form.processing" :loading="form.processing">{{ supplier ? 'Update' : 'Create' }} supplier</PrimaryButton>
                    </div>
                </Panel>
            </form>
        </FormSetupLayout>
    </AuthenticatedLayout>
</template>
