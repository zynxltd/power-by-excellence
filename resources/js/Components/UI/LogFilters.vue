<script setup>
import AppButton from '@/Components/UI/AppButton.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    routeName: { type: String, required: true },
    filters: { type: Object, default: () => ({}) },
    showDateRange: { type: Boolean, default: true },
    showMonth: { type: Boolean, default: false },
    showDays: { type: Boolean, default: true },
    showStatus: { type: Boolean, default: false },
    statusOptions: { type: Array, default: () => [] },
    showMethod: { type: Boolean, default: false },
    showDelivery: { type: Boolean, default: false },
    showBuyer: { type: Boolean, default: false },
    showTier: { type: Boolean, default: false },
    showSearch: { type: Boolean, default: false },
    showPath: { type: Boolean, default: false },
    showAction: { type: Boolean, default: false },
    showLevel: { type: Boolean, default: false },
    showCategory: { type: Boolean, default: false },
    showEventType: { type: Boolean, default: false },
    showFeedType: { type: Boolean, default: false },
    showTenant: { type: Boolean, default: false },
    showCampaign: { type: Boolean, default: false },
    showCurrency: { type: Boolean, default: false },
    deliveries: { type: Array, default: () => [] },
    buyers: { type: Array, default: () => [] },
    campaigns: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
    actionOptions: { type: Array, default: () => [] },
    levelOptions: { type: Array, default: () => [] },
    categoryOptions: { type: Array, default: () => [] },
    eventTypes: { type: Array, default: () => [] },
    typeOptions: { type: Array, default: () => [] },
    tenants: { type: Array, default: () => [] },
    searchLabel: { type: String, default: '' },
    searchPlaceholder: { type: String, default: '' },
});

const searchLabel = computed(() => {
    if (props.searchLabel) return props.searchLabel;
    if (props.showAction) return 'Search';
    return 'Lead UUID';
});

const searchPlaceholder = computed(() => {
    if (props.searchPlaceholder) return props.searchPlaceholder;
    if (props.showAction) return 'Email, IP, path…';
    return 'Search UUID…';
});

const local = ref({ ...props.filters });

watch(() => props.filters, (f) => {
    local.value = { ...f };
}, { deep: true });

const buildParams = () => {
    const params = { ...local.value };

    if (params.date_from && params.date_to) {
        delete params.days;
        delete params.month;
    } else if (params.month) {
        delete params.days;
        delete params.date_from;
        delete params.date_to;
    } else if (params.days) {
        delete params.date_from;
        delete params.date_to;
        delete params.month;
    }

    return Object.fromEntries(
        Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined)
    );
};

const apply = () => {
    router.get(route(props.routeName), buildParams(), { preserveState: true, replace: true });
};

const clear = () => {
    local.value = {};
    router.get(route(props.routeName), {}, { preserveState: true, replace: true });
};

const setDays = (days) => {
    local.value.days = days;
    local.value.date_from = '';
    local.value.date_to = '';
    local.value.month = '';
    apply();
};

const setMonth = () => {
    if (!local.value.month) {
        return;
    }

    local.value.days = '';
    local.value.date_from = '';
    local.value.date_to = '';
    apply();
};

const usingPresetDays = computed(() => (
    Boolean(local.value.days)
    && !local.value.date_from
    && !local.value.date_to
    && !local.value.month
));

const fieldClass = 'h-11 py-0';
const selectClass = `form-select !mt-0 ${fieldClass}`;
const inputClass = `form-input ${fieldClass}`;
</script>

