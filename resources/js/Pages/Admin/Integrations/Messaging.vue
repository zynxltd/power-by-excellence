<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    settings: Object,
    providerStatus: Object,
    webhookUrls: Object,
    emailProviders: Array,
    smsProviders: Array,
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const emptyProviders = () => ({
    sendgrid: { key: '' },
    mailgun: { domain: '', secret: '' },
    postmark: { key: '' },
    resend: { key: '' },
    twilio: { sid: '', token: '', from: '' },
    vonage: { key: '', secret: '', from: '' },
});

const mergeProviders = (stored = {}) => {
    const base = emptyProviders();
    for (const [name, values] of Object.entries(stored)) {
        base[name] = { ...base[name], ...(values ?? {}) };
    }

    return base;
};

const form = useForm({
    email_provider: props.settings?.email_provider ?? 'smtp',
    sms_provider: props.settings?.sms_provider ?? 'log',
    from_name: props.settings?.from_name ?? '',
    from_email: props.settings?.from_email ?? '',
    reply_to: props.settings?.reply_to ?? '',
    providers: mergeProviders(props.settings?.providers),
});

const statusLabel = (provider) => (props.providerStatus?.[provider] ? 'Connected' : 'Not configured');
const statusClass = (provider) => (props.providerStatus?.[provider] ? 'text-emerald-600' : 'text-slate-500');

const save = () => form.put(route('integrations.messaging.update'), { preserveScroll: true });
</script>

<template>
    <Head title="Email & SMS Providers" />
    <AuthenticatedLayout>
        <PageHeader title="Email & SMS Providers" description="Tenant ESP credentials for remarketing, auto-responders, and bulk campaigns.">
            <template #actions>
                <Link :href="route('e-delivery.index')" class="text-sm font-medium text-slate-500 hover:text-slate-700">E-Delivery hub</Link>
                <Link :href="route('integrations.index')" class="text-sm font-medium text-slate-500 hover:text-slate-700">← Integrations</Link>
            </template>
        </PageHeader>

        <p v-if="flashSuccess" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ flashSuccess }}
        </p>

        <form class="mx-auto max-w-3xl space-y-6" @submit.prevent="save">
            <Panel title="Default providers">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Email provider" />
                        <select v-model="form.email_provider" class="form-input mt-1 w-full">
                            <option v-for="p in emailProviders" :key="p" :value="p">{{ p }}</option>
                        </select>
                        <p class="mt-1 text-xs" :class="statusClass(form.email_provider)">{{ statusLabel(form.email_provider) }}</p>
                    </div>
                    <div>
                        <InputLabel value="SMS provider" />
                        <select v-model="form.sms_provider" class="form-input mt-1 w-full">
                            <option v-for="p in smsProviders" :key="p" :value="p">{{ p }}</option>
                        </select>
                        <p class="mt-1 text-xs" :class="statusClass(form.sms_provider)">{{ statusLabel(form.sms_provider) }}</p>
                    </div>
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel value="From name" />
                        <TextInput v-model="form.from_name" class="mt-1 w-full" />
                    </div>
                    <div>
                        <InputLabel value="From email" />
                        <TextInput v-model="form.from_email" type="email" class="mt-1 w-full" />
                    </div>
                    <div>
                        <InputLabel value="Reply-to" />
                        <TextInput v-model="form.reply_to" type="email" class="mt-1 w-full" />
                    </div>
                </div>
            </Panel>

            <Panel title="SendGrid">
                <InputLabel value="API key" />
                <TextInput v-model="form.providers.sendgrid.key" type="password" class="mt-1 w-full" autocomplete="off" placeholder="Leave blank to keep existing" />
                <p class="mt-1 text-xs" :class="statusClass('sendgrid')">
                    {{ statusLabel('sendgrid') }} · Webhook: {{ webhookUrls?.sendgrid }}
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
                        <TextInput v-model="form.providers.mailgun.secret" type="password" class="mt-1 w-full" autocomplete="off" placeholder="Leave blank to keep existing" />
                    </div>
                </div>
                <p class="mt-2 text-xs" :class="statusClass('mailgun')">{{ statusLabel('mailgun') }} · Webhook: {{ webhookUrls?.mailgun }}</p>
            </Panel>

            <Panel title="Postmark">
                <InputLabel value="Server token" />
                <TextInput v-model="form.providers.postmark.key" type="password" class="mt-1 w-full" autocomplete="off" placeholder="Leave blank to keep existing" />
                <p class="mt-1 text-xs" :class="statusClass('postmark')">{{ statusLabel('postmark') }} · Webhook: {{ webhookUrls?.postmark }}</p>
            </Panel>

            <Panel title="Resend">
                <InputLabel value="API key" />
                <TextInput v-model="form.providers.resend.key" type="password" class="mt-1 w-full" autocomplete="off" placeholder="Leave blank to keep existing" />
                <p class="mt-1 text-xs" :class="statusClass('resend')">{{ statusLabel('resend') }}</p>
            </Panel>

            <Panel title="Twilio SMS">
                <div class="grid gap-4 sm:grid-cols-3">
                    <TextInput v-model="form.providers.twilio.sid" placeholder="Account SID" class="w-full" />
                    <TextInput v-model="form.providers.twilio.token" type="password" placeholder="Auth token" class="w-full" autocomplete="off" />
                    <TextInput v-model="form.providers.twilio.from" placeholder="From number" class="w-full" />
                </div>
                <p class="mt-2 text-xs" :class="statusClass('twilio')">{{ statusLabel('twilio') }}</p>
            </Panel>

            <Panel title="Vonage SMS">
                <div class="grid gap-4 sm:grid-cols-3">
                    <TextInput v-model="form.providers.vonage.key" placeholder="API key" class="w-full" />
                    <TextInput v-model="form.providers.vonage.secret" type="password" placeholder="API secret" class="w-full" autocomplete="off" />
                    <TextInput v-model="form.providers.vonage.from" placeholder="Sender ID" class="w-full" />
                </div>
                <p class="mt-2 text-xs" :class="statusClass('vonage')">{{ statusLabel('vonage') }}</p>
            </Panel>

            <AppButton type="submit" :disabled="form.processing">Save providers</AppButton>
        </form>
    </AuthenticatedLayout>
</template>
