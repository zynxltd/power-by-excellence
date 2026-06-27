<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    notifications: Object,
    unreadCount: Number,
    isSuperAdmin: Boolean,
});

const severityClass = (severity) => ({
    info: 'border-indigo-200 bg-indigo-50/60 dark:border-indigo-500/30 dark:bg-indigo-950/20',
    warning: 'border-amber-200 bg-amber-50/60 dark:border-amber-500/30 dark:bg-amber-950/20',
    critical: 'border-rose-200 bg-rose-50/60 dark:border-rose-500/30 dark:bg-rose-950/20',
}[severity] ?? 'border-slate-200 bg-slate-50/60 dark:border-slate-700 dark:bg-slate-800/40');

const severityBadge = (severity) => ({
    info: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
    warning: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    critical: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
}[severity] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300');

const markAllRead = () => {
    router.post(route('notifications.read-all'), {}, {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['notifications', 'unreadCount'], preserveScroll: true }),
    });
};

const openNotification = (notification) => {
    router.post(route('notifications.read', notification.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['notifications', 'unreadCount'], preserveScroll: true });
            if (notification.href) {
                router.visit(notification.href);
            }
        },
    });
};
</script>

<template>
    <Head title="Notifications" />
    <AuthenticatedLayout>
        <PageHeader
            title="Notifications"
            description="Full message history for your platform. Use this page when bell previews truncate longer updates."
        >
            <template #actions>
                <AppButton v-if="unreadCount > 0" variant="secondary" @click="markAllRead">
                    Mark all read ({{ unreadCount }})
                </AppButton>
                <AppButton v-if="isSuperAdmin" :href="route('notifications.admin.index')" variant="secondary">
                    Manage broadcasts
                </AppButton>
            </template>
        </PageHeader>

        <Panel :padding="false">
            <div v-if="!notifications?.data?.length" class="px-6 py-12 text-center text-sm text-slate-500">
                No notifications yet.
            </div>

            <div v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                <div
                    v-for="notification in notifications.data"
                    :key="notification.id"
                    :class="[
                        'px-6 py-5',
                        !notification.is_read ? 'bg-indigo-50/30 dark:bg-indigo-950/10' : '',
                    ]"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-base font-semibold text-slate-900 dark:text-white">
                                    {{ notification.title }}
                                </h2>
                                <span :class="['rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide', severityBadge(notification.severity)]">
                                    {{ notification.severity }}
                                </span>
                                <span v-if="!notification.is_read" class="rounded-full bg-indigo-600 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                                    Unread
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                <FormattedDate :value="notification.created_at" />
                                <span v-if="notification.account"> · {{ notification.account.name }}</span>
                                <span v-if="notification.created_by"> · {{ notification.created_by }}</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <AppButton
                                v-if="notification.href"
                                variant="secondary"
                                @click="openNotification(notification)"
                            >
                                Open
                            </AppButton>
                            <AppButton
                                v-else-if="!notification.is_read"
                                variant="secondary"
                                @click="router.post(route('notifications.read', notification.id), {}, { preserveScroll: true, onSuccess: () => router.reload({ only: ['notifications', 'unreadCount'], preserveScroll: true }) })"
                            >
                                Mark read
                            </AppButton>
                        </div>
                    </div>

                    <div
                        v-if="notification.body"
                        :class="['mt-4 rounded-xl border px-4 py-3 text-sm leading-relaxed text-slate-700 dark:text-slate-200', severityClass(notification.severity)]"
                    >
                        {{ notification.body }}
                    </div>
                </div>
            </div>

            <Pagination v-if="notifications.links?.length > 3" :links="notifications.links" class="border-t border-slate-100 dark:border-slate-800" />
        </Panel>
    </AuthenticatedLayout>
</template>
