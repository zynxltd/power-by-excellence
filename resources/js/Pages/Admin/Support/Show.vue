<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ ticket: Object });

const replyForm = useForm({ body: '' });
const statusForm = useForm({ status: props.ticket.status });

const submitReply = () => {
    replyForm.post(route('support.admin.reply', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => replyForm.reset(),
    });
};

const updateStatus = () => {
    statusForm.patch(route('support.admin.status', props.ticket.id), {
        preserveScroll: true,
    });
};

const statusClass = (status) => ({
    open: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    resolved: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
    closed: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
}[status] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300');
</script>

<template>
    <Head :title="`Ticket: ${ticket.subject}`" />
    <AuthenticatedLayout>
        <PageHeader :title="ticket.subject" description="Admin support ticket">
            <template #subtitle>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium capitalize', statusClass(ticket.status)]">
                        {{ ticket.status }}
                    </span>
                    <span>Priority: {{ ticket.priority }}</span>
                    <span>Role: {{ ticket.portal_role }}</span>
                </div>
            </template>
            <template #actions>
                <AppButton variant="secondary" :href="route('support.admin.index')">Back to queue</AppButton>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <Panel title="Conversation" :padding="false">
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        <div
                            v-for="message in ticket.messages"
                            :key="message.id"
                            :class="[
                                'px-4 py-5 sm:px-6',
                                message.is_staff ? 'bg-indigo-50/50 dark:bg-indigo-950/20' : '',
                            ]"
                        >
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-slate-900 dark:text-white">
                                        {{ message.user?.name ?? 'Unknown' }}
                                    </span>
                                    <span
                                        v-if="message.is_staff"
                                        class="rounded-md bg-indigo-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300"
                                    >
                                        Staff
                                    </span>
                                </div>
                                <FormattedDate :value="message.created_at" class="text-xs text-slate-500" />
                            </div>
                            <p class="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-slate-700 dark:text-slate-300">
                                {{ message.body }}
                            </p>
                        </div>
                    </div>
                </Panel>

                <Panel title="Staff Reply">
                    <form class="space-y-4" @submit.prevent="submitReply">
                        <textarea
                            v-model="replyForm.body"
                            rows="5"
                            required
                            class="form-input w-full"
                            placeholder="Type your reply to the customer..."
                        />
                        <InputError :message="replyForm.errors.body" />
                        <AppButton type="submit" :disabled="replyForm.processing">
                            {{ replyForm.processing ? 'Sending...' : 'Send Reply' }}
                        </AppButton>
                    </form>
                </Panel>
            </div>

            <aside class="space-y-6">
                <Panel title="Requester">
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Platform</dt>
                            <dd class="mt-0.5 font-medium text-slate-900 dark:text-white">
                                {{ ticket.account?.brand_name || ticket.account?.name || '—' }}
                            </dd>
                            <dd v-if="ticket.account?.slug" class="text-xs text-slate-500">{{ ticket.account.slug }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Name</dt>
                            <dd class="mt-0.5 font-medium text-slate-900 dark:text-white">{{ ticket.user?.name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Email</dt>
                            <dd class="mt-0.5 text-slate-700 dark:text-slate-300">{{ ticket.user?.email ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Opened</dt>
                            <dd class="mt-0.5"><FormattedDate :value="ticket.created_at" /></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Last updated</dt>
                            <dd class="mt-0.5"><FormattedDate :value="ticket.updated_at" /></dd>
                        </div>
                    </dl>
                </Panel>

                <Panel title="Update Status">
                    <form class="space-y-4" @submit.prevent="updateStatus">
                        <select v-model="statusForm.status" class="form-select w-full">
                            <option value="open">Open</option>
                            <option value="pending">Pending</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                        <InputError :message="statusForm.errors.status" />
                        <AppButton type="submit" variant="secondary" :disabled="statusForm.processing">
                            {{ statusForm.processing ? 'Saving...' : 'Update Status' }}
                        </AppButton>
                    </form>
                </Panel>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>
