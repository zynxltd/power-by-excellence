<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    enabled: Boolean,
    settings: Object,
    webhookUrl: String,
    sdkUrl: String,
    telephonyProvider: String,
});

const form = useForm({
    enabled: props.enabled,
    max_tracking_numbers: props.settings.max_tracking_numbers,
    recording_enabled: props.settings.recording_enabled,
    concurrent_call_cap: props.settings.concurrent_call_cap,
});

const submit = () => form.put(route('call-logic.settings.update'));
</script>

<template>
    <Head title="Call Logic Settings" />
    <AuthenticatedLayout>
        <PageHeader title="Call Logic" description="Enable pay-per-call tracking, IVR, and ping-post call routing." />

        <form class="max-w-xl space-y-4" @submit.prevent="submit">
            <Panel>
                <label class="flex items-center gap-2 font-medium">
                    <input v-model="form.enabled" type="checkbox" class="rounded" /> Enable Call Logic product
                </label>
                <p class="mt-2 text-sm text-slate-500">Provider: {{ telephonyProvider }}</p>
            </Panel>

            <Panel title="Limits">
                <div class="space-y-3">
                    <div>
                        <label class="text-sm">Max tracking numbers</label>
                        <input v-model.number="form.max_tracking_numbers" type="number" min="1" class="mt-1 w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
                    </div>
                    <div>
                        <label class="text-sm">Concurrent call cap (account)</label>
                        <input v-model.number="form.concurrent_call_cap" type="number" min="1" class="mt-1 w-full rounded border-slate-300 dark:border-slate-800 dark:bg-slate-800" />
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.recording_enabled" type="checkbox" class="rounded" /> Enable call recording
                    </label>
                </div>
            </Panel>

            <Panel title="Integration">
                <dl class="text-sm space-y-2">
                    <dt class="text-slate-500">Twilio voice webhook</dt>
                    <dd class="font-mono text-xs break-all">{{ webhookUrl }}</dd>
                    <dt class="text-slate-500">DNI SDK</dt>
                    <dd class="font-mono text-xs break-all">{{ sdkUrl }}</dd>
                </dl>
            </Panel>

            <AppButton type="submit" :disabled="form.processing">Save settings</AppButton>
        </form>
    </AuthenticatedLayout>
</template>
