<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import DeliveryMethodBadge from '@/Components/UI/DeliveryMethodBadge.vue';
import DeliveryCard from '@/Components/Delivery/DeliveryCard.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import ClickableTableRow from '@/Components/UI/ClickableTableRow.vue';
import CampaignWorkflowNav from '@/Components/UI/CampaignWorkflowNav.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    deliveries: Object,
    grouped: Array,
    stats: Object,
    filters: Object,
    filterOptions: Object,
    view: { type: String, default: 'cards' },
    showPlatformColumn: { type: Boolean, default: false },
    campaignWorkflow: { type: Object, default: null },
});

const localFilters = ref({ ...props.filters, view: props.view });

const methodLabels = {
    direct_post: 'Direct API',
    ping_post: 'Ping Post',
    email_ping_post: 'Email Ping-Post',
    store_lead: 'Store',
    email: 'Email',
    sms: 'SMS',
};

const healthStyles = {
    healthy: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    warning: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    critical: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
    inactive: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
};

const healthLabel = (d) => {
    if (d.health_reason) {
        const platform = d.platform_name ? `${d.platform_name}: ` : '';
        return `${platform}${d.health_reason}`;
    }

    return d.health ?? 'inactive';
};

const methodValue = (d) => d.method?.value ?? d.method;

const deliveryRows = computed(() => props.deliveries?.data ?? []);

const applyFilters = () => {
    router.get(route('deliveries.index'), localFilters.value, { preserveState: true, replace: true });
};

const setView = (view) => {
    localFilters.value.view = view;
    applyFilters();
};

const clearFilters = () => {
    localFilters.value = { view: props.view };
    applyFilters();
};

const testDelivery = (id) => {
    if (confirm('Run a live test against this buyer endpoint using the latest lead for this campaign?\n\nResults appear on the delivery Logs tab (not the Leads list).')) {
        router.post(route('deliveries.test', id));
    }
};

const statCards = computed(() => [
    { label: 'Total', value: props.stats?.total ?? 0 },
    { label: 'Active', value: props.stats?.active ?? 0, accent: 'emerald' },
    { label: 'Healthy', value: props.stats?.healthy ?? 0, accent: 'emerald' },
    { label: 'Warning', value: props.stats?.warning ?? 0, accent: 'amber' },
    { label: 'Critical', value: props.stats?.critical ?? 0, accent: 'rose' },
    ...Object.entries(props.stats?.by_method ?? {}).map(([method, count]) => ({
        label: methodLabels[method] ?? method,
        value: count,
        accent: 'indigo',
    })),
]);

const pageSummary = computed(() => {
    const total = props.deliveries?.total ?? 0;
    const from = props.deliveries?.from ?? 0;
    const to = props.deliveries?.to ?? 0;

    if (!total) {
        return '0 deliveries';
    }

    if (total <= (props.deliveries?.per_page ?? 24)) {
        return `${total} deliver${total === 1 ? 'y' : 'ies'}`;
    }

    return `Showing ${from}–${to} of ${total} deliveries`;
});

watch(() => props.filters, (f) => {
    localFilters.value = { ...f, view: props.view };
});
watch(() => props.view, (v) => {
    localFilters.value.view = v;
});
</script>

