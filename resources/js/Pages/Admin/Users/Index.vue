<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import InputError from '@/Components/InputError.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import TenantContextBanner from '@/Components/UI/TenantContextBanner.vue';
import { pushToast } from '@/Composables/useToast';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);
const isImpersonating = computed(() => Boolean(page.props.auth?.impersonator));

const props = defineProps({
    users: Object,
    buyers: Array,
    suppliers: Array,
    modules: Array,
    portalUrl: String,
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
});

const showCreate = ref(false);
const showAdvancedCreate = ref(false);
const showAdvancedEdit = ref(false);
const showCreatePassword = ref(false);
const showEditPassword = ref(false);

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
const editPanel = ref(null);
const editForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'staff',
    buyer_id: '',
    supplier_id: '',
    allowed_modules: [],
});

const filterForm = useForm({
    search: props.filters?.search ?? '',
    role: props.filters?.role ?? '',
    status: props.filters?.status ?? '',
});

const roleOptions = [
    { value: 'account_admin', label: 'Account Admin', description: 'Full platform access', accent: 'indigo' },
    { value: 'staff', label: 'Staff', description: 'Limited module access', accent: 'violet' },
    { value: 'buyer_portal', label: 'Buyer Portal', description: 'Buyer performance & leads', accent: 'cyan' },
    { value: 'supplier_portal', label: 'Supplier Portal', description: 'Affiliate reporting', accent: 'amber' },
];

const statStrip = computed(() => [
    { label: 'Total users', value: props.summary?.total ?? 0, accent: 'indigo' },
    { label: 'Active', value: props.summary?.active ?? 0, accent: 'emerald' },
    { label: 'Suspended', value: props.summary?.suspended ?? 0, accent: 'rose' },
    { label: 'Admins', value: props.summary?.admins ?? 0, accent: 'violet' },
    { label: 'Portal users', value: props.summary?.portal ?? 0, accent: 'cyan' },
]);

const needsAdvancedCreate = computed(() =>
    form.role === 'staff'
    || ['buyer_portal', 'supplier_portal'].includes(form.role),
);

const needsAdvancedEdit = computed(() =>
    editForm.role === 'staff'
    || ['buyer_portal', 'supplier_portal'].includes(editForm.role),
);

watch(() => form.role, () => {
    if (!needsAdvancedCreate.value) {
        showAdvancedCreate.value = false;
    }
});

watch(() => Object.keys(form.errors).length, (count) => {
    if (count > 0) {
        showCreate.value = true;
    }
});

const openCreate = () => {
    showCreate.value = true;
    editingId.value = null;
    if (!form.password) {
        generatePassword(form);
    }
};

const closeCreate = () => {
    showCreate.value = false;
    showAdvancedCreate.value = false;
    form.clearErrors();
};

const submit = () => {
    form.post(route('users.store'), {
        onSuccess: () => {
            pushToast('User created.', 'success');
            form.reset('password', 'name', 'email', 'buyer_id', 'supplier_id');
            form.role = 'account_admin';
            form.send_credentials = true;
            closeCreate();
        },
        onError: () => pushToast('Could not create user - check the form.', 'error'),
    });
};

const applyFilters = () => {
    filterForm.get(route('users.index'), { preserveState: true, preserveScroll: true });
};

const clearFilters = () => {
    filterForm.search = '';
    filterForm.role = '';
    filterForm.status = '';
    applyFilters();
};

const destroy = (id) => {
    if (confirm('Delete this user?')) {
        router.delete(route('users.destroy', id), {
            onError: () => pushToast('Could not delete user.', 'error'),
        });
    }
};
const suspend = (id) => {
    if (confirm('Suspend this user? They will not be able to sign in.')) {
        router.post(route('users.suspend', id), {}, {
            onError: () => pushToast('Could not suspend user.', 'error'),
        });
    }
};
const activate = (id) => router.post(route('users.activate', id));
const emailCredentials = (id) => {
    if (confirm('Generate a new password and email credentials to this user?')) {
        router.post(route('users.email-credentials', id), {}, {
            onError: () => pushToast('Could not email credentials.', 'error'),
        });
    }
};

