<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AccountOverview from './Partials/AccountOverview.vue';
import ThemePreferences from './Partials/ThemePreferences.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import TwoFactorForm from './Partials/TwoFactorForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
    preferences: Object,
    accentOptions: Array,
    twoFactorEnabled: Boolean,
    recoveryCodes: Array,
});
</script>

<template>
    <Head title="Profile" />
    <AuthenticatedLayout>
        <PageHeader title="Profile" description="Manage your account, security, and platform access." />

        <div class="mx-auto max-w-3xl space-y-6">
            <Panel>
                <AccountOverview />
            </Panel>
            <Panel>
                <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" class="max-w-xl" />
            </Panel>
            <Panel>
                <ThemePreferences :preferences="preferences" :accent-options="accentOptions" />
            </Panel>
            <Panel id="password">
                <UpdatePasswordForm class="max-w-xl" />
            </Panel>
            <Panel>
                <TwoFactorForm :two-factor-enabled="twoFactorEnabled" :recovery-codes="recoveryCodes" />
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
