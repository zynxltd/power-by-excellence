<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    accessLogs: Object,
    auditLogs: Object,
    stats: Object,
    filters: Object,
    days: Number,
});

const localAction = ref(props.filters?.action ?? '');

const applyFilter = () => {
    router.get(route('logs.security'), { action: localAction.value || undefined }, {
        preserveState: true,
        replace: true,
    });
};

watch(() => props.filters, (f) => { localAction.value = f?.action ?? ''; });

const securityStatStrip = computed(() => [
    { label: 'Logins today', value: props.stats?.logins_today ?? 0, accent: 'emerald' },
    { label: 'Failed (24h)', value: props.stats?.failed_logins_24h ?? 0, accent: 'rose' },
    { label: 'Unique IPs', value: props.stats?.unique_ips_24h ?? 0, accent: 'cyan' },
    { label: 'Audit (24h)', value: props.stats?.audit_events_24h ?? 0, accent: 'indigo' },
    { label: 'Deliv errors', value: props.stats?.delivery_errors_24h ?? 0, accent: 'amber' },
    { label: 'Avg deliv ms', value: props.stats?.avg_delivery_ms ?? 0, accent: 'indigo' },
]);
</script>

<template>
    <Head title="Security Logs" />
    <AuthenticatedLayout>
        <PageHeader
            title="Security Logs"
            description="Access activity, audit events, and platform security metrics."
        />

        <CompactStatStrip :items="securityStatStrip" :columns="6" class="mb-6" />

        <Panel title="Access Log Filter" class="mb-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Action</label>
                    <select v-model="localAction" class="form-select w-full sm:max-w-xs">
                        <option value="">All actions</option>
                        <option value="login">Login</option>
                        <option value="logout">Logout</option>
                    </select>
                </div>
                <AppButton @click="applyFilter">Apply</AppButton>
            </div>
        </Panel>

        <Panel title="Access Logs" class="mb-6" :padding="false">
            <DataTable :empty="!accessLogs?.data?.length" empty-message="No access logs found.">
                <template #head>
                    <th class="text-left">When</th>
                    <th class="text-left">User</th>
                    <th class="text-left">Action</th>
                    <th class="text-left">IP</th>
                    <th class="text-left">Path</th>
                </template>
                <tr v-for="log in accessLogs.data" :key="log.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class=""><FormattedDate :value="log.created_at" /></td>
                    <td class="">
                        <p class="font-medium text-slate-900 dark:text-white">{{ log.user?.name ?? 'Unknown' }}</p>
                        <p class="text-xs text-slate-500">{{ log.user?.email }}</p>
                    </td>
                    <td class="">
                        <span
                            :class="[
                                'rounded-full px-2.5 py-0.5 text-xs font-medium capitalize',
                                log.action === 'login'
                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                    : 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
                            ]"
                        >
                            {{ log.action }}
                        </span>
                    </td>
                    <td class="font-mono text-xs text-slate-500">{{ log.ip_address ?? '-' }}</td>
                    <td class="text-xs text-slate-500">{{ log.path ?? '-' }}</td>
                </tr>
            </DataTable>
            <div v-if="accessLogs?.links?.length > 3" class="flex flex-wrap justify-center gap-1 border-t border-slate-100 dark:border-slate-800">
                <Link
                    v-for="link in accessLogs.links"
                    :key="'access-' + link.label"
                    :href="link.url ?? '#'"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-sm',
                        link.active ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
                        !link.url && 'pointer-events-none opacity-40',
                    ]"
                    v-html="link.label"
                />
            </div>
        </Panel>

        <Panel title="Audit Logs" :padding="false">
            <DataTable :empty="!auditLogs?.data?.length" empty-message="No audit events found.">
                <template #head>
                    <th class="text-left">When</th>
                    <th class="text-left">User</th>
                    <th class="text-left">Action</th>
                    <th class="text-left">Entity</th>
                    <th class="text-left">IP</th>
                </template>
                <tr v-for="log in auditLogs.data" :key="log.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class=""><FormattedDate :value="log.created_at" /></td>
                    <td class="">
                        <p class="font-medium text-slate-900 dark:text-white">{{ log.user?.name ?? 'System' }}</p>
                        <p class="text-xs text-slate-500">{{ log.user?.email }}</p>
                    </td>
                    <td class="">
                        <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                            {{ log.action }}
                        </span>
                    </td>
                    <td class="text-xs text-slate-600 dark:text-slate-400">
                        {{ log.entity_type ?? '-' }}
                        <span v-if="log.entity_id" class="font-mono text-xs text-slate-500">#{{ log.entity_id }}</span>
                    </td>
                    <td class="font-mono text-xs text-slate-500">{{ log.ip_address ?? '-' }}</td>
                </tr>
            </DataTable>
            <div v-if="auditLogs?.links?.length > 3" class="flex flex-wrap justify-center gap-1 border-t border-slate-100 dark:border-slate-800">
                <Link
                    v-for="link in auditLogs.links"
                    :key="'audit-' + link.label"
                    :href="link.url ?? '#'"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-sm',
                        link.active ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
                        !link.url && 'pointer-events-none opacity-40',
                    ]"
                    v-html="link.label"
                />
            </div>
        </Panel>
    </AuthenticatedLayout>
</template>
