<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    twoFactorEnabled: Boolean,
    recoveryCodes: Array,
    twoFactorQr: { type: String, default: null },
    twoFactorSecret: { type: String, default: null },
});

const page = usePage();

const qrUrl = computed(() => props.twoFactorQr ?? page.props.flash?.two_factor_qr ?? null);
const pendingSecret = computed(() => props.twoFactorSecret ?? page.props.flash?.two_factor_secret ?? null);
const awaitingConfirm = computed(() => Boolean(qrUrl.value || pendingSecret.value) && !props.twoFactorEnabled);

const enableForm = useForm({ password: '' });
const confirmForm = useForm({ password: '', code: '' });
const disableForm = useForm({ password: '' });
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-slate-900 dark:text-white">Two-factor authentication</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Add an extra layer of security. Recovery codes are shown once when you enable 2FA.
            </p>
        </header>

        <div v-if="recoveryCodes?.length" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/30">
            <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Save these recovery codes - they won't be shown again:</p>
            <ul class="mt-2 grid grid-cols-2 gap-1 font-mono text-sm">
                <li v-for="code in recoveryCodes" :key="code">{{ code }}</li>
            </ul>
        </div>

        <div v-if="twoFactorEnabled" class="mt-6">
            <p class="text-sm font-medium text-emerald-600">2FA is enabled on your account.</p>
            <form class="mt-4 max-w-sm space-y-4" @submit.prevent="disableForm.post(route('profile.two-factor.disable'))">
                <div>
                    <InputLabel for="disable_password" value="Password to disable" />
                    <TextInput id="disable_password" v-model="disableForm.password" type="password" class="mt-1 block w-full" required />
                    <InputError :message="disableForm.errors.password" class="mt-1" />
                </div>
                <AppButton type="submit" variant="danger" :disabled="disableForm.processing">Disable 2FA</AppButton>
            </form>
        </div>

        <div v-else-if="awaitingConfirm" class="mt-6 max-w-sm space-y-4">
            <p class="text-sm text-slate-600 dark:text-slate-400">Scan this QR code with your authenticator app, then enter a code to confirm.</p>
            <div v-if="qrUrl" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                <img :src="qrUrl" alt="Two-factor QR code" class="mx-auto h-48 w-48" />
            </div>
            <p v-if="pendingSecret" class="break-all font-mono text-xs text-slate-500">Manual key: {{ pendingSecret }}</p>

            <form class="space-y-4" @submit.prevent="confirmForm.post(route('profile.two-factor.confirm'))">
                <div>
                    <InputLabel for="confirm_password" value="Password" />
                    <TextInput id="confirm_password" v-model="confirmForm.password" type="password" class="mt-1 block w-full" required />
                    <InputError :message="confirmForm.errors.password" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="confirm_code" value="Authentication code" />
                    <TextInput id="confirm_code" v-model="confirmForm.code" type="text" inputmode="numeric" maxlength="6" class="mt-1 block w-full font-mono tracking-widest" required />
                    <InputError :message="confirmForm.errors.code" class="mt-1" />
                </div>
                <AppButton type="submit" :disabled="confirmForm.processing">Confirm and enable</AppButton>
            </form>
        </div>

        <form v-else class="mt-6 max-w-sm space-y-4" @submit.prevent="enableForm.post(route('profile.two-factor.enable'))">
            <div>
                <InputLabel for="enable_password" value="Password to enable" />
                <TextInput id="enable_password" v-model="enableForm.password" type="password" class="mt-1 block w-full" required />
                <InputError :message="enableForm.errors.password" class="mt-1" />
            </div>
            <AppButton type="submit" :disabled="enableForm.processing">Enable 2FA</AppButton>
        </form>
    </section>
</template>
