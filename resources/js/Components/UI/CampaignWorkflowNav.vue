<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    campaign: { type: Object, required: true },
    current: { type: String, default: '' },
    distributionConfigId: { type: [Number, String], default: null },
});

const links = computed(() => [
    { key: 'show', label: 'Overview', href: route('campaigns.show', props.campaign.id) },
    { key: 'edit', label: 'Settings', href: route('campaigns.edit', props.campaign.id) },
    { key: 'api-spec', label: 'Lead ingest API', href: route('campaigns.api-spec', props.campaign.id), hint: 'Supplier → platform' },
    { key: 'deliveries', label: 'Buyer deliveries', href: route('deliveries.index', { campaign_id: props.campaign.id }), hint: 'Per-tier ping/post' },
    {
        key: 'ping-tree',
        label: 'Ping tree',
        href: props.distributionConfigId
            ? route('distribution.show', props.distributionConfigId)
            : route('distribution.create') + '?campaign_id=' + props.campaign.id,
        hint: 'Unlimited tiers',
    },
    { key: 'leads', label: 'Leads', href: route('leads.index', { campaign_id: props.campaign.id }) },
    { key: 'forms', label: 'Forms', href: route('forms.index') },
]);
</script>

<template>
    <div class="mb-6 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Campaign workflow</p>
            <span class="text-xs text-slate-500">{{ campaign.name }} · {{ campaign.reference }}</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <Link
                v-for="link in links"
                :key="link.key"
                :href="link.href"
                :title="link.hint"
                :class="[
                    'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                    current === link.key
                        ? 'bg-indigo-600 text-white'
                        : 'bg-slate-100 text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-indigo-950/40',
                ]"
            >
                {{ link.label }}
            </Link>
        </div>
        <p class="mt-2 text-xs text-slate-500">
            <strong>Lead ingest API</strong> is how suppliers submit leads.
            <strong>Buyer deliveries</strong> + <strong>Ping tree</strong> configure unlimited tiers — each tier’s ping/post URLs, pricing, and filters.
        </p>
    </div>
</template>
