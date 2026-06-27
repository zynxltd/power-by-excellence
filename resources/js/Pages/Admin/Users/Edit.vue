<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { pushToast } from '@/Composables/useToast';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);
const isImpersonating = computed(() => Boolean(page.props.auth?.impersonator));

const props = defineProps({
    user: Object,
    buyers: Array,
    suppliers: Array,
    modules: Array,
});

const showAdvanced = ref(
    props.user.role === 'staff'
    || ['buyer_portal', 'supplier_portal'].includes(props.user.role),
);
const showPassword = ref(false);

const form = useForm({
    name: props.user.name ?? '',
    email: props.user.email ?? '',
    password: '',
    role: props.user.role ?? 'staff',
    buyer_id: props.user.buyer_id ?? '',
    supplier_id: props.user.supplier_id ?? '',
    allowed_modules: [...(props.user.allowed_modules ?? [])],
});

const roleOptions = [
    { value: 'account_admin', label: 'Account Admin', description: 'Full platform access' },
    { value: 'staff', label: 'Staff', description: 'Limited module access' },
    { value: 'buyer_portal', label: 'Buyer Portal', description: 'Buyer performance & leads' },
    { value: 'supplier_portal', label: 'Supplier Portal', description: 'Affiliate reporting' },
];

const needsAdvanced = computed(() =>
    form.role === 'staff'
    || ['buyer_portal', 'supplier_portal'].includes(form.role),
);

const isCurrentUser = computed(() => props.user.id === currentUserId.value);

const toggleModule = (key) => {
    const idx = form.allowed_modules.indexOf(key);
    if (idx >= 0) {
        form.allowed_modules.splice(idx, 1);
    } else {
        form.allowed_modules.push(key);
    }
};

const roleCardClass = (role, selected) => {
    const base = 'rounded-xl border p-3 text-left transition';
    if (!selected) {
        return `${base} border-slate-200 hover:border-indigo-300 hover:bg-slate-50 dark:border-slate-700 dark:hover:border-indigo-600 dark:hover:bg-slate-800/50`;
    }
    return `${base} border-indigo-500 bg-indigo-50 ring-2 ring-indigo-500/30 dark:border-indigo-500 dark:bg-indigo-950/40`;
};

const submit = () => {
    form.put(route('users.update', props.user.id), {
        onSuccess: () => pushToast('User updated.', 'success'),
        onError: () => pushToast('Could not update user — check the form.', 'error'),
    });
};

const suspend = () => {
    if (!confirm('Suspend this user? They will not be able to sign in.')) return;
    router.post(route('users.suspend', props.user.id), {}, {
        onSuccess: () => pushToast('User suspended.', 'success'),
    });
};

const activate = () => {
    router.post(route('users.activate', props.user.id), {
        onSuccess: () => pushToast('User reactivated.', 'success'),
    });
};

const emailCredentials = () => {
    if (!confirm('Generate a new password and email credentials to this user?')) return;
    router.post(route('users.email-credentials', props.user.id), {}, {
        onSuccess: () => pushToast('Credentials emailed.', 'success'),
    });
};

const destroy = () => {
    if (!confirm('Delete this user permanently?')) return;
    router.delete(route('users.destroy', props.user.id));
};
</script>

<template>
    <Head :title="`Edit ${user.name}`" />
    <AuthenticatedLayout>
        <PageHeader :title="`Edit ${user.name}`" description="Update profile, role, module access, and account status.">
            <template #actions>
                <AppButton variant="secondary" :href="route('users.index')">← Users</AppButton>
            </template>
        </PageHeader>

        <div class="mx-auto max-w-2xl space-y-6">
            <Panel>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ user.email }}</p>
                    <span
                        :class="[
                            'rounded-full px-2.5 py-0.5 text-xs font-medium',
                            user.is_suspended ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700',
                        ]"
                    >
                        {{ user.is_suspended ? 'Suspended' : 'Active' }}
                    </span>
                </div>
            </Panel>

            <Panel title="Profile & access">
                <form class="space-y-5" @submit.prevent="submit">
                    <FormErrorSummary :errors="form.errors" />

                    <div>
                        <InputLabel value="Role" />
                        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <button
                                v-for="role in roleOptions"
                                :key="role.value"
                                type="button"
                                :class="roleCardClass(role.value, form.role === role.value)"
                                @click="form.role = role.value"
                            >
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ role.label }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ role.description }}</p>
                            </button>
                        </div>
                        <InputError class="mt-1" :message="form.errors.role" />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Name" />
                            <TextInput v-model="form.name" class="mt-1 w-full" required />
                            <InputError class="mt-1" :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel value="Email" />
                            <TextInput v-model="form.email" type="email" class="mt-1 w-full" required />
                            <InputError class="mt-1" :message="form.errors.email" />
                        </div>
                    </div>

                    <div v-if="needsAdvanced">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 dark:border-slate-700 dark:text-slate-200"
                            @click="showAdvanced = !showAdvanced"
                        >
                            <span>Advanced options</span>
                            <span class="text-slate-400">{{ showAdvanced ? '▲' : '▼' }}</span>
                        </button>
                        <div v-show="showAdvanced" class="mt-3 space-y-4 rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-900/40">
                            <div v-if="form.role === 'buyer_portal'">
                                <InputLabel value="Link to buyer" />
                                <select v-model="form.buyer_id" class="form-select mt-1 w-full" required>
                                    <option value="">Select buyer</option>
                                    <option v-for="b in buyers" :key="b.id" :value="b.id">{{ b.name }} ({{ b.reference }})</option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.buyer_id" />
                            </div>
                            <div v-if="form.role === 'supplier_portal'">
                                <InputLabel value="Link to supplier" />
                                <select v-model="form.supplier_id" class="form-select mt-1 w-full" required>
                                    <option value="">Select supplier</option>
                                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }} ({{ s.reference }})</option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.supplier_id" />
                            </div>
                            <div v-if="form.role === 'staff'">
                                <InputLabel value="Module access" />
                                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                    <label v-for="mod in modules" :key="mod.key" class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-400">
                                        <input type="checkbox" class="mt-1 rounded" :checked="form.allowed_modules.includes(mod.key)" @change="toggleModule(mod.key)" />
                                        <span>{{ mod.label }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <InputLabel value="New password (optional)" />
                            <button type="button" class="text-xs font-medium text-slate-600 hover:underline" @click="showPassword = !showPassword">
                                {{ showPassword ? 'Hide' : 'Show' }}
                            </button>
                        </div>
                        <TextInput v-model="form.password" :type="showPassword ? 'text' : 'password'" class="mt-1 w-full font-mono" autocomplete="new-password" />
                        <InputError class="mt-1" :message="form.errors.password" />
                    </div>

                    <PrimaryButton :disabled="form.processing">Save changes</PrimaryButton>
                </form>
            </Panel>

            <Panel v-if="!isCurrentUser" title="Account actions">
                <div class="flex flex-wrap gap-2">
                    <AppButton variant="secondary" @click="emailCredentials">Email login credentials</AppButton>
                    <AppButton v-if="!user.is_suspended" variant="ghost" @click="suspend">Suspend</AppButton>
                    <AppButton v-else variant="ghost" @click="activate">Activate</AppButton>
                    <AppButton variant="danger" @click="destroy">Delete user</AppButton>
                </div>
            </Panel>
            <Panel v-else title="Account actions">
                <p class="text-sm text-slate-500">
                    <span v-if="isImpersonating">End impersonation to manage this account.</span>
                    <span v-else>You cannot suspend or delete your own account.</span>
                </p>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
