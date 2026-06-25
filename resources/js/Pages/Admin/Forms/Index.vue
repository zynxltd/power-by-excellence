<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ forms: Object, campaigns: Array, verticals: Array });

const showCreate = ref(false);
const form = useForm({
    campaign_id: props.campaigns[0]?.id ?? '',
    name: '',
    config: { redirect_url: '', allowed_domains: [], css: '' },
});

const campaignsByVertical = computed(() => {
    const groups = {};
    for (const c of props.campaigns ?? []) {
        const key = c.vertical_id || 'other';
        groups[key] ??= { vertical_id: key, label: c.vertical_label || 'Other', campaigns: [] };
        groups[key].campaigns.push(c);
    }
    return Object.values(groups);
});

const submit = () => {
    form.post(route('forms.store'), { onSuccess: () => { showCreate.value = false; form.reset(); } });
};
</script>

<template>
    <Head title="Form Builder" />
    <AuthenticatedLayout>
        <PageHeader title="Form Builder" description="Hosted lead capture forms — domain lock, custom CSS, redirects.">
            <template #actions>
                <AppButton @click="showCreate = !showCreate">{{ showCreate ? 'Cancel' : 'New Form' }}</AppButton>
            </template>
        </PageHeader>

        <Panel v-if="showCreate" class="mb-6">
            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Campaign / vertical</label>
                    <select v-model="form.campaign_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-800">
                        <optgroup v-for="group in campaignsByVertical" :key="group.vertical_id" :label="group.label">
                            <option v-for="c in group.campaigns" :key="c.id" :value="c.id">{{ c.name }} ({{ c.reference }})</option>
                        </optgroup>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Form name</label>
                    <input v-model="form.name" type="text" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-800" required />
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Thank-you redirect URL</label>
                    <input v-model="form.config.redirect_url" type="url" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-800" placeholder="https://yoursite.com/thanks" />
                </div>
                <div class="md:col-span-2">
                    <AppButton type="submit" :disabled="form.processing">Create form</AppButton>
                </div>
            </form>
        </Panel>

        <Panel :padding="false">
            <DataTable :empty="!forms?.data?.length">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Vertical</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Campaign</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">Embed URL</th>
                </template>
                <tr v-for="f in forms.data" :key="f.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ f.name }}</td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ f.campaign?.vertical_id?.replace('_', ' ') ?? '—' }}</td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ f.campaign?.name }}</td>
                    <td class="px-6 py-4">
                        <a :href="route('forms.show', f.slug)" target="_blank" class="text-sm text-indigo-600 dark:text-indigo-400">{{ route('forms.show', f.slug) }}</a>
                        <Link :href="route('forms.edit', f.id)" class="ml-3 text-sm text-slate-500 hover:text-indigo-600">Edit</Link>
                    </td>
                </tr>
            </DataTable>
            <Pagination :links="forms.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
