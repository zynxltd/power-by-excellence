<script setup>
import { computed, ref } from 'vue';
import { hasEntryFilters, routingModeLabel, ROUTING_MODE_STYLES } from '@/utils/routingModes';

const props = defineProps({
    groups: { type: Array, default: () => [] },
    collapsedLimit: { type: Number, default: 8 },
    compact: { type: Boolean, default: false },
});

const expanded = ref(false);

const tierCount = computed(() => props.groups?.length ?? 0);

const visibleGroups = computed(() => {
    const groups = props.groups ?? [];
    if (expanded.value || groups.length <= props.collapsedLimit) {
        return groups.map((group, index) => ({ group, index }));
    }
    return groups.slice(0, props.collapsedLimit).map((group, index) => ({ group, index }));
});

const hiddenCount = computed(() => Math.max(0, tierCount.value - props.collapsedLimit));

const assignedDeliveries = computed(() =>
    (props.groups ?? []).reduce((sum, group) => sum + (group.delivery_ids?.length ?? 0), 0),
);

const filteredTiers = computed(() =>
    (props.groups ?? []).filter((group) => hasEntryFilters(group.rules)).length,
);

const modeClass = (mode) => ROUTING_MODE_STYLES[mode] ?? ROUTING_MODE_STYLES.waterfall;
</script>

<template>
    <div v-if="tierCount" class="space-y-3">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
            <span><strong class="text-slate-700 dark:text-slate-300">{{ tierCount }}</strong> tiers</span>
            <span><strong class="text-slate-700 dark:text-slate-300">{{ assignedDeliveries }}</strong> delivery slots</span>
            <span v-if="filteredTiers"><strong class="text-slate-700 dark:text-slate-300">{{ filteredTiers }}</strong> with entry filters</span>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
            <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-slate-800">
                <thead>
                    <tr class="bg-slate-50/90 dark:bg-slate-800/60">
                        <th class="w-12 px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">#</th>
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Tier</th>
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Mode</th>
                        <th class="hidden px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 sm:table-cell">Deliveries</th>
                        <th class="hidden px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 md:table-cell">Floor</th>
                        <th class="px-3 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Filters</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                    <tr
                        v-for="{ group, index } in visibleGroups"
                        :key="index"
                        class="transition hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                    >
                        <td class="px-3 py-2.5 font-mono text-xs text-slate-400">{{ index + 1 }}</td>
                        <td class="px-3 py-2.5">
                            <p class="font-medium text-slate-900 dark:text-white" :class="compact ? 'text-xs' : 'text-sm'">
                                {{ group.name || `Tier ${index + 1}` }}
                            </p>
                        </td>
                        <td class="px-3 py-2.5">
                            <span
                                :class="[
                                    'inline-flex rounded-md px-2 py-0.5 text-[11px] font-semibold capitalize',
                                    modeClass(group.mode),
                                ]"
                            >
                                {{ routingModeLabel(group.mode) }}
                            </span>
                        </td>
                        <td class="hidden px-3 py-2.5 text-slate-600 dark:text-slate-400 sm:table-cell">
                            {{ group.delivery_ids?.length ?? 0 }}
                        </td>
                        <td class="hidden px-3 py-2.5 text-slate-600 dark:text-slate-400 md:table-cell">
                            <span v-if="group.floor_price != null && group.floor_price !== ''">{{ group.floor_price }}</span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-3 py-2.5 text-right">
                            <span
                                v-if="hasEntryFilters(group.rules)"
                                class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                            >
                                Yes
                            </span>
                            <span v-else class="text-xs text-slate-400">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <button
            v-if="hiddenCount > 0 && !expanded"
            type="button"
            class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
            @click="expanded = true"
        >
            Show all {{ tierCount }} tiers
        </button>
        <button
            v-else-if="tierCount > collapsedLimit && expanded"
            type="button"
            class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:hover:text-slate-300"
            @click="expanded = false"
        >
            Show fewer tiers
        </button>
    </div>
    <p v-else class="text-sm text-slate-500 dark:text-slate-400">No tiers configured yet.</p>
</template>
