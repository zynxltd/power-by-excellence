<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

defineProps({
    notifications: Object,
    tenants: Array,
    severities: Array,
});

const form = useForm({
    title: '',
    body: '',
    severity: 'info',
    account_id: '',
    expires_at: '',
});

const submit = () => {
    form.post(route('notifications.admin.store'), {
        onSuccess: () => form.reset('title', 'body', 'account_id', 'expires_at'),
    });
};

const destroy = (id) => {
    if (confirm('Delete this notification?')) {
        router.delete(route('notifications.admin.destroy', id));
    }
};

const severityClass = (s) => ({
    info: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
    warning: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    critical: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
}[s] ?? '');
</script>

<template>
    <Head title="Platform Notifications" />
    <AuthenticatedLayout>
        <PageHeader
            title="Platform Notifications"
            description="Push announcements to all tenants or a specific platform. Tenant activity (forms, API keys, campaigns) appears here automatically."
        />

        <div class="grid gap-6 lg:grid-cols-3">
            <Panel title="Send notification" class="lg:col-span-1">
                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <InputLabel value="Title" />
                        <TextInput v-model="form.title" class="mt-1 w-full" required />
                    </div>
                    <div>
                        <InputLabel value="Message" />
                        <textarea v-model="form.body" rows="4" class="form-textarea mt-1 w-full" placeholder="Optional details…" />
                    </div>
                    <div>
                        <InputLabel value="Severity" />
                        <select v-model="form.severity" class="form-select mt-1 w-full">
                            <option v-for="s in severities" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Target platform" />
                        <select v-model="form.account_id" class="form-select mt-1 w-full">
                            <option value="">All platforms</option>
                            <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.brand_name || t.name }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Expires (optional)" />
                        <TextInput v-model="form.expires_at" type="datetime-local" class="mt-1 w-full" />
                    </div>
                    <AppButton type="submit" :disabled="form.processing">Send notification</AppButton>
                </form>
            </Panel>

            <Panel title="All notifications & activity" class="lg:col-span-2" :padding="false">
                <DataTable :empty="!notifications?.data?.length">
                    <template #head>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">When</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Title</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Platform</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">Actions</th>
                    </template>
                    <tr v-for="n in notifications.data" :key="n.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-3 text-xs text-slate-500"><FormattedDate :value="n.created_at" /></td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="severityClass(n.severity)">{{ n.type }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900 dark:text-white">{{ n.title }}</p>
                            <p v-if="n.body" class="text-xs text-slate-500 line-clamp-1">{{ n.body }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ n.account ? (n.account.brand_name || n.account.name) : 'All platforms' }}</td>
                        <td class="px-4 py-3 text-right">
                            <button v-if="n.type === 'broadcast'" type="button" class="text-xs text-rose-600 hover:underline" @click="destroy(n.id)">Delete</button>
                            <span v-else class="text-xs text-slate-400">Auto-logged</span>
                        </td>
                    </tr>
                </DataTable>
                <Pagination :links="notifications.links" />
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
