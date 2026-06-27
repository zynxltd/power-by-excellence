<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    token: String,
    recipient: String,
    confirmed: Boolean,
});

const form = useForm({});
const confirm = () => form.post(route('messaging.unsubscribe.confirm', props.token));
</script>

<template>
    <Head title="Unsubscribe" />
    <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4 dark:bg-slate-950">
        <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <template v-if="confirmed">
                <h1 class="text-xl font-semibold text-slate-900 dark:text-white">You're unsubscribed</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    <span v-if="recipient">{{ recipient }}</span> will no longer receive marketing messages from this sender.
                </p>
            </template>
            <template v-else>
                <h1 class="text-xl font-semibold text-slate-900 dark:text-white">Unsubscribe</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Stop marketing emails<span v-if="recipient"> to {{ recipient }}</span>.
                </p>
                <button
                    type="button"
                    class="mt-6 w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:bg-white dark:text-slate-900"
                    :disabled="form.processing"
                    @click="confirm"
                >
                    Confirm unsubscribe
                </button>
            </template>
            <p class="mt-6 text-center text-xs text-slate-400">
                <Link href="/" class="hover:underline">Return home</Link>
            </p>
        </div>
    </div>
</template>
