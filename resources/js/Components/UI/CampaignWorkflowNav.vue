<script setup>
import NavIcon from '@/Components/UI/NavIcon.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    campaign: { type: Object, required: true },
    current: { type: String, default: '' },
    distributionConfigId: { type: [Number, String], default: null },
});

const links = computed(() => [
    { key: 'show', label: 'Overview', icon: 'home', href: route('campaigns.show', props.campaign.id) },
    { key: 'edit', label: 'Settings', icon: 'cog', href: route('campaigns.edit', props.campaign.id) },
    { key: 'api-spec', label: 'API spec', icon: 'code', href: route('campaigns.api-spec', props.campaign.id) },
    { key: 'leads', label: 'Leads', icon: 'users', href: route('leads.index', { campaign_id: props.campaign.id }) },
    {
        key: 'deliveries',
        label: 'Deliveries',
        icon: 'truck',
        href: route('deliveries.index', { campaign_id: props.campaign.id }),
    },
    {
        key: 'ping-tree',
        label: 'Ping tree',
        icon: 'tree',
        href: props.distributionConfigId
            ? route('distribution.show', props.distributionConfigId)
            : route('distribution.create') + '?campaign_id=' + props.campaign.id,
    },
    { key: 'operations', label: 'Live ops', icon: 'activity', href: route('operations.index', { campaign_id: props.campaign.id }) },
]);

const linkClass = (active) => [
    'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium transition sm:px-3 sm:py-1.5 sm:text-sm',
    active
        ? 'bg-indigo-600 text-white shadow-sm'
        : 'bg-white/80 text-slate-700 ring-1 ring-slate-200/80 hover:bg-indigo-600 hover:text-white hover:ring-indigo-600 dark:bg-slate-800/80 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-indigo-600 dark:hover:ring-indigo-600',
];
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-indigo-200/60 bg-gradient-to-r from-indigo-50/80 to-white dark:border-indigo-900/50 dark:from-indigo-950/40 dark:to-slate-900">
        <div class="p-2.5 sm:p-3">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-indigo-600 dark:text-indigo-400">Campaign</p>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ campaign.name }}</p>
                </div>
                <span class="font-mono text-xs text-slate-500">{{ campaign.reference }}</span>
            </div>
            <div class="flex flex-wrap gap-1">
                <Link
                    v-for="link in links"
                    :key="link.key"
                    :href="link.href"
                    :class="linkClass(current === link.key)"
                >
                    <NavIcon :name="link.icon" />
                    {{ link.label }}
                </Link>
            </div>
        </div>
    </div>
</template>
