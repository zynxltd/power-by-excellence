<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    stripe: Object,
    webhookUrl: String,
});

const form = useForm({
    enabled: props.stripe?.enabled ?? false,
    mode: props.stripe?.mode ?? 'test',
    publishable_key: props.stripe?.publishable_key ?? '',
    secret_key: props.stripe?.secret_key ?? '',
    webhook_secret: props.stripe?.webhook_secret ?? '',
    buyer_self_serve_topup: props.stripe?.buyer_self_serve_topup ?? true,
});

const submit = () => form.put(route('integrations.stripe.update'));
</script>

<template>
    <Head title="Stripe Payments" />
    <AuthenticatedLayout>
        <PageHeader
            title="Stripe Payments"
            description="Connect Stripe for buyer card top-ups and automated billing."
        >
            <template #actions>
                <Link :href="route('integrations.index')" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                    ← Integrations
                </Link>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-2">
            <Panel title="Stripe connection">
                <form class="space-y-4" @submit.prevent="submit">
                    <label class="flex items-center gap-3">
                        <input v-model="form.enabled" type="checkbox" class="rounded border-slate-300" />
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Enable Stripe payments</span>
                    </label>
                    <div>
                        <InputLabel value="Mode" />
                        <select v-model="form.mode" class="form-select mt-1 w-full max-w-xs">
                            <option value="test">Test</option>
                            <option value="live">Live</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Publishable key" />
                        <input v-model="form.publishable_key" type="text" class="form-input mt-1 w-full font-mono text-sm" placeholder="pk_test_..." />
                    </div>
                    <div>
                        <InputLabel value="Secret key" />
                        <input v-model="form.secret_key" type="password" class="form-input mt-1 w-full font-mono text-sm" placeholder="sk_test_..." />
                    </div>
                    <div>
                        <InputLabel value="Webhook signing secret" />
                        <input v-model="form.webhook_secret" type="password" class="form-input mt-1 w-full font-mono text-sm" placeholder="whsec_..." />
                    </div>
                    <label class="flex items-center gap-3">
                        <input v-model="form.buyer_self_serve_topup" type="checkbox" class="rounded border-slate-300" />
                        <span class="text-sm text-slate-600 dark:text-slate-400">Allow buyers to top up credit via portal</span>
                    </label>
                    <AppButton type="submit" :disabled="form.processing">Save Stripe settings</AppButton>
                </form>
            </Panel>

            <Panel title="Webhook endpoint">
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Add this URL in your Stripe dashboard under Developers → Webhooks.
                </p>
                <code class="mt-3 block break-all rounded-lg bg-slate-100 p-3 text-xs dark:bg-slate-800">{{ webhookUrl }}</code>
                <ul class="mt-4 list-disc space-y-1 pl-5 text-xs text-slate-500">
                    <li>Listen for <code>checkout.session.completed</code> and <code>payment_intent.succeeded</code></li>
                    <li>Buyer portal billing uses Stripe Checkout when enabled</li>
                </ul>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
