<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const page = usePage();
const open = ref(false);
const items = ref([]);
const unread = computed(() => page.props.notifications?.unread_count ?? 0);

const severityDot = (severity) => ({
    info: 'bg-indigo-500',
    warning: 'bg-amber-500',
    critical: 'bg-rose-500',
}[severity] ?? 'bg-slate-400');

const fetchNotifications = async () => {
    try {
        const res = await fetch(route('notifications.inbox'), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (res.ok) {
            const data = await res.json();
            items.value = data.notifications ?? [];
        }
    } catch {
        // ignore
    }
};

const toggle = () => {
    open.value = !open.value;
    if (open.value) {
        fetchNotifications();
    }
};

const markAll = () => {
    router.post(route('notifications.read-all'), {}, {
        preserveScroll: true,
        onSuccess: () => {
            items.value = items.value.map((n) => ({ ...n, is_read: true }));
            fetchNotifications();
        },
    });
};

const markOne = (id, href = null) => {
    router.post(route('notifications.read', id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            fetchNotifications();
            if (href) {
                open.value = false;
                router.visit(href);
            }
        },
    });
};

const onDocClick = (e) => {
    if (!e.target.closest('[data-notification-root]')) {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('click', onDocClick));
onUnmounted(() => document.removeEventListener('click', onDocClick));
</script>

<template>
    <div class="relative" data-notification-root>
        <button
            type="button"
            class="relative flex h-10 w-10 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-800 hover:text-white"
            aria-label="Notifications"
            @click.stop="toggle"
        >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span
                v-if="unread > 0"
                class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white"
            >
                {{ unread > 9 ? '9+' : unread }}
            </span>
        </button>

        <div
            v-if="open"
            class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900 sm:w-96"
        >
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <p class="text-sm font-semibold text-slate-900 dark:text-white">Notifications</p>
                <div class="flex items-center gap-2">
                    <button v-if="unread > 0" type="button" class="text-xs text-indigo-600 hover:underline" @click="markAll">Mark all read</button>
                    <Link
                        v-if="page.props.auth.isSuperAdmin"
                        :href="route('notifications.admin.index')"
                        class="text-xs text-slate-500 hover:text-indigo-600"
                        @click="open = false"
                    >
                        Manage
                    </Link>
                </div>
            </div>
            <div class="max-h-80 overflow-y-auto">
                <p v-if="!items.length" class="px-4 py-8 text-center text-sm text-slate-500">No notifications yet.</p>
                <button
                    v-for="n in items"
                    :key="n.id"
                    type="button"
                    :class="['w-full border-b border-slate-50 px-4 py-3 text-left last:border-0 dark:border-slate-800/80', !n.is_read ? 'bg-indigo-50/50 dark:bg-indigo-950/20' : 'hover:bg-slate-50 dark:hover:bg-slate-800/50']"
                    @click="markOne(n.id, n.href)"
                >
                    <div class="flex items-start gap-2">
                        <span :class="['mt-1.5 h-2 w-2 shrink-0 rounded-full', severityDot(n.severity)]" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ n.title }}</p>
                            <p v-if="n.body" class="mt-0.5 line-clamp-2 text-xs text-slate-600 dark:text-slate-400">{{ n.body }}</p>
                            <p v-if="n.account" class="mt-1 text-[10px] text-slate-500">{{ n.account.name }}</p>
                        </div>
                    </div>
                </button>
            </div>
        </div>
    </div>
</template>
