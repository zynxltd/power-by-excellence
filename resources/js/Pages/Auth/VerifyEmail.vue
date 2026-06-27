<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Verify Email - PowerByExcellence" />

        <template #header>
            <h2>Verify your email</h2>
            <p>
                Thanks for signing up. Click the link in the email we sent you, or request a new one below.
            </p>
        </template>

        <div
            v-if="verificationLinkSent"
            class="mb-6 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
        >
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            A new verification link has been sent to your email address.
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <button
                type="submit"
                :disabled="form.processing"
                class="flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-violet-600 via-indigo-600 to-indigo-700 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition disabled:opacity-60 sm:w-auto"
            >
                {{ form.processing ? 'Sending…' : 'Resend verification email' }}
            </button>

            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="text-center text-sm font-medium text-slate-600 underline hover:text-slate-900 sm:text-left"
            >
                Log out
            </Link>
        </form>
    </GuestLayout>
</template>
