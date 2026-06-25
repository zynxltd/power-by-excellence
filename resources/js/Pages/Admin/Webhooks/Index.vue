<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

defineProps({ webhooks: Array });

const form = useForm({ name: '', url: '', events: ['lead.sold'], is_active: true });
const submit = () => form.post(route('webhooks.store'), { onSuccess: () => form.reset() });
const destroy = (id) => { if (confirm('Delete this webhook?')) router.delete(route('webhooks.destroy', id)); };
</script>

<template>
    <Head title="Webhooks" />
    <AuthenticatedLayout>
        <PageHeader title="Webhooks" description="Receive outbound event notifications for lead activity." />

        <div class="space-y-6">
            <Panel title="Add Webhook">
                <form @submit.prevent="submit" class="space-y-4">
                    <div><InputLabel value="Name" /><TextInput v-model="form.name" class="mt-1 block w-full" required /></div>
                    <div><InputLabel value="Endpoint URL" /><TextInput v-model="form.url" type="url" class="mt-1 block w-full" placeholder="https://..." required /></div>
                    <PrimaryButton>Add Webhook</PrimaryButton>
                </form>
            </Panel>

            <Panel title="Active Webhooks">
                <div v-if="!webhooks?.length" class="py-8 text-center text-sm text-slate-500">No webhooks configured yet.</div>
                <div v-for="w in webhooks" :key="w.id" class="flex items-center justify-between border-b border-slate-100 py-4 last:border-0 dark:border-slate-800">
                    <div>
                        <p class="font-medium text-slate-900 dark:text-white">{{ w.name }}</p>
                        <p class="mt-0.5 font-mono text-xs text-slate-500">{{ w.url }}</p>
                    </div>
                    <AppButton variant="danger" @click="destroy(w.id)">Delete</AppButton>
                </div>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
