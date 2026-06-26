<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import LogFilters from '@/Components/UI/LogFilters.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { feedTypeBadgeClass, feedTypeLabels, statusBadgeClass } from '@/utils/liveFeedStyle';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    liveFeed: Object,
    stats: Object,
    filters: Object,
    tenants: Array,
    typeOptions: Array,
});

const expandedId = ref(null);

const feedStatStrip = computed(() => [
    { label: 'Activity', value: props.stats?.total ?? 0, accent: 'indigo' },
    { label: 'Lead events', value: props.stats?.lead_events ?? 0, accent: 'cyan' },
    { label: 'Deliveries', value: props.stats?.deliveries ?? 0, accent: 'emerald' },
    { label: 'Access', value: props.stats?.access ?? 0, accent: 'violet' },
]);

const toggleRow = (id) => {
    expandedId.value = expandedId.value === id ? null : id;
};

const hasMeta = (item) => item.meta && Object.keys(item.meta).length > 0;
</script>

<template>
    <Head title="Live Feed" />
    <AuthenticatedLayout>
        <PageHeader
            title="Live feed"
            description="Real-time activity across all partner platforms — leads, deliveries, and platform events."
        >
            <template #actions>
                <AppButton :href="route('command-center.index')" variant="secondary">Command Center</AppButton>
                <AppButton :href="route('platform-events.index')" variant="secondary">Platform events</AppButton>
            </template>
        </PageHeader>

        <CompactStatStrip :items="feedStatStrip" :columns="4" class="mb-6" />

        <LogFilters
            class="mb-6"
            route-name="live-feed.index"
            :filters="filters"
            show-days
            show-date-range
            show-feed-type
            show-tenant
            show-search
            :type-options="typeOptions"
            :tenants="tenants"
            search-label="Search"
            search-placeholder="Message, user, UUID, IP, path…"
        />

        <Panel title="Activity log" :padding="false">
            <DataTable :empty="!liveFeed?.data?.length" empty-message="No activity matches your filters.">
                <template #head>
                    <th class="w-6" />
                    <th class="text-left">When</th>
                    <th class="text-left">Type</th>
                    <th class="text-left">Tenant</th>
                    <th class="text-left">Message</th>
                    <th class="text-left">Actor / detail</th>
                    <th class="text-left">Status</th>
                    <th class="text-right">Link</th>
                </template>
                <template v-for="item in liveFeed?.data ?? []" :key="item.id">
                    <tr
                        class="cursor-pointer transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
                        @click="toggleRow(item.id)"
                    >
                        <td class="text-center text-slate-400">
                            <span class="inline-block text-[10px] transition" :class="expandedId === item.id ? 'rotate-90' : ''">▸</span>
                        </td>
                        <td class="whitespace-nowrap"><FormattedDate :value="item.created_at" /></td>
                        <td>
                            <span :class="['rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase', feedTypeBadgeClass(item.type)]">
                                {{ feedTypeLabels[item.type] ?? item.type }}
                            </span>
                        </td>
                        <td class="max-w-[8rem] truncate text-slate-600 dark:text-slate-400">{{ item.tenant ?? '—' }}</td>
                        <td class="max-w-xs truncate">{{ item.message }}</td>
                        <td class="max-w-[10rem] truncate text-slate-600 dark:text-slate-400">
                            {{ item.actor ?? item.detail ?? '—' }}
                        </td>
                        <td>
                            <span
                                v-if="item.status"
                                :class="['rounded-full px-1.5 py-0.5 text-[10px] font-semibold uppercase', statusBadgeClass(item.status)]"
                            >
                                {{ item.status }}
                            </span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="text-right" @click.stop>
                            <Link
                                v-if="item.href"
                                :href="item.href"
                                class="text-[10px] font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                            >
                                View
                            </Link>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                    </tr>
                    <tr v-if="expandedId === item.id" class="bg-slate-50 dark:bg-slate-900/50">
                        <td colspan="8" class="!py-3">
                            <div class="grid gap-3 text-xs md:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <p class="font-semibold uppercase text-slate-500">Detail</p>
                                    <p class="text-slate-700 dark:text-slate-300">{{ item.detail ?? '—' }}</p>
                                </div>
                                <div v-if="item.meta?.lead_uuid">
                                    <p class="font-semibold uppercase text-slate-500">Lead UUID</p>
                                    <p class="font-mono text-slate-700 dark:text-slate-300">{{ item.meta.lead_uuid }}</p>
                                </div>
                                <div v-if="item.meta?.campaign">
                                    <p class="font-semibold uppercase text-slate-500">Campaign</p>
                                    <p class="text-slate-700 dark:text-slate-300">{{ item.meta.campaign }}</p>
                                </div>
                                <div v-if="item.meta?.ip_address">
                                    <p class="font-semibold uppercase text-slate-500">IP address</p>
                                    <p class="font-mono text-slate-700 dark:text-slate-300">{{ item.meta.ip_address }}</p>
                                </div>
                                <div v-if="item.meta?.path">
                                    <p class="font-semibold uppercase text-slate-500">Path</p>
                                    <p class="font-mono text-slate-700 dark:text-slate-300">{{ item.meta.path }}</p>
                                </div>
                                <div v-if="item.meta?.duration_ms != null">
                                    <p class="font-semibold uppercase text-slate-500">Duration</p>
                                    <p class="text-slate-700 dark:text-slate-300">{{ item.meta.duration_ms }}ms</p>
                                </div>
                                <div v-if="item.meta?.revenue != null">
                                    <p class="font-semibold uppercase text-slate-500">Revenue</p>
                                    <p class="text-slate-700 dark:text-slate-300">{{ item.meta.revenue }}</p>
                                </div>
                                <div v-if="item.meta?.skipped_reason">
                                    <p class="font-semibold uppercase text-slate-500">Skipped</p>
                                    <p class="text-slate-700 dark:text-slate-300">{{ item.meta.skipped_reason }}</p>
                                </div>
                                <div v-if="item.meta?.payload && Object.keys(item.meta.payload).length" class="md:col-span-2 lg:col-span-4">
                                    <p class="mb-1 font-semibold uppercase text-slate-500">Payload</p>
                                    <pre class="max-h-48 overflow-auto rounded-lg bg-slate-900 p-2 text-[10px] text-emerald-300">{{ JSON.stringify(item.meta.payload, null, 2) }}</pre>
                                </div>
                                <div v-if="!hasMeta(item)" class="md:col-span-2 lg:col-span-4 text-slate-500">
                                    No additional metadata for this entry.
                                </div>
                            </div>
                        </td>
                    </tr>
                </template>
            </DataTable>
            <Pagination :links="liveFeed?.links ?? []" />
        </Panel>
    </AuthenticatedLayout>
</template>
