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

const submitReply = () => {
    replyForm.post(route('support.reply', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => replyForm.reset(),
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
    <Head :title="ticket.subject" />
    <AuthenticatedLayout>
        <PageHeader :title="ticket.subject" description="Support ticket conversation">
            <template #subtitle>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium capitalize', statusClass(ticket.status)]">
                        {{ ticket.status }}
                    </span>
                    <span class="text-xs text-slate-500">Priority: {{ ticket.priority }}</span>
                    <span class="text-xs text-slate-500">Opened <FormattedDate :value="ticket.created_at" /></span>
                </div>
            </template>
            <template #actions>
                <AppButton variant="secondary" :href="route('support.index')">All tickets</AppButton>
            </template>
        </PageHeader>

        <div class="space-y-6">
            <Panel title="Conversation" :padding="false">
                <div v-if="!ticket.messages?.length" class="px-6 py-8 text-center text-sm text-slate-500">
                    No messages yet.
                </div>
                <div v-else class="divide-y divide-slate-100 dark:divide-slate-800">
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

            <Panel v-if="ticket.status !== 'closed'" title="Reply">
                <form class="space-y-4" @submit.prevent="submitReply">
                    <textarea
                        v-model="replyForm.body"
                        rows="5"
                        required
                        class="form-input w-full"
                        placeholder="Type your reply..."
                    />
                    <InputError :message="replyForm.errors.body" />
                    <AppButton type="submit" :disabled="replyForm.processing">
                        {{ replyForm.processing ? 'Sending...' : 'Send Reply' }}
                    </AppButton>
                </form>
            </Panel>

            <Panel v-else title="Ticket closed">
                <p class="text-sm text-slate-500">This ticket has been closed. Open a new ticket if you need further assistance.</p>
                <AppButton class="mt-4" :href="route('support.create')">New Ticket</AppButton>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
