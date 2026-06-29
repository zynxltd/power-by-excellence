<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    exports: Object,
    buyers: Array,
});

const showCreate = ref(false);

const createForm = useForm({
    name: '',
    buyer_id: '',
    format: 'csv',
    delivery_method: 'email',
    cron: '0 8 * * *',
    status: 'active',
    remote_host: '',
    remote_port: '',
    remote_path: '/',
    remote_username: '',
    remote_credentials: '',
    config: {
        email: '',
    },
});

const isRemoteDelivery = computed(() => ['ftp', 'sftp'].includes(createForm.delivery_method));

const defaultPort = computed(() => (createForm.delivery_method === 'sftp' ? 22 : 21));

const submitCreate = () => {
    const payload = {
        ...createForm.data(),
        remote_port: createForm.remote_port || defaultPort.value,
    };

    createForm.transform(() => payload).post(route('scheduled-exports.store'), {
        onSuccess: () => {
            createForm.reset();
            createForm.delivery_method = 'email';
            createForm.remote_path = '/';
            showCreate.value = false;
        },
    });
};

const runNow = (id) => router.post(route('scheduled-exports.run', id));
const destroy = (id) => {
    if (confirm('Delete this scheduled export?')) {
        router.delete(route('scheduled-exports.destroy', id));
    }
};

const toggleStatus = (item) => {
    router.put(route('scheduled-exports.update', item.id), {
        name: item.name,
        buyer_id: item.buyer_id,
        format: item.format,
        delivery_method: item.delivery_method,
        cron: item.cron,
        config: item.config,
        remote_host: item.remote_host,
        remote_port: item.remote_port,
        remote_path: item.remote_path,
        remote_username: item.remote_username,
        status: item.status === 'active' ? 'paused' : 'active',
    });
};
</script>

<template>
    <Head title="Scheduled exports" />
    <AuthenticatedLayout>
        <PageHeader title="Scheduled exports" description="Automated CSV exports delivered by email, FTP, or SFTP on a cron schedule.">
            <template #actions>
                <AppButton @click="showCreate = !showCreate">{{ showCreate ? 'Cancel' : 'New export' }}</AppButton>
            </template>
        </PageHeader>

        <Panel v-if="showCreate" title="Create scheduled export" class="mb-6">
            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submitCreate">
                <FormErrorSummary :errors="createForm.errors" class="md:col-span-2" />

                <div>
                    <InputLabel value="Name" />
                    <input v-model="createForm.name" type="text" class="form-input mt-1 w-full" required />
                </div>

                <div>
                    <InputLabel value="Buyer (optional)" />
                    <select v-model="createForm.buyer_id" class="form-select mt-1 w-full">
                        <option value="">All buyers</option>
                        <option v-for="b in buyers" :key="b.id" :value="b.id">{{ b.name }} ({{ b.reference }})</option>
                    </select>
                </div>

                <div>
                    <InputLabel value="Cron schedule" />
                    <input v-model="createForm.cron" type="text" class="form-input mt-1 w-full font-mono text-sm" placeholder="0 8 * * *" />
                    <p class="mt-1 text-xs text-slate-500">Default: daily at 08:00 UTC</p>
                </div>

                <div>
                    <InputLabel value="Delivery method" />
                    <select v-model="createForm.delivery_method" class="form-select mt-1 w-full">
                        <option value="email">Email</option>
                        <option value="ftp">FTP</option>
                        <option value="sftp">SFTP</option>
                    </select>
                </div>

                <template v-if="createForm.delivery_method === 'email'">
                    <div class="md:col-span-2">
                        <InputLabel value="Recipient email" />
                        <input v-model="createForm.config.email" type="email" class="form-input mt-1 w-full" required />
                    </div>
                </template>

                <template v-else>
                    <div>
                        <InputLabel :value="`${createForm.delivery_method.toUpperCase()} host`" />
                        <input v-model="createForm.remote_host" type="text" class="form-input mt-1 w-full font-mono text-sm" required placeholder="ftp.example.com" />
                    </div>
                    <div>
                        <InputLabel value="Port" />
                        <input v-model="createForm.remote_port" type="number" class="form-input mt-1 w-full font-mono text-sm" :placeholder="String(defaultPort)" />
                    </div>
                    <div>
                        <InputLabel value="Username" />
                        <input v-model="createForm.remote_username" type="text" class="form-input mt-1 w-full" required />
                    </div>
                    <div>
                        <InputLabel value="Password" />
                        <input v-model="createForm.remote_credentials" type="password" class="form-input mt-1 w-full" required autocomplete="new-password" />
                    </div>
                    <div class="md:col-span-2">
                        <InputLabel value="Remote path" />
                        <input v-model="createForm.remote_path" type="text" class="form-input mt-1 w-full font-mono text-sm" placeholder="/exports/" />
                    </div>
                </template>

                <div class="md:col-span-2">
                    <PrimaryButton :disabled="createForm.processing">Create export</PrimaryButton>
                </div>
            </form>
        </Panel>

        <Panel title="Configured exports" :padding="false">
            <DataTable :empty="!exports?.data?.length" empty-message="No scheduled exports yet.">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Buyer</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Method</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Destination</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Cron</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Last run</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                </template>
                <tr v-for="item in exports.data" :key="item.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ item.name }}</td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ item.buyer?.name ?? 'All' }}</td>
                    <td class="px-6 py-4 uppercase text-slate-600 dark:text-slate-400">{{ item.delivery_method }}</td>
                    <td class="px-6 py-4 font-mono text-xs text-slate-500">
                        <span v-if="item.delivery_method === 'email'">{{ item.config?.email_recipients?.[0] ?? '—' }}</span>
                        <span v-else>{{ item.remote_host ?? item.config?.ftp_host ?? '—' }}</span>
                    </td>
                    <td class="px-6 py-4 font-mono text-xs text-slate-500">{{ item.cron }}</td>
                    <td class="px-6 py-4"><StatusBadge :status="item.status" /></td>
                    <td class="px-6 py-4"><FormattedDate :value="item.last_run_at" /></td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex flex-wrap justify-end gap-1">
                            <AppButton variant="ghost" @click="runNow(item.id)">Run now</AppButton>
                            <AppButton variant="ghost" @click="toggleStatus(item)">{{ item.status === 'active' ? 'Pause' : 'Resume' }}</AppButton>
                            <AppButton variant="ghost" @click="destroy(item.id)">Delete</AppButton>
                        </div>
                    </td>
                </tr>
            </DataTable>
            <Pagination :links="exports.links" />
        </Panel>
    </AuthenticatedLayout>
</template>
