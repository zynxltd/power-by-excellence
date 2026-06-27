<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    email: String,
});

const mode = ref('totp');

const totpForm = useForm({ code: '' });
const recoveryForm = useForm({ recovery_code: '' });

const submitTotp = () => {
    totpForm.post(route('two-factor.verify'), {
        preserveScroll: true,
        onFinish: () => totpForm.reset('code'),
    });
};

const submitRecovery = () => {
    recoveryForm.post(route('two-factor.recovery'), {
        preserveScroll: true,
        onFinish: () => recoveryForm.reset('recovery_code'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Two-factor authentication" />

        <template #header>
            <h2>Two-factor authentication</h2>
            <p v-if="email">Confirm your identity for <strong>{{ email }}</strong></p>
        </template>

        <div class="mb-6 flex rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-800/50">
            <button
                type="button"
                :class="[
                    'flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition',
                    mode === 'totp' ? 'bg-white text-slate-900 shadow dark:bg-slate-900 dark:text-white' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300',
                ]"
                @click="mode = 'totp'"
            >
                Authenticator code
            </button>
            <button
                type="button"
                :class="[
                    'flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition',
                    mode === 'recovery' ? 'bg-white text-slate-900 shadow dark:bg-slate-900 dark:text-white' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300',
                ]"
                @click="mode = 'recovery'"
            >
                Recovery code
            </button>
        </div>

        <form v-if="mode === 'totp'" class="space-y-5" @submit.prevent="submitTotp">
            <div>
                <label for="code" class="mb-1.5 block text-sm font-semibold text-slate-700">6-digit code</label>
                <input
                    id="code"
                    v-model="totpForm.code"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="6"
                    autocomplete="one-time-code"
                    required
                    autofocus
                    placeholder="000000"
                    class="form-input w-full text-center font-mono text-lg tracking-[0.35em]"
                />
                <InputError class="mt-2" :message="totpForm.errors.code" />
            </div>
            <PrimaryButton class="w-full justify-center" :disabled="totpForm.processing">
                {{ totpForm.processing ? 'Verifying…' : 'Continue' }}
            </PrimaryButton>
        </form>

        <form v-else class="space-y-5" @submit.prevent="submitRecovery">
            <div>
                <label for="recovery_code" class="mb-1.5 block text-sm font-semibold text-slate-700">Recovery code</label>
                <input
                    id="recovery_code"
                    v-model="recoveryForm.recovery_code"
                    type="text"
                    required
                    autofocus
                    placeholder="XXXX-XXXX"
                    class="form-input w-full font-mono uppercase"
                />
                <p class="mt-1 text-xs text-slate-500">Each recovery code can only be used once.</p>
                <InputError class="mt-2" :message="recoveryForm.errors.recovery_code" />
            </div>
            <PrimaryButton class="w-full justify-center" :disabled="recoveryForm.processing">
                {{ recoveryForm.processing ? 'Verifying…' : 'Continue with recovery code' }}
            </PrimaryButton>
        </form>
    </GuestLayout>
</template>
