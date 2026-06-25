<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
    tenant: { type: Object, default: null },
    isCentralHost: { type: Boolean, default: true },
    centralLoginUrl: { type: String, default: null },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const primaryError = computed(() => form.errors.email || form.errors.password);

const isSuperAdminRejection = computed(() => {
    const msg = form.errors.email ?? '';
    return msg.toLowerCase().includes('super admin');
});

const submit = () => {
    form.post(route('login'), {
        preserveScroll: true,
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Sign In — PowerByExcellence" />

        <template #header>
            <h2 class="text-3xl font-bold tracking-tight text-slate-900">
                {{ tenant ? `Sign in — ${tenant.name}` : 'Welcome back' }}
            </h2>
            <p v-if="tenant" class="mt-2 text-slate-500">Partner platform portal — admins, buyers, and suppliers.</p>
            <p v-else-if="isCentralHost" class="mt-2 text-slate-500">Super admin sign-in for PowerByExcellence central operations.</p>
            <p v-else class="mt-2 text-slate-500">Sign in to access your admin or portal dashboard.</p>
        </template>

        <div
            v-if="status"
            class="mb-6 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
        >
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {{ status }}
        </div>

        <div
            v-if="form.hasErrors"
            class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
            role="alert"
        >
            <p class="font-semibold">Could not sign in</p>
            <p class="mt-1">{{ primaryError }}</p>
            <a
                v-if="isSuperAdminRejection && centralLoginUrl"
                :href="centralLoginUrl"
                class="mt-3 inline-flex font-medium text-indigo-700 underline hover:text-indigo-600"
            >
                Sign in at the central platform instead →
            </a>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700">Email address</label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                        <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="you@company.com"
                        :class="[
                            'block w-full rounded-xl border bg-slate-50 py-3 pl-11 pr-4 text-slate-900 placeholder-slate-400 transition focus:bg-white focus:outline-none focus:ring-2',
                            form.errors.email
                                ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-500/20'
                                : 'border-slate-200 focus:border-indigo-500 focus:ring-indigo-500/20',
                        ]"
                    />
                </div>
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">Password</label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                        <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        required
                        autocomplete="current-password"
                        placeholder="Enter your password"
                        :class="[
                            'block w-full rounded-xl border bg-slate-50 py-3 pl-11 pr-12 text-slate-900 placeholder-slate-400 transition focus:bg-white focus:outline-none focus:ring-2',
                            form.errors.password
                                ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-500/20'
                                : 'border-slate-200 focus:border-indigo-500 focus:ring-indigo-500/20',
                        ]"
                    />
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600"
                        @click="showPassword = !showPassword"
                    >
                        <svg v-if="!showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                    </button>
                </div>
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <label class="flex cursor-pointer items-center gap-2.5">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="text-sm text-slate-600">Remember me</span>
                </label>
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm font-medium text-indigo-600 transition hover:text-indigo-500"
                >
                    Forgot password?
                </Link>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="relative flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-violet-600 via-indigo-600 to-indigo-700 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:shadow-indigo-500/40 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <svg v-if="form.processing" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
                {{ form.processing ? 'Signing in...' : 'Sign in' }}
            </button>
        </form>

        <p class="mt-8 text-center text-xs text-slate-400">
            <template v-if="isCentralHost && !tenant">
                Central platform access — partner admins must sign in on their dedicated subdomain.
            </template>
            <template v-else>
                Access is by invitation only. Contact your platform administrator for credentials.
            </template>
        </p>
    </GuestLayout>
</template>
