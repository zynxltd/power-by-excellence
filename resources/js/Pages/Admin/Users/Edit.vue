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
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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
    { value: 'account_admin', label: 'Account Admin' },
    { value: 'staff', label: 'Staff' },
    { value: 'buyer_portal', label: 'Buyer Portal' },
    { value: 'supplier_portal', label: 'Supplier Portal' },
];

const needsAdvanced = computed(() =>
    form.role === 'staff'
    || ['buyer_portal', 'supplier_portal'].includes(form.role),
);

const toggleModule = (key) => {
    const idx = form.allowed_modules.indexOf(key);
    if (idx >= 0) {
        form.allowed_modules.splice(idx, 1);
    } else {
        form.allowed_modules.push(key);
    }
};

const submit = () => form.put(route('users.update', props.user.id));
</script>

<template>
    <Head :title="`Edit ${user.name}`" />
    <AuthenticatedLayout>
        <PageHeader :title="`Edit ${user.name}`" description="Update name, email, role, and module access.">
            <template #actions>
                <AppButton variant="secondary" :href="route('users.index')">← Users</AppButton>
            </template>
        </PageHeader>

        <Panel class="max-w-2xl">
            <form class="space-y-5" @submit.prevent="submit">
                <FormErrorSummary :errors="form.errors" />

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

                <div>
                    <InputLabel value="Role" />
                    <select v-model="form.role" class="form-select mt-1 w-full">
                        <option v-for="role in roleOptions" :key="role.value" :value="role.value">{{ role.label }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.role" />
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
                            <select v-model="form.buyer_id" class="form-select mt-1 w-full">
                                <option value="">Select buyer</option>
                                <option v-for="b in buyers" :key="b.id" :value="b.id">{{ b.name }} ({{ b.reference }})</option>
                            </select>
                        </div>
                        <div v-if="form.role === 'supplier_portal'">
                            <InputLabel value="Link to supplier" />
                            <select v-model="form.supplier_id" class="form-select mt-1 w-full">
                                <option value="">Select supplier</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }} ({{ s.reference }})</option>
                            </select>
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
    </AuthenticatedLayout>
</template>