<template>
    <Head title="Deliveries" />
    <AuthenticatedLayout>
        <PageHeader title="Deliveries" description="Configure how leads are delivered to buyers — grouped by campaign for quick scanning.">
            <template #actions>
                <AppButton :href="route('deliveries.create')">New Delivery</AppButton>
            </template>
        </PageHeader>

        <CampaignWorkflowNav
            v-if="campaignWorkflow"
            :campaign="campaignWorkflow.campaign"
            :distribution-config-id="campaignWorkflow.distributionConfigId"
            current="deliveries"
            class="mb-6"
        />

        <CompactStatStrip :items="statCards" class="mb-6" />

        <Panel title="Filters" class="mb-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Vertical</label>
                    <select v-model="localFilters.vertical_id" class="form-select">
                        <option value="">All verticals</option>
                        <option v-for="v in filterOptions?.verticals" :key="v.id" :value="v.id">{{ v.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Campaign</label>
                    <select v-model="localFilters.campaign_id" class="form-select">
                        <option value="">All campaigns</option>
                        <option v-for="c in filterOptions?.campaigns" :key="c.id" :value="c.id">{{ c.vertical_label }} — {{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Method</label>
                    <select v-model="localFilters.method" class="form-select">
                        <option value="">All methods</option>
                        <option v-for="m in filterOptions?.methods" :key="m" :value="m">{{ methodLabels[m] ?? m }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Status</label>
                    <select v-model="localFilters.status" class="form-select">
                        <option value="">All statuses</option>
                        <option v-for="s in filterOptions?.statuses" :key="s" :value="s">{{ s }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Buyer</label>
                    <select v-model="localFilters.buyer_id" class="form-select">
                        <option value="">All buyers</option>
                        <option v-for="b in filterOptions?.buyers" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Search</label>
                    <input v-model="localFilters.search" type="text" class="form-input" placeholder="Delivery name" @keyup.enter="applyFilters" />
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <AppButton @click="applyFilters">Apply filters</AppButton>
                <AppButton variant="secondary" @click="clearFilters">Clear</AppButton>
                <div class="ml-auto flex rounded-lg border border-slate-200 p-0.5 dark:border-slate-700">
                    <button
                        type="button"
                        :class="[
                            'rounded-md px-3 py-1.5 text-xs font-semibold transition',
                            view === 'cards'
                                ? 'bg-indigo-600 text-white'
                                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
                        ]"
                        @click="setView('cards')"
                    >
                        Cards
                    </button>
                    <button
                        type="button"
                        :class="[
                            'rounded-md px-3 py-1.5 text-xs font-semibold transition',
                            view === 'table'
                                ? 'bg-indigo-600 text-white'
                                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
                        ]"
                        @click="setView('table')"
                    >
                        Table
                    </button>
                </div>
            </div>
        </Panel>

        <!-- Cards view -->
        <div v-if="view === 'cards'" class="space-y-8">
            <div class="flex items-center justify-end">
                <span class="text-xs text-slate-500">{{ pageSummary }}</span>
            </div>

            <section v-for="group in grouped" :key="group.campaign">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">{{ group.campaign }}</h2>
                    <span class="text-xs text-slate-400">{{ group.items.length }} on this page</span>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <DeliveryCard
                        v-for="d in group.items"
                        :key="d.id"
                        :delivery="d"
                        :health-styles="healthStyles"
                        :method-labels="methodLabels"
                        :show-platform-in-health="showPlatformColumn"
                        @test="testDelivery"
                    />
                </div>
            </section>

            <Panel v-if="!grouped?.length" title="No deliveries yet">
                <p class="text-xs text-slate-600 dark:text-slate-400">Create your first delivery to start routing leads to buyers.</p>
                <AppButton class="mt-4" :href="route('deliveries.create')">Create delivery</AppButton>
            </Panel>

            <Panel v-else :padding="false">
                <Pagination :links="deliveries?.links ?? []" />
            </Panel>
        </div>

        <!-- Table view -->
        <Panel v-else :padding="false">
            <template #header>
                <span class="text-xs text-slate-500">{{ pageSummary }}</span>
            </template>
            <DataTable :empty="!deliveryRows.length" empty-message="No deliveries match your filters.">
                <template #head>
                    <th class="text-left">Name</th>
                    <th v-if="showPlatformColumn" class="text-left">Platform</th>
                    <th class="text-left">Campaign</th>
                    <th class="text-left">Buyer</th>
                    <th class="text-left">Method</th>
                    <th class="text-left">Status</th>
                    <th class="text-left">Health</th>
                    <th class="text-left">Success (24h)</th>
                    <th class="text-left">Updated</th>
                    <th class="text-right">Actions</th>
                </template>
                <ClickableTableRow v-for="d in deliveryRows" :key="d.id" :href="route('deliveries.show', d.id)">
                    <td class="font-medium text-slate-900 dark:text-white">{{ d.name }}</td>
                    <td v-if="showPlatformColumn" class="text-xs text-slate-600 dark:text-slate-400">
                        {{ d.platform_name ?? '—' }}
                    </td>
                    <td class="text-xs text-slate-600 dark:text-slate-400">{{ d.campaign?.name }}</td>
                    <td class="text-xs text-slate-600 dark:text-slate-400">{{ d.buyer?.name ?? '—' }}</td>
                    <td class=""><DeliveryMethodBadge :method="methodValue(d)" /></td>
                    <td class=""><StatusBadge :status="d.status" /></td>
                    <td class="max-w-xs">
                        <span :class="['inline-block rounded-full px-2 py-0.5 text-xs font-semibold capitalize', healthStyles[d.health] ?? healthStyles.inactive]">
                            {{ d.health ?? 'inactive' }}
                        </span>
                        <p v-if="d.health_reason" class="mt-1 text-xs leading-snug text-amber-800 dark:text-amber-300" :title="healthLabel(d)">
                            {{ d.health_reason }}
                        </p>
                    </td>
                    <td class="text-xs text-slate-600 dark:text-slate-400">
                        {{ d.stats?.success_rate != null ? `${d.stats.success_rate}%` : '—' }}
                    </td>
                    <td class=""><FormattedDate :value="d.updated_at" format="relative" /></td>
                    <td class="text-right" @click.stop>
                        <Link :href="route('deliveries.edit', d.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Edit</Link>
                    </td>
                </ClickableTableRow>
            </DataTable>
            <Pagination :links="deliveries?.links ?? []" />
        </Panel>
    </AuthenticatedLayout>
</template>
