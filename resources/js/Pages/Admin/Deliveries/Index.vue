<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import DeliveryMethodBadge from '@/Components/UI/DeliveryMethodBadge.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import ClickableTableRow from '@/Components/UI/ClickableTableRow.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    deliveries: Array,
    grouped: Array,
    stats: Object,
    filters: Object,
    filterOptions: Object,
    view: { type: String, default: 'cards' },
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

const methodValue = (d) => d.method?.value ?? d.method;

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
    if (confirm('Run a test delivery using the latest lead for this campaign?')) {
        router.post(route('deliveries.test', id));
    }
};

const statCards = computed(() => [
    { label: 'Total deliveries', value: props.stats?.total ?? 0 },
    { label: 'Active', value: props.stats?.active ?? 0, accent: 'text-emerald-600 dark:text-emerald-400' },
    { label: 'Healthy', value: props.stats?.healthy ?? 0, accent: 'text-emerald-600 dark:text-emerald-400' },
    { label: 'Warning', value: props.stats?.warning ?? 0, accent: 'text-amber-600 dark:text-amber-400' },
    { label: 'Critical', value: props.stats?.critical ?? 0, accent: 'text-rose-600 dark:text-rose-400' },
    ...Object.entries(props.stats?.by_method ?? {}).map(([method, count]) => ({
        label: methodLabels[method] ?? method,
        value: count,
    })),
]);

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

        <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
            <div
                v-for="card in statCards"
                :key="card.label"
                class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900"
            >
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ card.label }}</p>
                <p :class="['mt-1 text-2xl font-bold text-slate-900 dark:text-white', card.accent]">{{ card.value }}</p>
            </div>
        </div>

        <Panel title="Filters" class="mb-6">
            <div class="grid gap-4 md:grid-cols-6">
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
            <section v-for="group in grouped" :key="group.campaign">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">{{ group.campaign }}</h2>
                    <span class="text-xs text-slate-400">{{ group.items.length }} deliver{{ group.items.length === 1 ? 'y' : 'ies' }}</span>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="d in group.items"
                        :key="d.id"
                        class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:border-indigo-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-700"
                    >
                        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <Link
                                        :href="route('deliveries.show', d.id)"
                                        class="block truncate text-base font-semibold text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400"
                                    >
                                        {{ d.name }}
                                    </Link>
                                    <p class="mt-1 text-sm text-slate-500">{{ d.buyer?.name ?? 'No buyer linked' }}</p>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1.5">
                                    <StatusBadge :status="d.status" />
                                    <span
                                        :class="['rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide', healthStyles[d.health] ?? healthStyles.inactive]"
                                    >
                                        {{ d.health ?? 'inactive' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3 px-5 py-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <DeliveryMethodBadge :method="methodValue(d)" />
                                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium capitalize text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {{ d.revenue_type?.replace(/_/g, ' ') }}
                                    <span v-if="d.revenue_type === 'fixed'" class="text-emerald-600 dark:text-emerald-400">£{{ d.revenue_amount }}</span>
                                </span>
                            </div>

                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                <span>Priority {{ d.priority }}</span>
                                <span v-if="d.routing_mode" class="capitalize">{{ d.routing_mode.replace(/_/g, ' ') }}</span>
                                <span v-if="d.stats?.success_rate != null">{{ d.stats.success_rate }}% success (24h)</span>
                                <span v-if="d.logs_count">{{ d.logs_count }} runs</span>
                            </div>

                            <p class="text-xs text-slate-400">
                                Updated <FormattedDate :value="d.updated_at" format="relative" />
                            </p>
                        </div>

                        <div class="flex border-t border-slate-100 bg-slate-50/50 dark:border-slate-800 dark:bg-slate-800/30">
                            <button
                                type="button"
                                class="flex-1 px-4 py-2.5 text-center text-xs font-medium text-cyan-600 hover:bg-cyan-50 dark:text-cyan-400 dark:hover:bg-cyan-950/30"
                                @click="testDelivery(d.id)"
                            >
                                Test
                            </button>
                            <Link
                                :href="route('deliveries.show', d.id)"
                                class="flex-1 border-l border-slate-100 px-4 py-2.5 text-center text-xs font-medium text-indigo-600 hover:bg-indigo-50 dark:border-slate-800 dark:text-indigo-400 dark:hover:bg-indigo-950/30"
                            >
                                Details →
                            </Link>
                            <Link
                                :href="route('deliveries.edit', d.id)"
                                class="flex-1 border-l border-slate-100 px-4 py-2.5 text-center text-xs font-medium text-slate-600 hover:bg-slate-100 dark:border-slate-800 dark:text-slate-400 dark:hover:bg-slate-800"
                            >
                                Edit
                            </Link>
                        </div>
                    </article>
                </div>
            </section>

            <Panel v-if="!grouped?.length" title="No deliveries yet">
                <p class="text-sm text-slate-600 dark:text-slate-400">Create your first delivery to start routing leads to buyers.</p>
                <AppButton class="mt-4" :href="route('deliveries.create')">Create delivery</AppButton>
            </Panel>
        </div>

        <!-- Table view -->
        <Panel v-else :padding="false">
            <DataTable :empty="!deliveries?.length" empty-message="No deliveries match your filters.">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Buyer</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Method</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Health</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Updated</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                </template>
                <ClickableTableRow v-for="d in deliveries" :key="d.id" :href="route('deliveries.show', d.id)">
                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">{{ d.name }}</td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ d.campaign?.name }}</td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ d.buyer?.name ?? '—' }}</td>
                    <td class="px-6 py-4"><DeliveryMethodBadge :method="methodValue(d)" /></td>
                    <td class="px-6 py-4"><StatusBadge :status="d.status" /></td>
                    <td class="px-6 py-4">
                        <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold capitalize', healthStyles[d.health] ?? healthStyles.inactive]">
                            {{ d.health ?? 'inactive' }}
                        </span>
                    </td>
                    <td class="px-6 py-4"><FormattedDate :value="d.updated_at" format="relative" /></td>
                    <td class="px-6 py-4 text-right" @click.stop>
                        <Link :href="route('deliveries.edit', d.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Edit</Link>
                    </td>
                </ClickableTableRow>
            </DataTable>
        </Panel>
    </AuthenticatedLayout>
</template>
