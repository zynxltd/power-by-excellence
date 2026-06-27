<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    settings: Object,
    providerStatus: Object,
    webhookUrls: Object,
    emailProviders: Array,
    smsProviders: Array,
});

const form = useForm({
    email_provider: props.settings?.email_provider ?? 'smtp',
    sms_provider: props.settings?.sms_provider ?? 'log',
    from_name: props.settings?.from_name ?? '',
    from_email: props.settings?.from_email ?? '',
    reply_to: props.settings?.reply_to ?? '',
    providers: props.settings?.providers ?? {},
});

const save = () => form.put(route('integrations.messaging.update'), { preserveScroll: true });
</script>

<template>
    <Head title="Email & SMS Providers" />
    <AuthenticatedLayout>
        <PageHeader title="Email & SMS Providers" description="Tenant ESP credentials for remarketing, auto-responders, and bulk campaigns.">
            <template #actions>
                <Link :href="route('integrations.index')" class="text-sm font-medium text-slate-500 hover:text-slate-700">← Integrations</Link>
            </template>
        </PageHeader>

        <form class="mx-auto max-w-3xl space-y-6" @submit.prevent="save">
            <Panel title="Default providers">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Email provider" />
                        <select v-model="form.email_provider" class="form-input mt-1 w-full">
                            <option v-for="p in emailProviders" :key="p" :value="p">{{ p }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="SMS provider" />
                        <select v-model="form.sms_provider" class="form-input mt-1 w-full">
                            <option v-for="p in smsProviders" :key="p" :value="p">{{ p }}</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="From name" />
                        <TextInput v-model="form.from_name" class="mt-1 w-full" />
                    </div>
                    <div>
                        <InputLabel value="From email" />
                        <TextInput v-model="form.from_email" type="email" class="mt-1 w-full" />
                    </div>
                </div>
            </Panel>

            <Panel title="SendGrid">
                <InputLabel value="API key" />
                <TextInput v-model="form.providers.sendgrid.key" type="password" class="mt-1 w-full" autocomplete="off" />
                <p class="mt-1 text-xs" :class="providerStatus?.sendgrid ? 'text-emerald-600' : 'text-slate-500'">
                    {{ providerStatus?.sendgrid ? 'Connected' : 'Not configured' }} · Webhook: {{ webhookUrls?.sendgrid }}
                </p>
            </Panel>

            <Panel title="Mailgun">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Domain" />
                        <TextInput v-model="form.providers.mailgun.domain" class="mt-1 w-full" />
                    </div>
                    <div>
                        <InputLabel value="API secret" />
                        <TextInput v-model="form.providers.mailgun.secret" type="password" class="mt-1 w-full" autocomplete="off" />
                    </div>
                </div>
            </Panel>

            <Panel title="Twilio SMS">
                <div class="grid gap-4 sm:grid-cols-3">
                    <TextInput v-model="form.providers.twilio.sid" placeholder="Account SID" class="w-full" />
                    <TextInput v-model="form.providers.twilio.token" type="password" placeholder="Auth token" class="w-full" autocomplete="off" />
                    <TextInput v-model="form.providers.twilio.from" placeholder="From number" class="w-full" />
                </div>
            </Panel>

            <AppButton type="submit" :disabled="form.processing">Save providers</AppButton>
        </form>
    </AuthenticatedLayout>
</template>
