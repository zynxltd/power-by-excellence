<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};

const inputClass =
    'block w-full rounded-xl border border-slate-200 bg-slate-50 py-3 px-4 text-base text-slate-900 transition focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20';
</script>

<template>
    <GuestLayout>
        <Head title="Confirm Password - PowerByExcellence" />

        <template #header>
            <h2>Confirm password</h2>
            <p>This is a secure area. Please confirm your password before continuing.</p>
        </template>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">Password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    autofocus
                    :class="inputClass"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-violet-600 via-indigo-600 to-indigo-700 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition disabled:opacity-60"
            >
                {{ form.processing ? 'Confirming…' : 'Confirm' }}
            </button>
        </form>
    </GuestLayout>
</template>