<template>
    <div class="rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-end gap-4">
            <div v-if="showDays" class="min-w-0 shrink-0">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Period</label>
                <div class="flex h-11 items-stretch overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <button
                        v-for="d in [1, 7, 14, 28, 30, 60, 90]"
                        :key="d"
                        type="button"
                        :class="[
                            'flex h-full min-w-[2.25rem] flex-1 items-center justify-center border-r border-slate-200 px-2 text-xs font-semibold transition last:border-r-0 sm:min-w-[2.5rem] sm:px-2.5 sm:text-sm dark:border-slate-700',
                            usingPresetDays && Number(local.days) === d
                                ? 'bg-indigo-600 text-white'
                                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700',
                        ]"
                        @click="setDays(d)"
                    >
                        {{ d === 1 ? '24h' : d + 'd' }}
                    </button>
                </div>
            </div>

            <div v-if="showMonth" class="shrink-0">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Monthly report</label>
                <input
                    v-model="local.month"
                    type="month"
                    :class="[inputClass, 'min-w-[9rem]']"
                    @change="setMonth"
                />
            </div>

            <template v-if="showDateRange">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">From</label>
                    <input v-model="local.date_from" type="date" :class="[inputClass, 'w-full min-w-[9rem]']" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">To</label>
                    <input v-model="local.date_to" type="date" :class="[inputClass, 'w-full min-w-[9rem]']" />
                </div>
            </template>

            <div v-if="showStatus" class="w-full min-w-0 sm:w-auto sm:min-w-[8rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Status</label>
                <select v-model="local.status" :class="[selectClass, 'w-full']">
                    <option value="">All statuses</option>
                    <template v-if="statusOptions.length && typeof statusOptions[0] === 'object'">
                        <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </template>
                    <template v-else>
                        <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
                    </template>
                </select>
            </div>

            <div v-if="showMethod" class="w-full min-w-0 sm:w-auto sm:min-w-[8rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Method</label>
                <select v-model="local.method" :class="[selectClass, 'w-full']">
                    <option value="">All methods</option>
                    <option value="ping-post">Ping-post</option>
                    <option value="direct">Direct</option>
                </select>
            </div>

            <div v-if="showDelivery" class="w-full min-w-0 sm:w-auto sm:min-w-[10rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Delivery</label>
                <select v-model="local.delivery_id" :class="[selectClass, 'w-full']">
                    <option value="">All deliveries</option>
                    <option v-for="d in deliveries" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
            </div>

            <div v-if="showBuyer" class="w-full min-w-0 sm:w-auto sm:min-w-[10rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Buyer</label>
                <select v-model="local.buyer_id" :class="[selectClass, 'w-full']">
                    <option value="">All buyers</option>
                    <option v-for="b in buyers" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
            </div>

            <div v-if="showTier" class="w-full min-w-0 sm:w-auto sm:min-w-[6rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Tier</label>
                <select v-model="local.tier" :class="[selectClass, 'w-full']">
                    <option value="">All tiers</option>
                    <option v-for="t in 10" :key="t" :value="t">Tier {{ t }}</option>
                </select>
            </div>

            <div v-if="showFeedType" class="w-full min-w-0 sm:w-auto sm:min-w-[10rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Type</label>
                <select v-model="local.type" :class="[selectClass, 'w-full']">
                    <option value="">All activity</option>
                    <option v-for="t in typeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
            </div>

            <div v-if="showTenant" class="w-full min-w-0 sm:w-auto sm:min-w-[10rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Tenant</label>
                <select v-model="local.account_id" :class="[selectClass, 'w-full']">
                    <option value="">All platforms</option>
                    <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
            </div>

            <div v-if="showLevel" class="w-full min-w-0 sm:w-auto sm:min-w-[8rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Level</label>
                <select v-model="local.level" :class="[selectClass, 'w-full']">
                    <option value="">All levels</option>
                    <option v-for="l in levelOptions" :key="l.value" :value="l.value">{{ l.label }}</option>
                </select>
            </div>

            <div v-if="showCategory" class="w-full min-w-0 sm:w-auto sm:min-w-[10rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Category</label>
                <select v-model="local.category" :class="[selectClass, 'w-full']">
                    <option value="">All categories</option>
                    <option v-for="c in categoryOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
                </select>
            </div>

            <div v-if="showEventType" class="w-full min-w-0 sm:w-auto sm:min-w-[12rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Event type</label>
                <select v-model="local.event_type" :class="[selectClass, 'w-full']">
                    <option value="">All event types</option>
                    <option v-for="t in eventTypes" :key="t" :value="t">{{ t }}</option>
                </select>
            </div>

            <div v-if="showSearch" class="min-w-[12rem] flex-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{{ searchLabel }}</label>
                <input v-model="local.q" type="search" :placeholder="searchPlaceholder" :class="[inputClass, 'w-full']" />
            </div>

            <div v-if="showPath" class="min-w-[12rem] flex-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">API path</label>
                <input v-model="local.path" type="search" placeholder="/api/v1/…" :class="[inputClass, 'w-full']" />
            </div>

            <div v-if="showAction" class="w-full min-w-0 sm:w-auto sm:min-w-[8rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Action</label>
                <select v-model="local.action" :class="[selectClass, 'w-full']">
                    <option value="">All actions</option>
                    <option v-for="a in actionOptions" :key="a" :value="a">{{ a }}</option>
                </select>
            </div>

            <div v-if="showCampaign" class="w-full min-w-0 sm:w-auto sm:min-w-[12rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Campaign</label>
                <select v-model="local.campaign_id" :class="[selectClass, 'w-full']">
                    <option value="">All campaigns</option>
                    <option v-for="c in campaigns" :key="c.id" :value="c.id">
                        {{ c.name }} ({{ c.currency }})
                    </option>
                </select>
            </div>

            <div v-if="showCurrency" class="w-full min-w-0 sm:w-auto sm:min-w-[8rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Currency</label>
                <select v-model="local.currency" :class="[selectClass, 'w-full']">
                    <option value="">All currencies</option>
                    <option v-for="c in currencies" :key="c" :value="c">{{ c }}</option>
                </select>
            </div>

            <div class="flex h-11 items-center gap-2">
                <AppButton type="button" class="h-11" @click="apply">Apply filters</AppButton>
                <button type="button" class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300" @click="clear">Clear</button>
            </div>
        </div>
    </div>
</template>
