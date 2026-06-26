<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    webhooks: Array,
    buyers: Array,
    eventOptions: Array,
});

const form = useForm({
    name: '',
    url: '',
    events: ['lead.sold'],
    buyer_id: '',
    is_active: true,
});

const submit = () => form.post(route('webhooks.store'), {
    onSuccess: () => form.reset('name', 'url', 'buyer_id'),
});

const destroy = (webhook) => {
    if (webhook.config?.synced_from === 'buyer_sold_webhook') {
        return;
    }
    if (confirm('Delete this webhook?')) {
        router.delete(route('webhooks.destroy', webhook.id));
    }
};

const toggleEvent = (event) => {
    const idx = form.events.indexOf(event);
    if (idx >= 0) form.events.splice(idx, 1);
    else form.events.push(event);
};

const scopeLabel = (webhook) => {
    if (webhook.buyer) {
        return `Buyer: ${webhook.buyer.name}`;
    }
    return 'Account-wide';
};

const isManaged = (webhook) => webhook.config?.synced_from === 'buyer_sold_webhook';
</script>

<template>
    <Head title="Webhooks" />
    <AuthenticatedLayout>
        <PageHeader
            title="Webhooks"
            description="Outbound JSON notifications to your CRM or buyer systems. Account-wide hooks fire for every matching event; buyer-scoped hooks only fire when that buyer wins the lead."
        />

        <Panel class="mb-6" title="How this differs from postbacks">
            <ul class="list-inside list-disc space-y-1 text-sm text-slate-600 dark:text-slate-400">
                <li><strong class="font-medium text-slate-800 dark:text-slate-200">Webhooks</strong> — JSON POST to your endpoints (CRM, data warehouse, buyer integrations).</li>
                <li><strong class="font-medium text-slate-800 dark:text-slate-200">Postbacks</strong> — tracking pixels / affiliate URLs for <Link :href="route('postbacks.index')" class="text-indigo-600 hover:underline">suppliers</Link> (GET query strings with <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">[lead_uuid]</code> tags).</li>
                <li>Buyers receive leads via <strong class="font-medium text-slate-800 dark:text-slate-200">deliveries</strong> (ping/post). Optional sold webhook URL can also be set on each <Link :href="route('buyers.index')" class="text-indigo-600 hover:underline">buyer</Link>.</li>
            </ul>
        </Panel>

        <div class="space-y-6">
            <Panel title="Add Webhook">
                <form @submit.prevent="submit" class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <InputLabel value="Name" />
                        <TextInput v-model="form.name" class="mt-1 block w-full" placeholder="CRM lead sold feed" required />
                    </div>
                    <div class="md:col-span-2">
                        <InputLabel value="Endpoint URL" />
                        <TextInput v-model="form.url" type="url" class="mt-1 block w-full font-mono text-sm" placeholder="https://hooks.example.com/leads" required />
                        <p class="mt-1 text-xs text-slate-500">Receives JSON POST with event, lead_uuid, buyer_id, revenue, and field_data.</p>
                    </div>
                    <div>
                        <InputLabel value="Scope — buyer (optional)" />
                        <select v-model="form.buyer_id" class="form-select mt-1 w-full">
                            <option value="">All buyers (account-wide)</option>
                            <option v-for="b in buyers" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Events" />
                        <div class="mt-2 flex flex-wrap gap-2">
                            <label
                                v-for="ev in eventOptions"
                                :key="ev"
                                class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-sm dark:border-slate-700"
                            >
                                <input type="checkbox" :checked="form.events.includes(ev)" @change="toggleEvent(ev)" />
                                {{ ev }}
                            </label>
                        </div>
                    </div>
                    <div class="flex items-end md:col-span-2">
                        <PrimaryButton :disabled="form.processing || !form.events.length">Add Webhook</PrimaryButton>
                    </div>
                </form>
            </Panel>

            <Panel title="Configured Webhooks">
                <div v-if="!webhooks?.length" class="py-8 text-center text-sm text-slate-500">No webhooks configured yet.</div>
                <div v-for="w in webhooks" :key="w.id" class="flex flex-col gap-1.5 border-b border-slate-100 py-3 last:border-0 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-slate-900 dark:text-white">{{ w.name }}</p>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ scopeLabel(w) }}</span>
                            <span v-if="isManaged(w)" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Buyer form</span>
                        </div>
                        <p class="mt-1 truncate font-mono text-xs text-slate-500">{{ w.url }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ (w.events || []).join(', ') }}</p>
                    </div>
                    <AppButton
                        v-if="!isManaged(w)"
                        variant="danger"
                        class="shrink-0"
                        @click="destroy(w)"
                    >
                        Delete
                    </AppButton>
                    <Link
                        v-else-if="w.buyer"
                        :href="route('buyers.edit', w.buyer.id)"
                        class="shrink-0 text-sm font-medium text-indigo-600 hover:underline"
                    >
                        Edit buyer
                    </Link>
                </div>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
