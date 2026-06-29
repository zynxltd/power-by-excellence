<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    numbers: Object,
    campaigns: Array,
    productSettings: Object,
    searchResults: Array,
    searchMeta: Object,
    defaultCountry: String,
});

const searchForm = useForm({ area_code: props.searchMeta?.area_code ?? '020', country: props.searchMeta?.country ?? props.defaultCountry ?? 'GB' });
const provisionForm = useForm({ campaign_id: '', friendly_name: '', dni_pool: '', area_code: '020' });
const purchaseForm = useForm({ phone_number: '', campaign_id: '', friendly_name: '', dni_pool: '' });

const runSearch = () => searchForm.post(route('call-logic.tracking-numbers.search'), { preserveScroll: true });
const buyNumber = (result) => {
    purchaseForm.phone_number = result.phone_number;
    purchaseForm.campaign_id = provisionForm.campaign_id;
    purchaseForm.friendly_name = result.friendly_name || provisionForm.friendly_name;
    purchaseForm.dni_pool = provisionForm.dni_pool;
    purchaseForm.post(route('call-logic.tracking-numbers.purchase'), { preserveScroll: true });
};
const quickProvision = () => provisionForm.post(route('call-logic.tracking-numbers.store'), { preserveScroll: true, onSuccess: () => provisionForm.reset('friendly_name') });
</script>

<template>
    <Head title="Call Logic - Tracking Numbers" />
    <AuthenticatedLayout>
        <PageHeader title="Tracking numbers" description="Search Twilio inventory by area code, purchase numbers, and auto-configure voice webhooks." />

        <Panel title="Search available numbers" class="mb-4">
            <form class="mb-4 flex flex-wrap items-end gap-3" @submit.prevent="runSearch">
                <div><label class="mb-1 block text-xs text-slate-500">Area code</label><input v-model="searchForm.area_code" class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-800" required /></div>
                <div><label class="mb-1 block text-xs text-slate-500">Country</label><input v-model="searchForm.country" maxlength="2" class="w-16 rounded uppercase dark:border-slate-600 dark:bg-slate-800" /></div>
                <div><label class="mb-1 block text-xs text-slate-500">Campaign</label><select v-model="provisionForm.campaign_id" class="rounded dark:border-slate-600 dark:bg-slate-800"><option value="">None</option><option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
                <AppButton type="submit" :disabled="searchForm.processing">Search</AppButton>
            </form>
            <table v-if="searchResults?.length" class="min-w-full text-sm">
                <thead><tr class="border-b text-left text-xs uppercase text-slate-500"><th class="px-3 py-2">Number</th><th class="px-3 py-2">Locality</th><th class="px-3 py-2"></th></tr></thead>
                <tbody>
                    <tr v-for="result in searchResults" :key="result.sid" class="border-b dark:border-slate-800">
                        <td class="px-3 py-2 font-mono">{{ result.phone_number }}</td>
                        <td class="px-3 py-2">{{ result.locality || '—' }}</td>
                        <td class="px-3 py-2 text-right"><AppButton size="sm" @click="buyNumber(result)">Buy</AppButton></td>
                    </tr>
                </tbody>
            </table>
        </Panel>

        <Panel title="Quick provision" class="mb-4">
            <form class="grid gap-3 md:grid-cols-4" @submit.prevent="quickProvision">
                <input v-model="provisionForm.friendly_name" placeholder="Friendly name" class="rounded dark:border-slate-600 dark:bg-slate-800" />
                <select v-model="provisionForm.campaign_id" class="rounded dark:border-slate-600 dark:bg-slate-800"><option value="">No campaign</option><option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option></select>
                <input v-model="provisionForm.area_code" placeholder="Area code" class="rounded dark:border-slate-600 dark:bg-slate-800" />
                <AppButton type="submit">Provision first match</AppButton>
            </form>
        </Panel>

        <Panel>
            <DataTable>
                <template #head><tr><th>Number</th><th>Name</th><th>Campaign</th><th>Twilio SID</th><th>Webhooks</th><th>Status</th><th></th></tr></template>
                <template #body>
                    <tr v-for="n in numbers.data" :key="n.id">
                        <td class="font-mono">{{ n.phone_number }}</td>
                        <td>{{ n.friendly_name || '—' }}</td>
                        <td>{{ n.campaign?.name || '—' }}</td>
                        <td class="font-mono text-xs">{{ n.provider_sid || '—' }}</td>
                        <td><StatusBadge :status="n.webhook_status || 'pending'" /></td>
                        <td><StatusBadge :status="n.status" /></td>
                        <td><AppButton v-if="n.status === 'active'" variant="danger" size="sm" method="delete" :href="route('call-logic.tracking-numbers.destroy', n.id)">Release</AppButton></td>
                    </tr>
                </template>
            </DataTable>
            <Pagination :links="numbers.links" class="mt-4" />
        </Panel>
    </AuthenticatedLayout>
</template>
