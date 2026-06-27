<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    phone: {
        type: String,
        default: '',
    },
    status: {
        type: String,
        default: '',
    },
});

const step = ref(props.phone && props.status === 'verification-code-sent' ? 'code' : 'phone');

const phoneForm = useForm({
    phone: props.phone || '',
});

const codeForm = useForm({
    code: '',
});

const codeSent = computed(() => props.status === 'verification-code-sent');

const sendCode = () => {
    phoneForm.post(route('verification.phone.send'), {
        preserveScroll: true,
        onSuccess: () => {
            step.value = 'code';
        },
    });
};

const verifyCode = () => {
    codeForm.post(route('verification.phone.verify'), {
        preserveScroll: true,
    });
};

const inputClass =
    'block w-full rounded-xl border border-slate-200 bg-slate-50 py-3 px-4 text-base text-slate-900 placeholder-slate-400 transition focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20';
</script>

<template>
    <GuestLayout>
        <Head title="Verify Phone - PowerByExcellence" />

        <template #header>
            <h2>Verify your phone number</h2>
            <p>We send a one-time code by SMS so we can confirm your contact details.</p>
        </template>

        <div
            v-if="codeSent"
            class="mb-6 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
        >
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Verification code sent. Enter it below to continue.
        </div>

        <div v-if="step === 'phone'" class="space-y-5">
            <div>
                <label for="phone" class="mb-1.5 block text-sm font-semibold text-slate-700">Mobile number</label>
                <input
                    id="phone"
                    v-model="phoneForm.phone"
                    type="tel"
                    required
                    autocomplete="tel"
                    placeholder="+44 7700 900123"
                    :class="inputClass"
                />
                <p class="mt-2 text-xs text-slate-500">Include country code. UK numbers can start with 0.</p>
                <InputError class="mt-2" :message="phoneForm.errors.phone" />
            </div>

            <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="text-center text-sm font-medium text-slate-600 underline hover:text-slate-900 sm:text-left"
                >
                    Log out
                </Link>
                <button
                    type="button"
                    :disabled="phoneForm.processing"
                    class="flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-violet-600 via-indigo-600 to-indigo-700 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition disabled:opacity-60 sm:w-auto"
                    @click="sendCode"
                >
                    {{ phoneForm.processing ? 'Sending…' : 'Send verification code' }}
                </button>
            </div>
        </div>

        <div v-else class="space-y-5">
            <div>
                <label for="code" class="mb-1.5 block text-sm font-semibold text-slate-700">6-digit code</label>
                <input
                    id="code"
                    v-model="codeForm.code"
                    type="text"
                    inputmode="numeric"
                    maxlength="6"
                    required
                    autocomplete="one-time-code"
                    placeholder="123456"
                    :class="inputClass"
                />
                <InputError class="mt-2" :message="codeForm.errors.code" />
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <button
                    type="button"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                    @click="step = 'phone'"
                >
                    Use a different number
                </button>
                <button
                    type="button"
                    :disabled="codeForm.processing"
                    class="flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-violet-600 via-indigo-600 to-indigo-700 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition disabled:opacity-60 sm:w-auto"
                    @click="verifyCode"
                >
                    {{ codeForm.processing ? 'Verifying…' : 'Verify phone' }}
                </button>
            </div>
        </div>
    </GuestLayout>
</template>
