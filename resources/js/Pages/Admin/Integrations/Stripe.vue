<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
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

const defaultSubscriptionPrices = () => (
    props.stripe?.subscription_prices?.length
        ? props.stripe.subscription_prices.map((row) => ({
            price_id: row.price_id ?? '',
            label: row.label ?? '',
            credit_amount: row.credit_amount ?? '',
        }))
        : [{ price_id: '', label: '', credit_amount: '' }]
);

const form = useForm({
    enabled: props.stripe?.enabled ?? false,
    allow_buyer_self_serve: props.stripe?.allow_buyer_self_serve ?? true,
    allow_subscriptions: props.stripe?.allow_subscriptions ?? false,
    min_topup: props.stripe?.min_topup ?? 1,
    preset_amounts: (props.stripe?.preset_amounts ?? [50, 100, 250, 500, 1000]).join(', '),
    subscription_prices: defaultSubscriptionPrices(),
    key: props.stripe?.key ?? '',
    secret: props.stripe?.secret ?? '',
    webhook_secret: props.stripe?.webhook_secret ?? '',
});

const addSubscriptionPrice = () => {
    form.subscription_prices.push({ price_id: '', label: '', credit_amount: '' });
};

const removeSubscriptionPrice = (index) => {
    form.subscription_prices.splice(index, 1);
    if (!form.subscription_prices.length) {
        addSubscriptionPrice();
    }
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        preset_amounts: String(data.preset_amounts)
            .split(',')
            .map((value) => parseFloat(value.trim()))
            .filter((value) => !Number.isNaN(value) && value >= 1),
        subscription_prices: data.subscription_prices
            .filter((row) => String(row.price_id).trim() !== '')
            .map((row) => ({
                price_id: String(row.price_id).trim(),
                label: String(row.label ?? '').trim(),
                credit_amount: row.credit_amount === '' || row.credit_amount == null
                    ? null
                    : parseFloat(row.credit_amount),
            })),
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
        <PageHeader title="Stripe checkout" description="Enable buyer self-serve credit top-ups and recurring subscriptions via Stripe Checkout.">
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

                    <label class="flex items-center gap-3">
                        <input v-model="form.allow_subscriptions" type="checkbox" class="rounded border-slate-300" />
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Allow recurring subscription plans in buyer portal</span>
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

                    <div class="space-y-3 rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Subscription price IDs</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Stripe Price IDs from your Dashboard. Credit amount is added to the buyer balance on each paid invoice.</p>
                            </div>
                            <button type="button" class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400" @click="addSubscriptionPrice">
                                Add plan
                            </button>
                        </div>

                        <div
                            v-for="(row, index) in form.subscription_prices"
                            :key="index"
                            class="grid gap-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-800/50 md:grid-cols-[1fr_1fr_8rem_auto]"
                        >
                            <div>
                                <InputLabel value="Price ID" />
                                <input v-model="row.price_id" type="text" class="form-input mt-1 w-full font-mono text-sm" placeholder="price_..." />
                            </div>
                            <div>
                                <InputLabel value="Label" />
                                <input v-model="row.label" type="text" class="form-input mt-1 w-full text-sm" placeholder="Monthly credit" />
                            </div>
                            <div>
                                <InputLabel value="Credit amount" />
                                <input v-model="row.credit_amount" type="number" min="0" step="0.01" class="form-input mt-1 w-full text-sm" placeholder="Auto" />
                            </div>
                            <div class="flex items-end">
                                <button type="button" class="text-xs font-medium text-rose-600 hover:underline dark:text-rose-400" @click="removeSubscriptionPrice(index)">
                                    Remove
                                </button>
                            </div>
                        </div>
                        <InputError :message="form.errors['subscription_prices']" />
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
                    <p>Configure this URL in your Stripe Dashboard → Developers → Webhooks. Subscribe to:</p>
                    <ul class="list-inside list-disc space-y-1 text-xs">
                        <li><code class="rounded bg-slate-100 px-1 dark:bg-slate-800">checkout.session.completed</code></li>
                        <li><code class="rounded bg-slate-100 px-1 dark:bg-slate-800">invoice.paid</code></li>
                        <li><code class="rounded bg-slate-100 px-1 dark:bg-slate-800">customer.subscription.updated</code></li>
                        <li><code class="rounded bg-slate-100 px-1 dark:bg-slate-800">customer.subscription.deleted</code></li>
                    </ul>
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
