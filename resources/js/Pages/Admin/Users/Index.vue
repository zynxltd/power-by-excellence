<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import InputError from '@/Components/InputError.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import TenantContextBanner from '@/Components/UI/TenantContextBanner.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ users: Object, buyers: Array, suppliers: Array, modules: Array, portalUrl: String });

const form = useForm({
    name: '',
    email: '',
    password: '',
    role: 'account_admin',
    buyer_id: '',
    supplier_id: '',
    send_credentials: true,
    allowed_modules: props.modules?.filter((m) => ['dashboard', 'operations', 'campaigns'].includes(m.key)).map((m) => m.key) ?? [],
});

const editingId = ref(null);
const editForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'staff',
    buyer_id: '',
    supplier_id: '',
    allowed_modules: [],
});

const submit = () => form.post(route('users.store'), { onSuccess: () => form.reset('password') });
const destroy = (id) => { if (confirm('Delete this user?')) router.delete(route('users.destroy', id)); };
const suspend = (id) => { if (confirm('Suspend this user? They will not be able to sign in.')) router.post(route('users.suspend', id)); };
const activate = (id) => router.post(route('users.activate', id));
const emailCredentials = (id) => { if (confirm('Generate a new password and email credentials to this user?')) router.post(route('users.email-credentials', id)); };

const startEdit = (user) => {
    editingId.value = user.id;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.password = '';
    editForm.role = user.role;
    editForm.buyer_id = user.buyer?.id ?? '';
    editForm.supplier_id = user.supplier?.id ?? '';
    editForm.allowed_modules = [...(user.allowed_modules ?? [])];
};

const saveEdit = () => {
    editForm.put(route('users.update', editingId.value), {
        onSuccess: () => { editingId.value = null; },
    });
};

const toggleModule = (target, key) => {
    const list = target.allowed_modules ?? [];
    if (list.includes(key)) {
        target.allowed_modules = list.filter((m) => m !== key);
    } else {
        target.allowed_modules = [...list, key];
    }
};

const roleLabel = (role) => ({
    super_admin: 'Super Admin',
    account_admin: 'Account Admin',
    staff: 'Staff',
    buyer_portal: 'Buyer Portal',
    supplier_portal: 'Supplier Portal',
}[role] ?? role);
</script>

