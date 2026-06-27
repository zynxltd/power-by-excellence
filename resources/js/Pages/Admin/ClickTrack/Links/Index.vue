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

const props = defineProps({
    entitlement: Object,
    links: Object,
    campaigns: Array,
    suppliers: Array,
    buyers: Array,
    goalOptions: Array,
});

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
                        <div class="mt-3 flex gap-2">
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
