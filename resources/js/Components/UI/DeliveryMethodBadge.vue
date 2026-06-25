<script setup>
defineProps({
    method: { type: String, required: true },
});

const styles = {
    direct_post: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    ping_post: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
    store_lead: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    email: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    sms: 'bg-pink-100 text-pink-800 dark:bg-pink-900/40 dark:text-pink-300',
};

const labels = {
    direct_post: 'Direct API',
    ping_post: 'Ping Post',
    store_lead: 'Store Lead',
    email: 'Email',
    sms: 'SMS',
};
</script>

<template>
    <span
        :class="[
            'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold',
            styles[method] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
        ]"
    >
        {{ labels[method] ?? method?.replace(/_/g, ' ') }}
    </span>
</template>