<template>
    <Head title="Users" />
    <AuthenticatedLayout>
        <PageHeader title="Users" description="Manage team members, portal users, and access roles. Email credentials to buyers and suppliers for seamless portal onboarding." />

        <TenantContextBanner />
        <div class="space-y-6">
            <Panel title="Create User">
                <form @submit.prevent="submit" class="space-y-4">
                    <FormErrorSummary :errors="form.errors" />
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div><InputLabel value="Name" /><TextInput v-model="form.name" class="mt-1 w-full" required /><InputError class="mt-1" :message="form.errors.name" /></div>
                        <div><InputLabel value="Email" /><TextInput v-model="form.email" type="email" class="mt-1 w-full" required /><InputError class="mt-1" :message="form.errors.email" /></div>
                        <div><InputLabel value="Password" /><TextInput v-model="form.password" type="password" class="mt-1 w-full" required /><InputError class="mt-1" :message="form.errors.password" /></div>
                        <div>
                            <InputLabel value="Role" />
                            <select v-model="form.role" class="form-select">
                                <option value="account_admin">Account Admin</option>
                                <option value="staff">Staff</option>
                                <option value="buyer_portal">Buyer Portal</option>
                                <option value="supplier_portal">Supplier Portal</option>
                            </select>
                        </div>
                        <div v-if="form.role === 'buyer_portal'">
                            <InputLabel value="Link to Buyer" />
                            <select v-model="form.buyer_id" class="form-select" required>
                                <option value="">Select buyer</option>
                                <option v-for="b in buyers" :key="b.id" :value="b.id">{{ b.name }} ({{ b.reference }})</option>
                            </select>
                        </div>
                        <div v-if="form.role === 'supplier_portal'">
                            <InputLabel value="Link to Supplier" />
                            <select v-model="form.supplier_id" class="form-select" required>
                                <option value="">Select supplier</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }} ({{ s.reference }})</option>
                            </select>
                        </div>
                        <label v-if="['buyer_portal', 'supplier_portal'].includes(form.role)" class="flex items-center gap-2 text-sm md:col-span-2">
                            <input v-model="form.send_credentials" type="checkbox" class="rounded" />
                            Email portal login credentials on create
                        </label>
                    </div>
                    <div v-if="form.role === 'staff'" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <InputLabel value="Module access" />
                        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <label v-for="mod in modules" :key="mod.key" class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-400">
                                <input type="checkbox" class="mt-1 rounded" :checked="form.allowed_modules.includes(mod.key)" @change="toggleModule(form, mod.key)" />
                                <span><strong class="text-slate-800 dark:text-slate-200">{{ mod.label }}</strong><br><span class="text-xs">{{ mod.description }}</span></span>
                            </label>
                        </div>
                    </div>
                    <PrimaryButton>Create User</PrimaryButton>
                </form>
            </Panel>

            <Panel title="All Users" :padding="false">
                <DataTable :empty="!users?.data?.length">
                    <template #head>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Linked</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                    </template>
                    <tr v-for="u in users.data" :key="u.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ u.name }}</td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ u.email }}</td>
                        <td class="px-6 py-4">
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-700 dark:text-slate-300">{{ roleLabel(u.role) }}</span>
                            <p v-if="u.role === 'staff'" class="mt-1 text-xs text-slate-500">{{ u.allowed_modules?.length ?? 0 }} modules</p>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-500">{{ u.buyer?.name ?? u.supplier?.name ?? '—' }}</td>
                        <td class="px-6 py-4">
                            <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', u.is_suspended ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700']">
                                {{ u.is_suspended ? 'Suspended' : 'Active' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex flex-wrap justify-end gap-1">
                                <AppButton variant="ghost" @click="startEdit(u)">Edit</AppButton>
                                <AppButton v-if="['buyer_portal', 'supplier_portal'].includes(u.role)" variant="ghost" @click="emailCredentials(u.id)">Email login</AppButton>
                                <AppButton v-if="!u.is_suspended" variant="ghost" @click="suspend(u.id)">Suspend</AppButton>
                                <AppButton v-else variant="ghost" @click="activate(u.id)">Activate</AppButton>
                                <AppButton variant="ghost" @click="destroy(u.id)">Delete</AppButton>
                            </div>
                        </td>
                    </tr>
                </DataTable>
                <Pagination :links="users.links" />
            </Panel>

            <Panel v-if="editingId" title="Edit User">
                <form @submit.prevent="saveEdit" class="space-y-4">
                    <FormErrorSummary :errors="editForm.errors" />
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div><InputLabel value="Name" /><TextInput v-model="editForm.name" class="mt-1 w-full" required /></div>
                        <div><InputLabel value="Email" /><TextInput v-model="editForm.email" type="email" class="mt-1 w-full" required /></div>
                        <div><InputLabel value="New password (optional)" /><TextInput v-model="editForm.password" type="password" class="mt-1 w-full" /></div>
                        <div>
                            <InputLabel value="Role" />
                            <select v-model="editForm.role" class="form-select">
                                <option value="account_admin">Account Admin</option>
                                <option value="staff">Staff</option>
                                <option value="buyer_portal">Buyer Portal</option>
                                <option value="supplier_portal">Supplier Portal</option>
                            </select>
                        </div>
                    </div>
                    <div v-if="editForm.role === 'staff'" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <InputLabel value="Module access" />
                        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <label v-for="mod in modules" :key="mod.key" class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-400">
                                <input type="checkbox" class="mt-1 rounded" :checked="editForm.allowed_modules.includes(mod.key)" @change="toggleModule(editForm, mod.key)" />
                                <span><strong class="text-slate-800 dark:text-slate-200">{{ mod.label }}</strong></span>
                            </label>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <PrimaryButton>Save changes</PrimaryButton>
                        <AppButton type="button" variant="secondary" @click="editingId = null">Cancel</AppButton>
                    </div>
                </form>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
