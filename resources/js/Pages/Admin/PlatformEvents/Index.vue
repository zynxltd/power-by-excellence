<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import LogFilters from '@/Components/UI/LogFilters.vue';
import { eventTypeBadgeClass, formatEventType, levelBadgeClass } from '@/utils/platformEventStyle';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    events: Object,
    filters: Object,
    stats: Object,
    tenants: Array,
    levelOptions: Array,
    categoryOptions: Array,
    eventTypes: Array,
});

const expandedId = ref(null);

const eventStatStrip = computed(() => [
    { label: 'Events', value: props.stats?.total ?? 0, accent: 'indigo' },
    { label: 'Warnings', value: props.stats?.warnings ?? 0, accent: 'amber' },
    { label: 'Errors', value: props.stats?.errors ?? 0, accent: 'rose' },
    { label: 'Unique leads', value: props.stats?.unique_leads ?? 0, accent: 'cyan' },
    { label: 'Sold', value: props.stats?.sold ?? 0, accent: 'emerald' },
]);

const toggleRow = (id) => {
    expandedId.value = expandedId.value === id ? null : id;
};

const hasPayload = (event) => event.payload && Object.keys(event.payload).length > 0;
</script>

<template>
    <Head title="Platform Events" />
    <AuthenticatedLayout>
        <PageHeader
            title="Platform events"
            description="Lead pipeline events across all partner platforms — status changes, validations, deliveries, and quarantine."
        >
            <template #actions>
                <AppButton :href="route('command-center.index')" variant="secondary">Command Center</AppButton>
                <AppButton :href="route('live-feed.index')" variant="secondary">Live feed</AppButton>
            </template>
        </PageHeader>

        <CompactStatStrip :items="eventStatStrip" :columns="5" class="mb-6" />

        <LogFilters
            class="mb-6"
            route-name="platform-events.index"
            :filters="filters"
            show-days
            show-date-range
            show-tenant
            show-level
            show-category
            show-event-type
            show-search
            :tenants="tenants"
            :level-options="levelOptions"
            :category-options="categoryOptions"
            :event-types="eventTypes"
            search-label="Search"
            search-placeholder="Message, event type, lead UUID…"
        />

        <Panel title="Event log" :padding="false">
            <DataTable :empty="!events?.data?.length" empty-message="No platform events match your filters.">
                <template #head>
                    <th class="w-8" />
                    <th class="text-left">When</th>
                    <th class="text-left">Tenant</th>
                    <th class="text-left">Level</th>
                    <th class="text-left">Event</th>
                    <th class="text-left">Message</th>
                    <th class="text-left">Campaign</th>
                    <th class="text-left">Lead</th>
                </template>
                <template v-for="event in events.data" :key="event.id">
                    <tr
                        class="cursor-pointer transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
                        @click="toggleRow(event.id)"
                    >
                        <td class="text-center text-slate-400">
                            <span class="inline-block text-xs transition" :class="expandedId === event.id ? 'rotate-90' : ''">▸</span>
                        </td>
                        <td class="whitespace-nowrap"><FormattedDate :value="event.created_at" /></td>
                        <td class="text-xs text-slate-600 dark:text-slate-400">{{ event.tenant ?? '—' }}</td>
                        <td>
                            <span :class="['rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase', levelBadgeClass(event.level)]">
                                {{ event.level ?? 'info' }}
                            </span>
                        </td>
                        <td>
                            <span :class="['rounded-md px-2 py-0.5 text-xs font-medium', eventTypeBadgeClass(event.event_type, event.level)]">
                                {{ event.event_type }}
                            </span>
                        </td>
                        <td class="max-w-sm text-xs text-slate-600 dark:text-slate-400">
                            <span class="line-clamp-2">{{ event.message ?? '—' }}</span>
                        </td>
                        <td class="text-xs text-slate-600 dark:text-slate-400">{{ event.campaign ?? '—' }}</td>
                        <td @click.stop>
                            <Link
                                v-if="event.lead_id"
                                :href="route('leads.show', event.lead_id)"
                                class="font-mono text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                :title="event.lead_uuid"
                            >
                                {{ event.lead_uuid?.slice(0, 12) }}…
                            </Link>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                    </tr>
                    <tr v-if="expandedId === event.id" class="bg-slate-50 dark:bg-slate-900/50">
                        <td colspan="8" class="!py-4">
                            <div class="grid gap-4 text-sm md:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase text-slate-500">Event</p>
                                    <p class="text-slate-700 dark:text-slate-300">{{ formatEventType(event.event_type) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase text-slate-500">Lead status</p>
                                    <p class="text-slate-700 dark:text-slate-300">{{ event.lead_status ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase text-slate-500">Lead UUID</p>
                                    <p class="font-mono text-xs text-slate-700 dark:text-slate-300">{{ event.lead_uuid ?? '—' }}</p>
                                </div>
                                <div v-if="event.lead_id" class="md:col-span-2 lg:col-span-3">
                                    <Link
                                        :href="route('leads.show', event.lead_id)"
                                        class="text-sm font-medium text-indigo-600 hover:underline"
                                    >
                                        View full lead journey →
                                    </Link>
                                </div>
                                <div v-if="hasPayload(event)" class="md:col-span-2 lg:col-span-3">
                                    <p class="mb-1 text-xs font-semibold uppercase text-slate-500">Payload</p>
                                    <pre class="max-h-64 overflow-auto rounded-lg bg-slate-900 p-3 text-xs text-emerald-300">{{ JSON.stringify(event.payload, null, 2) }}</pre>
                                </div>
                                <div v-else class="md:col-span-2 lg:col-span-3 text-xs text-slate-500">
                                    No additional payload for this event.
                                </div>
                            </div>
                        </td>
                    </tr>
                </template>
            </DataTable>
            <Pagination :links="events.links" />
            <p class="border-t border-slate-100 px-3 py-2 text-[10px] text-slate-500 dark:border-slate-800">
                Threshold alert history lives under
                <Link :href="route('automation.index', { tab: 'alerts' })" class="font-medium text-indigo-600 hover:underline">Automation → Event Alerts</Link>.
                Click a row to expand payload details.
            </p>
        </Panel>
    </AuthenticatedLayout>
</template>
