<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

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
</script>

<template>
    <Head title="Security Logs" />
    <AuthenticatedLayout>
        <PageHeader
            title="Security Logs"
            description="Access activity, audit events, and platform security metrics."
        />

        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
            <StatCard label="Logins today" :value="stats.logins_today ?? 0" accent="emerald" />
            <StatCard label="Failed logins (24h)" :value="stats.failed_logins_24h ?? 0" accent="rose" />
            <StatCard label="Unique IPs (24h)" :value="stats.unique_ips_24h ?? 0" accent="cyan" />
            <StatCard label="Audit events (24h)" :value="stats.audit_events_24h ?? 0" accent="indigo" />
            <StatCard label="Delivery errors (24h)" :value="stats.delivery_errors_24h ?? 0" accent="amber" />
            <StatCard label="Avg delivery (ms)" :value="stats.avg_delivery_ms ?? 0" accent="indigo" />
        </div>

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
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">When</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">User</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Action</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">IP</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Path</th>
                </template>
                <tr v-for="log in accessLogs.data" :key="log.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4"><FormattedDate :value="log.created_at" /></td>
                    <td class="px-6 py-4">
                        <p class="font-medium text-slate-900 dark:text-white">{{ log.user?.name ?? 'Unknown' }}</p>
                        <p class="text-xs text-slate-500">{{ log.user?.email }}</p>
                    </td>
                    <td class="px-6 py-4">
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
                    <td class="px-6 py-4 font-mono text-xs text-slate-500">{{ log.ip_address ?? '—' }}</td>
                    <td class="px-6 py-4 text-xs text-slate-500">{{ log.path ?? '—' }}</td>
                </tr>
            </DataTable>
            <div v-if="accessLogs?.links?.length > 3" class="flex flex-wrap justify-center gap-1 border-t border-slate-100 px-6 py-4 dark:border-slate-800">
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
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">When</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">User</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Action</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Entity</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">IP</th>
                </template>
                <tr v-for="log in auditLogs.data" :key="log.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4"><FormattedDate :value="log.created_at" /></td>
                    <td class="px-6 py-4">
                        <p class="font-medium text-slate-900 dark:text-white">{{ log.user?.name ?? 'System' }}</p>
                        <p class="text-xs text-slate-500">{{ log.user?.email }}</p>
                    </td>
                    <td class="px-6 py-4">
                        <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                            {{ log.action }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                        {{ log.entity_type ?? '—' }}
                        <span v-if="log.entity_id" class="font-mono text-xs text-slate-500">#{{ log.entity_id }}</span>
                    </td>
                    <td class="px-6 py-4 font-mono text-xs text-slate-500">{{ log.ip_address ?? '—' }}</td>
                </tr>
            </DataTable>
            <div v-if="auditLogs?.links?.length > 3" class="flex flex-wrap justify-center gap-1 border-t border-slate-100 px-6 py-4 dark:border-slate-800">
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