const isCurrentUser = (user) => user.id === currentUserId.value;

const startEdit = async (user) => {
    showCreate.value = false;
    editingId.value = user.id;
    showAdvancedEdit.value = user.role === 'staff' || ['buyer_portal', 'supplier_portal'].includes(user.role);
    editForm.clearErrors();
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.password = '';
    editForm.role = user.role;
    editForm.buyer_id = user.buyer?.id ?? '';
    editForm.supplier_id = user.supplier?.id ?? '';
    editForm.allowed_modules = [...(user.allowed_modules ?? [])];
    await nextTick();
    editPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const saveEdit = () => {
    editForm.put(route('users.update', editingId.value), {
        onSuccess: () => {
            pushToast('User updated.', 'success');
            editingId.value = null;
            showAdvancedEdit.value = false;
        },
        onError: () => pushToast('Could not update user - check the form.', 'error'),
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

const generatePassword = (target) => {
    const chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$';
    let password = '';
    for (let i = 0; i < 14; i += 1) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    target.password = password;
};

const copyPassword = async (password) => {
    if (!password) {
        return;
    }

    try {
        await navigator.clipboard.writeText(password);
        pushToast('Password copied to clipboard.', 'success');
    } catch {
        pushToast('Could not copy password.', 'error');
    }
};

const roleCardClass = (role, selected) => {
    const base = 'rounded-xl border p-3 text-left transition';
    if (!selected) {
        return `${base} border-slate-200 hover:border-indigo-300 hover:bg-slate-50 dark:border-slate-700 dark:hover:border-indigo-600 dark:hover:bg-slate-800/50`;
    }
    return `${base} border-indigo-500 bg-indigo-50 ring-2 ring-indigo-500/30 dark:border-indigo-500 dark:bg-indigo-950/40`;
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
        <PageHeader title="Users" description="Manage team members, portal users, and access roles. Email credentials to buyers and suppliers for seamless portal onboarding.">
            <template #actions>
                <AppButton v-if="portalUrl" variant="secondary" :href="portalUrl" target="_blank">Portal login URL</AppButton>
                <AppButton @click="showCreate ? closeCreate() : openCreate()">
                    {{ showCreate ? 'Cancel' : '+ Add user' }}
                </AppButton>
            </template>
        </PageHeader>

        <TenantContextBanner />

        <CompactStatStrip :items="statStrip" class="mb-6" />

        <div class="space-y-6">
            <Panel v-if="showCreate" title="Create user" class="ring-2 ring-indigo-500/20">
                <form class="space-y-5" @submit.prevent="submit">
                    <FormErrorSummary :errors="form.errors" />

                    <div>
                        <InputLabel value="Role" />
                        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                            <button
                                v-for="role in roleOptions"
                                :key="role.value"
                                type="button"
                                :class="roleCardClass(role.value, form.role === role.value)"
                                @click="form.role = role.value"
                            >
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ role.label }}</p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ role.description }}</p>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
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
                        <div class="md:col-span-2">
                            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-900/40">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <InputLabel value="Password" />
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400" @click="generatePassword(form)">
                                            Generate secure password
                                        </button>
                                        <button
                                            v-if="form.password"
                                            type="button"
                                            class="text-xs font-medium text-slate-600 hover:underline dark:text-slate-300"
                                            @click="copyPassword(form.password)"
                                        >
                                            Copy
                                        </button>
                                        <button
                                            type="button"
                                            class="text-xs font-medium text-slate-600 hover:underline dark:text-slate-300"
                                            @click="showCreatePassword = !showCreatePassword"
                                        >
                                            {{ showCreatePassword ? 'Hide' : 'Show' }}
                                        </button>
                                    </div>
                                </div>
                                <TextInput
                                    v-model="form.password"
                                    :type="showCreatePassword ? 'text' : 'password'"
                                    class="mt-2 w-full font-mono"
                                    placeholder="Leave blank to auto-generate on save"
                                />
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                    A 14-character password is generated automatically when you open this form. Leave blank and the server will create one on save.
                                </p>
                                <InputError class="mt-1" :message="form.errors.password" />

                                <label class="mt-4 flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3 dark:border-slate-700 dark:bg-slate-900">
                                    <input v-model="form.send_credentials" type="checkbox" class="mt-1 rounded" />
                                    <span>
                                        <span class="text-sm font-medium text-slate-800 dark:text-slate-200">Email login credentials on create</span>
                                        <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">
                                            Sends login URL, email address, and password to the user immediately after creation.
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div v-if="needsAdvancedCreate">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 dark:border-slate-700 dark:text-slate-200"
                            @click="showAdvancedCreate = !showAdvancedCreate"
                        >
                            <span>Advanced options</span>
                            <span class="text-slate-400">{{ showAdvancedCreate ? '▲' : '▼' }}</span>
                        </button>

                        <div v-show="showAdvancedCreate" class="mt-3 space-y-4 rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-900/40">
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
                                <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    <label v-for="mod in modules" :key="mod.key" class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-400">
                                        <input type="checkbox" class="mt-1 rounded" :checked="form.allowed_modules.includes(mod.key)" @change="toggleModule(form, mod.key)" />
                                        <span>
                                            <strong class="text-slate-800 dark:text-slate-200">{{ mod.label }}</strong>
                                            <br><span class="text-xs">{{ mod.description }}</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <PrimaryButton :loading="form.processing" :disabled="form.processing">Create user</PrimaryButton>
                        <AppButton type="button" variant="secondary" @click="closeCreate">Cancel</AppButton>
                    </div>
                </form>
            </Panel>

            <div v-if="editingId" ref="editPanel">
                <Panel title="Edit user" class="ring-2 ring-indigo-500/40">
                    <form class="space-y-5" @submit.prevent="saveEdit">
                        <FormErrorSummary :errors="editForm.errors" />

                        <div>
                            <InputLabel value="Role" />
                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                                <button
                                    v-for="role in roleOptions"
                                    :key="`edit-${role.value}`"
                                    type="button"
                                    :class="roleCardClass(role.value, editForm.role === role.value)"
                                    @click="editForm.role = role.value"
                                >
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ role.label }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ role.description }}</p>
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div><InputLabel value="Name" /><TextInput v-model="editForm.name" class="mt-1 w-full" required /></div>
                            <div><InputLabel value="Email" /><TextInput v-model="editForm.email" type="email" class="mt-1 w-full" required /></div>
                            <div>
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <InputLabel value="New password (optional)" class="flex-1" />
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" class="text-xs font-medium text-indigo-600 hover:underline" @click="generatePassword(editForm)">Generate</button>
                                        <button v-if="editForm.password" type="button" class="text-xs font-medium text-slate-600 hover:underline" @click="copyPassword(editForm.password)">Copy</button>
                                        <button type="button" class="text-xs font-medium text-slate-600 hover:underline" @click="showEditPassword = !showEditPassword">{{ showEditPassword ? 'Hide' : 'Show' }}</button>
                                    </div>
                                </div>
                                <TextInput v-model="editForm.password" :type="showEditPassword ? 'text' : 'password'" class="mt-1 w-full font-mono" />
                            </div>
                        </div>

                        <div v-if="needsAdvancedEdit">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 dark:border-slate-700 dark:text-slate-200"
                                @click="showAdvancedEdit = !showAdvancedEdit"
                            >
                                <span>Advanced options</span>
                                <span class="text-slate-400">{{ showAdvancedEdit ? '▲' : '▼' }}</span>
                            </button>
                            <div v-show="showAdvancedEdit" class="mt-3 space-y-4 rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-900/40">
                                <div v-if="editForm.role === 'buyer_portal'">
                                    <InputLabel value="Link to buyer" />
                                    <select v-model="editForm.buyer_id" class="form-select mt-1 w-full" required>
                                        <option value="">Select buyer</option>
                                        <option v-for="b in buyers" :key="b.id" :value="b.id">{{ b.name }} ({{ b.reference }})</option>
                                    </select>
                                </div>
                                <div v-if="editForm.role === 'supplier_portal'">
                                    <InputLabel value="Link to supplier" />
                                    <select v-model="editForm.supplier_id" class="form-select mt-1 w-full" required>
                                        <option value="">Select supplier</option>
                                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }} ({{ s.reference }})</option>
                                    </select>
                                </div>
                                <div v-if="editForm.role === 'staff'">
                                    <InputLabel value="Module access" />
                                    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                        <label v-for="mod in modules" :key="mod.key" class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-400">
                                            <input type="checkbox" class="mt-1 rounded" :checked="editForm.allowed_modules.includes(mod.key)" @change="toggleModule(editForm, mod.key)" />
                                            <span><strong class="text-slate-800 dark:text-slate-200">{{ mod.label }}</strong></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <PrimaryButton :loading="editForm.processing">Save changes</PrimaryButton>
                            <AppButton type="button" variant="secondary" @click="editingId = null">Cancel</AppButton>
                        </div>
                    </form>
                </Panel>
            </div>

            <Panel title="All users" :padding="false">
                <div class="border-b border-slate-100 p-4 dark:border-slate-800">
                    <form class="flex flex-wrap items-end gap-3" @submit.prevent="applyFilters">
                        <div class="min-w-[12rem] flex-1">
                            <InputLabel value="Search" />
                            <TextInput v-model="filterForm.search" type="search" placeholder="Name or email…" class="mt-1 w-full" />
                        </div>
                        <div>
                            <InputLabel value="Role" />
                            <select v-model="filterForm.role" class="form-select mt-1">
                                <option value="">All roles</option>
                                <option v-for="role in roleOptions" :key="`filter-${role.value}`" :value="role.value">{{ role.label }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Status" />
                            <select v-model="filterForm.status" class="form-select mt-1">
                                <option value="">All</option>
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <AppButton type="submit" variant="secondary">Filter</AppButton>
                        <AppButton v-if="filters.search || filters.role || filters.status" type="button" variant="ghost" @click="clearFilters">Clear</AppButton>
                    </form>
                </div>

                <DataTable :empty="!users?.data?.length" empty-message="No users match your filters.">
                    <template #head>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Linked</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                    </template>
                    <tr
                        v-for="u in users.data"
                        :key="u.id"
                        :class="[
                            'transition',
                            editingId === u.id
                                ? 'bg-indigo-50/80 dark:bg-indigo-500/10'
                                : 'hover:bg-slate-50 dark:hover:bg-slate-800/50',
                        ]"
                    >
                        <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ u.name }}</td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ u.email }}</td>
                        <td class="px-6 py-4">
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-700 dark:text-slate-300">{{ roleLabel(u.role) }}</span>
                            <p v-if="u.role === 'staff'" class="mt-1 text-xs text-slate-500">{{ u.allowed_modules?.length ?? 0 }} modules</p>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-500">{{ u.buyer?.name ?? u.supplier?.name ?? '-' }}</td>
                        <td class="px-6 py-4">
                            <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', u.is_suspended ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700']">
                                {{ u.is_suspended ? 'Suspended' : 'Active' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex flex-wrap justify-end gap-1">
                                <AppButton variant="ghost" @click="startEdit(u)">Edit</AppButton>
                                <AppButton v-if="!isCurrentUser(u)" variant="ghost" @click="emailCredentials(u.id)">Email login</AppButton>
                                <AppButton
                                    v-if="!u.is_suspended && !isCurrentUser(u)"
                                    variant="ghost"
                                    @click="suspend(u.id)"
                                >
                                    Suspend
                                </AppButton>
                                <span
                                    v-else-if="!u.is_suspended && isCurrentUser(u)"
                                    class="px-2 py-1 text-xs text-slate-400"
                                    :title="isImpersonating ? 'End impersonation to manage this account as super admin' : 'You cannot suspend your own account'"
                                >
                                    You
                                </span>
                                <AppButton v-else variant="ghost" @click="activate(u.id)">Activate</AppButton>
                                <AppButton v-if="!isCurrentUser(u)" variant="ghost" @click="destroy(u.id)">Delete</AppButton>
                            </div>
                        </td>
                    </tr>
                </DataTable>
                <Pagination :links="users.links" />
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
