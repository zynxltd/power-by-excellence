<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const inputClass =
    'block w-full rounded-xl border border-slate-200 bg-slate-50 py-3 px-4 text-base text-slate-900 placeholder-slate-400 transition focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20';
</script>

<template>
    <GuestLayout>
        <Head title="Register - PowerByExcellence" />

        <template #header>
            <h2>Create your account</h2>
            <p>Enter your details to get started.</p>
        </template>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label for="name" class="mb-1.5 block text-sm font-semibold text-slate-700">Name</label>
                <input id="name" v-model="form.name" type="text" required autofocus autocomplete="name" :class="inputClass" />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700">Email address</label>
                <input id="email" v-model="form.email" type="email" required autocomplete="username" :class="inputClass" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">Password</label>
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

            <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
                <Link :href="route('login')" class="text-center text-sm font-medium text-indigo-600 hover:text-indigo-500 sm:text-left">
                    Already registered? Sign in
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-violet-600 via-indigo-600 to-indigo-700 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:shadow-indigo-500/40 disabled:opacity-60 sm:w-auto sm:min-w-[10rem]"
                >
                    {{ form.processing ? 'Creating…' : 'Register' }}
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
