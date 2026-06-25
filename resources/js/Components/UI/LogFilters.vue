<script setup>
import AppButton from '@/Components/UI/AppButton.vue';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    routeName: { type: String, required: true },
    filters: { type: Object, default: () => ({}) },
    showDateRange: { type: Boolean, default: true },
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
    deliveries: { type: Array, default: () => [] },
    buyers: { type: Array, default: () => [] },
    actionOptions: { type: Array, default: () => [] },
});

const local = ref({ ...props.filters });

watch(() => props.filters, (f) => {
    local.value = { ...f };
}, { deep: true });

const apply = () => {
    const params = Object.fromEntries(
        Object.entries(local.value).filter(([, v]) => v !== '' && v !== null && v !== undefined)
    );
    router.get(route(props.routeName), params, { preserveState: true, replace: true });
};

const clear = () => {
    local.value = {};
    router.get(route(props.routeName), {}, { preserveState: true, replace: true });
};

const setDays = (days) => {
    local.value.days = days;
    local.value.date_from = '';
    local.value.date_to = '';
    apply();
};
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/50">
        <div class="flex flex-wrap items-end gap-4">
            <div v-if="showDays" class="shrink-0">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Period</label>
                <div class="flex rounded-lg border border-slate-200 bg-white p-0.5 dark:border-slate-700 dark:bg-slate-800">
                    <button
                        v-for="d in [1, 7, 14, 28]"
                        :key="d"
                        type="button"
                        :class="[
                            'rounded-md px-2.5 py-1.5 text-xs font-semibold transition',
                            Number(local.days) === d && !local.date_from
                                ? 'bg-indigo-600 text-white'
                                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700',
                        ]"
                        @click="setDays(d)"
                    >
                        {{ d === 1 ? '24h' : d + 'd' }}
                    </button>
                </div>
            </div>

            <template v-if="showDateRange">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">From</label>
                    <input v-model="local.date_from" type="date" class="form-input w-full min-w-[9rem]" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">To</label>
                    <input v-model="local.date_to" type="date" class="form-input w-full min-w-[9rem]" />
                </div>
            </template>

            <div v-if="showStatus" class="min-w-[8rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Status</label>
                <select v-model="local.status" class="form-select w-full">
                    <option value="">All statuses</option>
                    <template v-if="statusOptions.length && typeof statusOptions[0] === 'object'">
                        <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </template>
                    <template v-else>
                        <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
                    </template>
                </select>
            </div>

            <div v-if="showMethod" class="min-w-[8rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Method</label>
                <select v-model="local.method" class="form-select w-full">
                    <option value="">All methods</option>
                    <option value="ping-post">Ping-post</option>
                    <option value="direct">Direct</option>
                </select>
            </div>

            <div v-if="showDelivery" class="min-w-[10rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Delivery</label>
                <select v-model="local.delivery_id" class="form-select w-full">
                    <option value="">All deliveries</option>
                    <option v-for="d in deliveries" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
            </div>

            <div v-if="showBuyer" class="min-w-[10rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Buyer</label>
                <select v-model="local.buyer_id" class="form-select w-full">
                    <option value="">All buyers</option>
                    <option v-for="b in buyers" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
            </div>

            <div v-if="showTier" class="min-w-[6rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Tier</label>
                <select v-model="local.tier" class="form-select w-full">
                    <option value="">All tiers</option>
                    <option v-for="t in 10" :key="t" :value="t">Tier {{ t }}</option>
                </select>
            </div>

            <div v-if="showSearch" class="min-w-[12rem] flex-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{{ showAction ? 'Search' : 'Lead UUID' }}</label>
                <input v-model="local.q" type="search" :placeholder="showAction ? 'Email, IP, path…' : 'Search UUID…'" class="form-input w-full" />
            </div>

            <div v-if="showPath" class="min-w-[12rem] flex-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">API path</label>
                <input v-model="local.path" type="search" placeholder="/api/v1/…" class="form-input w-full" />
            </div>

            <div v-if="showAction" class="min-w-[8rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Action</label>
                <select v-model="local.action" class="form-select w-full">
                    <option value="">All actions</option>
                    <option v-for="a in actionOptions" :key="a" :value="a">{{ a }}</option>
                </select>
            </div>

            <div class="flex gap-2">
                <AppButton type="button" @click="apply">Apply filters</AppButton>
                <button type="button" class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300" @click="clear">Clear</button>
            </div>
        </div>
    </div>
</template>
