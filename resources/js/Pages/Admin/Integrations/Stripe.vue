<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    stripe: Object,
    webhookUrl: String,
});

const copied = ref('');

const form = useForm({
    enabled: props.stripe?.enabled ?? false,
    allow_buyer_self_serve: props.stripe?.allow_buyer_self_serve ?? true,
    min_topup: props.stripe?.min_topup ?? 1,
    preset_amounts: (props.stripe?.preset_amounts ?? [50, 100, 250, 500, 1000]).join(', '),
    key: props.stripe?.key ?? '',
    secret: props.stripe?.secret ?? '',
    webhook_secret: props.stripe?.webhook_secret ?? '',
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        preset_amounts: String(data.preset_amounts)
            .split(',')
            .map((value) => parseFloat(value.trim()))
            .filter((value) => !Number.isNaN(value) && value >= 1),
    })).put(route('integrations.stripe.update'));
};

const copyText = async (key, value) => {
    if (!value) return;
    await navigator.clipboard.writeText(value);
    copied.value = key;
    setTimeout(() => { copied.value = ''; }, 2000);
};
</script>

<template>
    <Head title="Stripe integration" />
    <AuthenticatedLayout>
        <PageHeader title="Stripe checkout" description="Enable buyer self-serve credit top-ups via Stripe Checkout.">
            <template #actions>
                <Link :href="route('integrations.index')" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                    ← Integrations
                </Link>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-2">
            <Panel title="Settings">
                <form class="space-y-4" @submit.prevent="submit">
                    <FormErrorSummary :errors="form.errors" />

                    <label class="flex items-center gap-3">
                        <input v-model="form.enabled" type="checkbox" class="rounded border-slate-300" />
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Enable Stripe checkout for buyers</span>
                    </label>

                    <label class="flex items-center gap-3">
                        <input v-model="form.allow_buyer_self_serve" type="checkbox" class="rounded border-slate-300" />
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Allow buyer self-serve top-ups in portal</span>
                    </label>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Minimum top-up amount" />
                            <input v-model="form.min_topup" type="number" min="1" step="0.01" class="form-input mt-1 w-full" />
                            <InputError class="mt-1" :message="form.errors.min_topup" />
                        </div>
                        <div>
                            <InputLabel value="Preset amounts (comma-separated)" />
                            <input v-model="form.preset_amounts" type="text" class="form-input mt-1 w-full font-mono text-sm" placeholder="50, 100, 250, 500" />
                            <InputError class="mt-1" :message="form.errors.preset_amounts" />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Publishable key" />
                        <input v-model="form.key" type="text" class="form-input mt-1 w-full font-mono text-sm" autocomplete="off" />
                        <InputError class="mt-1" :message="form.errors.key" />
                    </div>

                    <div>
                        <InputLabel value="Secret key" />
                        <input v-model="form.secret" type="password" class="form-input mt-1 w-full font-mono text-sm" autocomplete="off" placeholder="Leave blank to keep existing" />
                        <InputError class="mt-1" :message="form.errors.secret" />
                    </div>

                    <div>
                        <InputLabel value="Webhook signing secret" />
                        <input v-model="form.webhook_secret" type="password" class="form-input mt-1 w-full font-mono text-sm" autocomplete="off" placeholder="Leave blank to keep existing" />
                        <InputError class="mt-1" :message="form.errors.webhook_secret" />
                    </div>

                    <PrimaryButton :disabled="form.processing">Save Stripe settings</PrimaryButton>
                </form>
            </Panel>

            <Panel title="Webhook endpoint">
                <div class="space-y-4 text-sm text-slate-600 dark:text-slate-400">
                    <p>Configure this URL in your Stripe Dashboard → Developers → Webhooks. Subscribe to <code class="rounded bg-slate-100 px-1 text-xs dark:bg-slate-800">checkout.session.completed</code>.</p>
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-medium text-slate-800 dark:text-slate-200">Webhook URL</p>
                            <button
                                type="button"
                                class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                @click="copyText('webhook', webhookUrl)"
                            >
                                {{ copied === 'webhook' ? 'Copied' : 'Copy' }}
                            </button>
                        </div>
                        <code class="mt-1 block break-all rounded-lg bg-slate-100 p-3 text-xs dark:bg-slate-800">{{ webhookUrl }}</code>
                    </div>
                </div>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
