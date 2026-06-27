<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const inputClass =
    'block w-full rounded-xl border border-slate-200 bg-slate-50 py-3 px-4 text-base text-slate-900 placeholder-slate-400 transition focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20';
</script>

<template>
    <GuestLayout>
        <Head title="Reset Password - PowerByExcellence" />

        <template #header>
            <h2>Set new password</h2>
            <p>Choose a strong password for your account.</p>
        </template>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700">Email address</label>
                <input id="email" v-model="form.email" type="email" required autofocus autocomplete="username" :class="inputClass" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">New password</label>
                <input
                    id="password"
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    required
                    autocomplete="new-password"
                    :class="inputClass"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-slate-700">Confirm password</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    :type="showPassword ? 'text' : 'password'"
                    required
                    autocomplete="new-password"
                    :class="inputClass"
                />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <label class="flex cursor-pointer items-center gap-2.5">
                <input v-model="showPassword" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                <span class="text-sm text-slate-600">Show passwords</span>
            </label>

            <button
                type="submit"
                :disabled="form.processing"
                class="flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-violet-600 via-indigo-600 to-indigo-700 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:shadow-indigo-500/40 disabled:opacity-60"
            >
                {{ form.processing ? 'Resetting...' : 'Reset password' }}
            </button>
        </form>

        <p class="mt-8 text-center text-sm text-slate-500">
            <Link :href="route('login')" class="font-medium text-indigo-600 hover:text-indigo-500">
                &larr; Back to sign in
            </Link>
        </p>
    </GuestLayout>
</template>
