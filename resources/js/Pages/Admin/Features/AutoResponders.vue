<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

defineProps({
    responders: Array,
    campaigns: Array,
});

const form = useForm({
    name: '',
    campaign_id: '',
    channel: 'email',
    trigger_event: 'on_lead_received',
    status: 'active',
    config: {
        subject: '',
        body: '',
        to_field: 'email',
        provider: '',
    },
});

const submit = () => {
    form.post(route('features.auto-responders.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const destroy = (id) => {
    if (confirm('Remove this auto responder?')) {
        router.delete(route('features.auto-responders.destroy', id));
    }
};
</script>

<template>
    <Head title="Auto Responders" />
    <AuthenticatedLayout>
        <PageHeader
            title="Auto Responders"
            description="Send automated email or SMS when leads are received or sold."
        >
            <template #actions>
                <Link :href="route('features.index')" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300">
                    ← All features
                </Link>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-2">
            <Panel title="Create auto responder">
                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <InputLabel for="name" value="Name" />
                        <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="campaign_id" value="Campaign (optional)" />
                        <select id="campaign_id" v-model="form.campaign_id" class="form-select mt-1 w-full">
                            <option value="">All campaigns</option>
                            <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <InputError :message="form.errors.campaign_id" class="mt-1" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="channel" value="Channel" />
                            <select id="channel" v-model="form.channel" class="form-select mt-1 w-full">
                                <option value="email">Email</option>
                                <option value="sms">SMS</option>
                            </select>
                            <InputError :message="form.errors.channel" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="provider" value="Provider (optional)" />
                            <select id="provider" v-model="form.config.provider" class="form-select mt-1 w-full">
                                <option value="">Default</option>
                                <template v-if="form.channel === 'email'">
                                    <option value="smtp">SMTP</option>
                                    <option value="sendgrid">SendGrid</option>
                                    <option value="mailgun">Mailgun</option>
                                    <option value="postmark">Postmark</option>
                                    <option value="resend">Resend</option>
                                </template>
                                <template v-else>
                                    <option value="log">Log (dev)</option>
                                    <option value="twilio">Twilio</option>
                                    <option value="vonage">Vonage</option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="trigger_event" value="Trigger" />
                            <select id="trigger_event" v-model="form.trigger_event" class="form-select mt-1 w-full">
                                <option value="on_lead_received">On lead received</option>
                                <option value="on_lead_sold">On lead sold</option>
                            </select>
                            <InputError :message="form.errors.trigger_event" class="mt-1" />
                        </div>
                    </div>

                    <div v-if="form.channel === 'email'">
                        <InputLabel for="subject" value="Subject" />
                        <TextInput id="subject" v-model="form.config.subject" class="mt-1 block w-full" />
                        <InputError :message="form.errors['config.subject']" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="body" :value="form.channel === 'sms' ? 'Message' : 'Body'" />
                        <textarea
                            id="body"
                            v-model="form.config.body"
                            rows="5"
                            class="form-input mt-1 w-full"
                            placeholder="Use {firstname}, {email}, etc."
                        />
                        <InputError :message="form.errors['config.body']" class="mt-1" />
                    </div>

                    <AppButton type="submit" :disabled="form.processing">Create responder</AppButton>
                </form>
            </Panel>

            <Panel title="Active responders" :padding="false">
                <DataTable :empty="!responders?.length" empty-message="No auto responders configured yet.">
                    <template #head>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Channel</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Trigger</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500" />
                    </template>
                    <tr v-for="r in responders" :key="r.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ r.name }}</td>
                        <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ r.campaign?.name ?? 'All' }}</td>
                        <td class="px-6 py-4">
                            <span
                                :class="[
                                    'rounded-full px-2 py-0.5 text-xs font-medium uppercase',
                                    r.channel === 'email'
                                        ? 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300'
                                        : 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
                                ]"
                            >
                                {{ r.channel }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs capitalize text-slate-500">{{ r.trigger_event?.replace(/_/g, ' ') }}</td>
                        <td class="px-6 py-4"><StatusBadge :status="r.status" /></td>
                        <td class="px-6 py-4 text-right">
                            <button
                                type="button"
                                class="text-xs font-medium text-rose-600 hover:text-rose-500 dark:text-rose-400"
                                @click="destroy(r.id)"
                            >
                                Remove
                            </button>
                        </td>
                    </tr>
                </DataTable>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
