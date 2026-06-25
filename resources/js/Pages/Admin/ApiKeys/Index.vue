<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

defineProps({ apiKeys: Array, suppliers: Array });

const form = useForm({ name: '', type: 'administrator', supplier_id: '' });
const submit = () => form.post(route('api-keys.store'), { onSuccess: () => form.reset() });
const destroy = (id) => { if (confirm('Revoke this API key?')) router.delete(route('api-keys.destroy', id)); };
</script>

<template>
    <Head title="API Keys" />
    <AuthenticatedLayout>
        <PageHeader title="API Keys" description="Generate and manage API keys for integrations." />

        <div class="space-y-6">
            <Panel title="Generate Key">
                <form @submit.prevent="submit" class="grid gap-4 md:grid-cols-2">
                    <div><InputLabel value="Name" /><TextInput v-model="form.name" class="mt-1 block w-full" required /></div>
                    <div>
                        <InputLabel value="Type" />
                        <select v-model="form.type" class="form-select">
                            <option value="administrator">Administrator</option>
                            <option value="supplier">Supplier</option>
                        </select>
                    </div>
                    <div v-if="form.type === 'supplier'" class="md:col-span-2">
                        <InputLabel value="Supplier" />
                        <select v-model="form.supplier_id" class="form-select">
                            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div class="md:col-span-2"><PrimaryButton>Generate Key</PrimaryButton></div>
                </form>
            </Panel>

            <Panel title="Active Keys" :padding="false">
                <DataTable :empty="!apiKeys?.length">
                    <template #head>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Prefix</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Last Used</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                    </template>
                    <tr v-for="k in apiKeys" :key="k.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ k.name }}</td>
                        <td class="px-6 py-4 capitalize text-slate-600 dark:text-slate-400">{{ k.type }}</td>
                        <td class="px-6 py-4 font-mono text-xs text-slate-500">{{ k.key_prefix }}…</td>
                        <td class="px-6 py-4 text-slate-500">{{ k.last_used_at ?? 'Never' }}</td>
                        <td class="px-6 py-4 text-right">
                            <AppButton variant="ghost" @click="destroy(k.id)">Revoke</AppButton>
                        </td>
                    </tr>
                </DataTable>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
