<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({ entitlement: Object, settings: Object, plans: Object });

const form = useForm({
    enabled: settings?.enabled ?? false,
    clicks_cap: settings?.clicks_cap ?? '',
    conversions_cap: settings?.conversions_cap ?? '',
});

const submit = () => form.patch(route('click-track.settings.update'));
</script>

<template>
    <Head title="Click Track Settings" />
    <AuthenticatedLayout>
        <PageHeader title="Click Track settings" description="Enable the Lynx-style click tracking plan and configure usage caps." />

        <div class="grid gap-6 lg:grid-cols-2">
            <Panel title="Plan & entitlement">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Plan</dt><dd class="font-semibold">{{ entitlement?.plan_label }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Entitled</dt><dd class="font-semibold">{{ entitlement?.entitled ? 'Yes' : 'No' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Clicks used</dt><dd>{{ entitlement?.clicks_used }} / {{ entitlement?.clicks_cap ?? '∞' }}</dd></div>
                </dl>
            </Panel>

            <Panel title="Configuration">
                <form class="space-y-4" @submit.prevent="submit">
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.enabled" type="checkbox" /> Enable Click Track add-on</label>
                    <div><InputLabel value="Custom clicks cap (optional)" /><TextInput v-model="form.clicks_cap" type="number" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Custom conversions cap (optional)" /><TextInput v-model="form.conversions_cap" type="number" class="mt-1 block w-full" /></div>
                    <PrimaryButton :disabled="form.processing">Save settings</PrimaryButton>
                </form>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
