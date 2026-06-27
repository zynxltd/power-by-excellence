<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    tab: { type: String, default: 'access' },
    tabs: { type: Array, default: () => [] },
});

const leadUuid = ref('');

const activeTab = computed(() => props.tabs.find((t) => t.key === props.tab) ?? props.tabs[0]);

const tabClass = (key) => [
    'rounded-lg px-3 py-2 text-sm font-medium transition',
    props.tab === key
        ? 'bg-indigo-600 text-white'
        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
];

const searchLogs = () => {
    const routeName = activeTab.value?.route ?? 'logs.access';
    const params = leadUuid.value.trim() ? { q: leadUuid.value.trim() } : {};
    router.get(route(routeName), params);
};

const openTab = (t) => {
    router.get(route('logs.hub', { tab: t.key }));
};
</script>

<template>
    <Head title="Logs hub" />
    <AuthenticatedLayout>
        <PageHeader title="Logs hub" description="Search across delivery, API, access, change, and security logs." />

        <Panel class="mb-6">
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Lead UUID search</label>
            <div class="mt-2 flex flex-wrap gap-2">
                <input
                    v-model="leadUuid"
                    type="search"
                    placeholder="Search by lead UUID…"
                    class="form-input min-w-[16rem] flex-1"
                    @keyup.enter="searchLogs"
                />
                <AppButton @click="searchLogs">Search in {{ activeTab?.label ?? 'logs' }}</AppButton>
            </div>
            <p class="mt-2 text-xs text-slate-500">Opens the active tab with your UUID filter applied.</p>
        </Panel>

        <nav class="mb-6 flex flex-wrap gap-1 border-b border-slate-200 pb-3 dark:border-slate-700">
            <button
                v-for="t in tabs"
                :key="t.key"
                type="button"
                :class="tabClass(t.key)"
                @click="openTab(t)"
            >
                {{ t.label }}
            </button>
        </nav>

        <Panel :title="`${activeTab?.label ?? 'Logs'} logs`">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                View full {{ activeTab?.label?.toLowerCase() }} log entries, filters, and export options on the dedicated page.
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <AppButton v-if="activeTab?.route" :href="route(activeTab.route)">
                    Open {{ activeTab.label }} logs →
                </AppButton>
                <Link :href="route('leads.index')" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                    Lead pipeline
                </Link>
            </div>
        </Panel>
    </AuthenticatedLayout>
</template>
