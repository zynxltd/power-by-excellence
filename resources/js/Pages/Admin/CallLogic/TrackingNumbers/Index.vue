<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    numbers: Object,
    campaigns: Array,
    productSettings: Object,
});

const form = useForm({
    campaign_id: '',
    friendly_name: '',
    dni_pool: '',
    area_code: '020',
});

const submit = () => form.post(route('call-logic.tracking-numbers.store'), { preserveScroll: true, onSuccess: () => form.reset() });
</script>

<template>
    <Head title="Call Logic - Tracking Numbers" />
    <AuthenticatedLayout>
        <PageHeader title="Tracking numbers" description="DIDs for call tracking and dynamic number insertion." />

        <Panel title="Add number" class="mb-4">
            <form class="grid gap-3 md:grid-cols-4" @submit.prevent="submit">
                <input v-model="form.friendly_name" placeholder="Friendly name" class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
                <select v-model="form.campaign_id" class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-800">
                    <option value="">No campaign</option>
                    <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <input v-model="form.dni_pool" placeholder="DNI pool (optional)" class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
                <AppButton type="submit" :disabled="form.processing">Provision number</AppButton>
            </form>
        </Panel>

        <Panel>
            <DataTable>
                <template #head>
                    <tr><th>Number</th><th>Name</th><th>Campaign</th><th>Pool</th><th>Provider</th><th></th></tr>
                </template>
                <template #body>
                    <tr v-for="n in numbers.data" :key="n.id">
                        <td class="font-mono">{{ n.phone_number }}</td>
                        <td>{{ n.friendly_name || '—' }}</td>
                        <td>{{ n.campaign?.name || '—' }}</td>
                        <td>{{ n.dni_pool || '—' }}</td>
                        <td>{{ n.provider }}</td>
                        <td>
                            <AppButton v-if="n.status === 'active'" variant="danger" size="sm" method="delete" :href="route('call-logic.tracking-numbers.destroy', n.id)">Release</AppButton>
                        </td>
                    </tr>
                </template>
            </DataTable>
            <Pagination :links="numbers.links" class="mt-4" />
        </Panel>
    </AuthenticatedLayout>
</template>
