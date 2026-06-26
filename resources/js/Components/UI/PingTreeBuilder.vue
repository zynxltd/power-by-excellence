<script setup>
import DeliveryMethodBadge from '@/Components/UI/DeliveryMethodBadge.vue';
import EligibilityRulesEditor from '@/Components/UI/EligibilityRulesEditor.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { routingModeLabel, ROUTING_MODE_STYLES } from '@/utils/routingModes';
import { computed, ref } from 'vue';

const groups = defineModel('groups', { type: Array, required: true });

const props = defineProps({
    deliveries: { type: Array, default: () => [] },
    routingModes: { type: Array, default: () => [] },
    filterFieldOptions: { type: Array, default: () => [] },
    campaignName: { type: String, default: '' },
    deliveriesTeleport: { type: String, default: '' },
});

const expandedTier = ref(-1);
const selectedIds = ref([]);
const dragPayload = ref(null);
const dropTarget = ref(null);

const deliveryMap = computed(() =>
    Object.fromEntries((props.deliveries ?? []).map((d) => [d.id, d])),
);

const assignedIdSet = computed(() => {
    const ids = new Set();
    for (const group of groups.value ?? []) {
        for (const id of group.delivery_ids ?? []) {
            ids.add(id);
        }
    }
    return ids;
});

const unassignedDeliveries = computed(() =>
    (props.deliveries ?? []).filter((d) => !assignedIdSet.value.has(d.id)),
);

const deliveriesForTier = (group) =>
    (group.delivery_ids ?? [])
        .map((id) => deliveryMap.value[id])
        .filter(Boolean);

const modeClass = (mode) => ROUTING_MODE_STYLES[mode] ?? ROUTING_MODE_STYLES.waterfall;

const clearSelection = () => {
    selectedIds.value = [];
};

const toggleSelected = (id) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx >= 0) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
};

const removeFromAllTiers = (deliveryId) => {
    for (const group of groups.value) {
        const ids = group.delivery_ids ?? [];
        const idx = ids.indexOf(deliveryId);
        if (idx >= 0) {
            ids.splice(idx, 1);
        }
    }
};

const addToTier = (tierIndex, deliveryId, atIndex = null) => {
    removeFromAllTiers(deliveryId);
    const ids = groups.value[tierIndex].delivery_ids ?? [];
    if (atIndex === null || atIndex >= ids.length) {
        ids.push(deliveryId);
    } else {
        ids.splice(atIndex, 0, deliveryId);
    }
    groups.value[tierIndex].delivery_ids = ids;
};

const addSelectedToTier = (tierIndex) => {
    for (const id of [...selectedIds.value]) {
        addToTier(tierIndex, id);
    }
    clearSelection();
};

const removeFromTier = (tierIndex, deliveryId) => {
    const ids = groups.value[tierIndex].delivery_ids ?? [];
    const idx = ids.indexOf(deliveryId);
    if (idx >= 0) {
        ids.splice(idx, 1);
    }
};

const moveTier = (fromIndex, toIndex) => {
    if (fromIndex === toIndex || toIndex < 0 || toIndex >= groups.value.length) {
        return;
    }
    const next = [...groups.value];
    const [tier] = next.splice(fromIndex, 1);
    next.splice(toIndex, 0, tier);
    groups.value = next;
    expandedTier.value = toIndex;
};

const addTier = () => {
    groups.value.push({
        name: `Tier ${groups.value.length + 1}`,
        mode: 'waterfall',
        floor_price: null,
        redirect_url: null,
        delivery_ids: [],
        rules: { operator: 'and', conditions: [] },
    });
    expandedTier.value = groups.value.length - 1;
};

const removeTier = (index) => {
    if (groups.value.length <= 1) {
        return;
    }
    groups.value.splice(index, 1);
    expandedTier.value = Math.min(expandedTier.value, groups.value.length - 1);
};

const onDragStart = (event, payload) => {
    dragPayload.value = payload;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', JSON.stringify(payload));
};

const onDragEnd = () => {
    dragPayload.value = null;
    dropTarget.value = null;
};

const onDragOver = (event, target) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    dropTarget.value = target;
};

const onDragLeave = (target) => {
    if (dropTarget.value?.key === target.key) {
        dropTarget.value = null;
    }
};

const onDrop = (event, target) => {
    event.preventDefault();
    dropTarget.value = null;

    let payload = dragPayload.value;
    if (!payload) {
        try {
            payload = JSON.parse(event.dataTransfer.getData('text/plain'));
        } catch {
            return;
        }
    }

    if (payload.type === 'delivery') {
        const { deliveryId, fromTier, fromIndex } = payload;

        if (target.type === 'tier' && target.tierIndex === -1) {
            removeFromAllTiers(deliveryId);
            dragPayload.value = null;
            return;
        }

        removeFromAllTiers(deliveryId);

        if (target.type === 'tier' && target.tierIndex >= 0) {
            const ids = groups.value[target.tierIndex].delivery_ids ?? [];
            let insertAt = target.atIndex ?? ids.length;
            if (fromTier === target.tierIndex && fromIndex !== null && fromIndex < insertAt) {
                insertAt -= 1;
            }
            ids.splice(insertAt, 0, deliveryId);
            groups.value[target.tierIndex].delivery_ids = ids;
        }
    } else if (payload.type === 'tier' && target.type === 'tier-reorder') {
        moveTier(payload.tierIndex, target.tierIndex);
    }

    dragPayload.value = null;
};

const isDropActive = (target) => {
    const active = dropTarget.value;
    if (!active) {
        return false;
    }
    return active.type === target.type
        && active.tierIndex === target.tierIndex
        && (active.atIndex ?? -1) === (target.atIndex ?? -1);
};

const dropZoneClass = (target) => [
    'rounded-lg border-2 border-dashed transition',
    isDropActive(target)
        ? 'border-indigo-400 bg-indigo-50/80 dark:border-indigo-500 dark:bg-indigo-950/30'
        : 'border-slate-200 dark:border-slate-700',
];
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                Drag deliveries into tiers to build your ping tree. Order within a tier sets waterfall / sequential priority.
            </p>
            <button
                type="button"
                class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 lg:hidden dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                @click="addTier"
            >
                + Add tier
            </button>
        </div>

        <div v-if="!deliveries.length" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
            No deliveries for this campaign. Create deliveries first, then assign them to tiers.
        </div>

        <template v-else>
            <Teleport v-if="deliveriesTeleport" :to="deliveriesTeleport">
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Deliveries</p>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                            @click="addTier"
                        >
                            + Tier
                        </button>
                    </div>
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                        :class="dropZoneClass({ type: 'tier', tierIndex: -1 })"
                        @dragover="onDragOver($event, { type: 'tier', tierIndex: -1 })"
                        @dragleave="onDragLeave({ type: 'tier', tierIndex: -1 })"
                        @drop="onDrop($event, { type: 'tier', tierIndex: -1 })"
                    >
                        <div class="mb-3 space-y-2">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Unassigned</p>
                                <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">
                                    {{ unassignedDeliveries.length }} available · drag into a tier
                                </p>
                            </div>
                            <div v-if="selectedIds.length" class="space-y-2">
                                <span class="text-xs text-slate-500">{{ selectedIds.length }} selected</span>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="(group, ti) in groups"
                                        :key="`bulk-${ti}`"
                                        type="button"
                                        class="rounded-md bg-indigo-600 px-2 py-1 text-xs font-semibold text-white hover:bg-indigo-500"
                                        @click="addSelectedToTier(ti)"
                                    >
                                        → {{ group.name || `Tier ${ti + 1}` }}
                                    </button>
                                    <button type="button" class="text-xs text-slate-500 hover:text-slate-700" @click="clearSelection">
                                        Clear
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="unassignedDeliveries.length"
                            class="flex flex-col gap-2"
                        >
                            <div
                                v-for="delivery in unassignedDeliveries"
                                :key="delivery.id"
                                draggable="true"
                                class="flex cursor-grab items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 active:cursor-grabbing dark:border-slate-700 dark:bg-slate-800/60"
                                @dragstart="onDragStart($event, { type: 'delivery', deliveryId: delivery.id, fromTier: null, fromIndex: null })"
                                @dragend="onDragEnd"
                            >
                                <input
                                    type="checkbox"
                                    class="shrink-0 rounded border-slate-300 text-indigo-600"
                                    :checked="selectedIds.includes(delivery.id)"
                                    @click.stop
                                    @change="toggleSelected(delivery.id)"
                                />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ delivery.name }}</p>
                                    <DeliveryMethodBadge v-if="delivery.method" :method="delivery.method" />
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-slate-500">All deliveries are assigned to tiers.</p>
                    </div>
                </div>
            </Teleport>

            <div v-else class="mb-6 space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Deliveries</p>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        @click="addTier"
                    >
                        + Tier
                    </button>
                </div>
                <div
                    class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                    :class="dropZoneClass({ type: 'tier', tierIndex: -1 })"
                    @dragover="onDragOver($event, { type: 'tier', tierIndex: -1 })"
                    @dragleave="onDragLeave({ type: 'tier', tierIndex: -1 })"
                    @drop="onDrop($event, { type: 'tier', tierIndex: -1 })"
                >
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Unassigned</p>
                    <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">{{ unassignedDeliveries.length }} available</p>
                    <div v-if="unassignedDeliveries.length" class="mt-3 flex flex-col gap-2">
                        <div
                            v-for="delivery in unassignedDeliveries"
                            :key="delivery.id"
                            draggable="true"
                            class="flex cursor-grab items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-800/60"
                            @dragstart="onDragStart($event, { type: 'delivery', deliveryId: delivery.id, fromTier: null, fromIndex: null })"
                            @dragend="onDragEnd"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ delivery.name }}</p>
                                <DeliveryMethodBadge v-if="delivery.method" :method="delivery.method" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Visual flow — full width, scrolls with page -->
            <div class="relative mx-auto w-full max-w-3xl py-2">
                <div class="flex flex-col items-center">
                    <div class="rounded-xl border-2 border-indigo-300 bg-indigo-50 px-6 py-3 text-center dark:border-indigo-700 dark:bg-indigo-950/40">
                        <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Lead arrives</p>
                        <p v-if="campaignName" class="mt-1 text-sm font-medium text-slate-900 dark:text-white">{{ campaignName }}</p>
                    </div>
                    <div class="my-2 h-8 w-0.5 bg-slate-300 dark:bg-slate-600" />
                </div>

                <div
                    v-for="(group, tierIndex) in groups"
                    :key="tierIndex"
                    class="flex flex-col items-center"
                >
                    <div
                        class="w-full max-w-2xl rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
                        :class="expandedTier === tierIndex ? 'ring-2 ring-indigo-400/60' : ''"
                    >
                        <div
                            class="flex cursor-grab items-start justify-between gap-3 border-b border-slate-100 px-4 py-3 active:cursor-grabbing dark:border-slate-800"
                            draggable="true"
                            @dragstart="onDragStart($event, { type: 'tier', tierIndex })"
                            @dragend="onDragEnd"
                            @dragover="onDragOver($event, { type: 'tier-reorder', tierIndex })"
                            @dragleave="onDragLeave({ type: 'tier-reorder', tierIndex })"
                            @drop="onDrop($event, { type: 'tier-reorder', tierIndex })"
                            @click="expandedTier = expandedTier === tierIndex ? -1 : tierIndex"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-slate-400" title="Drag to reorder tier">⠿</span>
                                    <span class="rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-bold text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">
                                        Tier {{ tierIndex + 1 }}
                                    </span>
                                    <span
                                        :class="[
                                            'inline-flex rounded-md px-2 py-0.5 text-[11px] font-semibold capitalize',
                                            modeClass(group.mode),
                                        ]"
                                    >
                                        {{ routingModeLabel(group.mode) }}
                                    </span>
                                </div>
                                <h3 class="mt-1 text-base font-semibold text-slate-900 dark:text-white">
                                    {{ group.name || `Tier ${tierIndex + 1}` }}
                                </h3>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ deliveriesForTier(group).length }} deliver{{ deliveriesForTier(group).length === 1 ? 'y' : 'ies' }}
                                    <span v-if="group.mode === 'parallel_auction' && group.floor_price"> · floor {{ group.floor_price }}</span>
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <button
                                    v-if="groups.length > 1"
                                    type="button"
                                    class="text-xs text-rose-500 hover:text-rose-400"
                                    @click.stop="removeTier(tierIndex)"
                                >
                                    Remove
                                </button>
                                <span class="text-xs text-indigo-600 dark:text-indigo-400">
                                    {{ expandedTier === tierIndex ? '▲ Settings' : '▼ Settings' }}
                                </span>
                            </div>
                        </div>

                        <div
                            class="space-y-2 p-4"
                            @dragover="onDragOver($event, { type: 'tier', tierIndex })"
                            @dragleave="onDragLeave({ type: 'tier', tierIndex })"
                            @drop="onDrop($event, { type: 'tier', tierIndex })"
                        >
                            <template v-for="(delivery, di) in deliveriesForTier(group)" :key="delivery.id">
                                <div
                                    :class="dropZoneClass({ type: 'tier', tierIndex, atIndex: di })"
                                    class="h-2"
                                    @dragover="onDragOver($event, { type: 'tier', tierIndex, atIndex: di })"
                                    @dragleave="onDragLeave({ type: 'tier', tierIndex, atIndex: di })"
                                    @drop="onDrop($event, { type: 'tier', tierIndex, atIndex: di })"
                                />
                                <div
                                    draggable="true"
                                    class="flex cursor-grab items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 active:cursor-grabbing dark:border-slate-700 dark:bg-slate-800/50"
                                    @dragstart="onDragStart($event, { type: 'delivery', deliveryId: delivery.id, fromTier: tierIndex, fromIndex: di })"
                                    @dragend="onDragEnd"
                                >
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-200 text-xs font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                            {{ di + 1 }}
                                        </span>
                                        <div>
                                            <p class="font-medium text-slate-900 dark:text-white">{{ delivery.name }}</p>
                                            <p v-if="delivery.buyer" class="text-xs text-slate-500">{{ delivery.buyer }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <DeliveryMethodBadge v-if="delivery.method" :method="delivery.method" />
                                        <button
                                            type="button"
                                            class="text-xs text-slate-400 hover:text-rose-500"
                                            title="Remove from tier"
                                            @click="removeFromTier(tierIndex, delivery.id)"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <div
                                :class="[
                                    dropZoneClass({ type: 'tier', tierIndex, atIndex: deliveriesForTier(group).length }),
                                    deliveriesForTier(group).length ? 'py-3' : 'py-8',
                                ]"
                                @dragover="onDragOver($event, { type: 'tier', tierIndex, atIndex: deliveriesForTier(group).length })"
                                @dragleave="onDragLeave({ type: 'tier', tierIndex, atIndex: deliveriesForTier(group).length })"
                                @drop="onDrop($event, { type: 'tier', tierIndex, atIndex: deliveriesForTier(group).length })"
                            >
                                <p class="text-center text-xs text-slate-400">
                                    {{ deliveriesForTier(group).length ? 'Drop here to append' : 'Drop deliveries here' }}
                                </p>
                            </div>

                            <p
                                v-if="!(group.delivery_ids?.length)"
                                class="text-center text-xs font-medium text-amber-600 dark:text-amber-400"
                            >
                                At least one delivery required before save
                            </p>
                        </div>

                        <div
                            v-show="expandedTier === tierIndex"
                            class="space-y-4 border-t border-slate-100 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/50"
                            @click.stop
                        >
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <InputLabel value="Tier name" />
                                    <TextInput v-model="group.name" class="mt-1 w-full" required />
                                </div>
                                <div>
                                    <InputLabel value="Routing mode" />
                                    <select v-model="group.mode" class="form-select mt-1 w-full">
                                        <option v-for="m in routingModes" :key="m.value" :value="m.value">{{ m.label }}</option>
                                    </select>
                                </div>
                            </div>
                            <div v-if="group.mode === 'parallel_auction'" class="max-w-xs">
                                <InputLabel value="Floor price" />
                                <TextInput v-model="group.floor_price" type="number" step="0.01" min="0" class="mt-1 w-full" />
                            </div>
                            <div>
                                <InputLabel value="Redirect URL" />
                                <TextInput
                                    v-model="group.redirect_url"
                                    type="url"
                                    class="mt-1 w-full"
                                    placeholder="https://yoursite.com/thank-you"
                                />
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Returned in the API when a lead sells via this tier. Overrides the delivery redirect when set.
                                </p>
                            </div>
                            <div>
                                <InputLabel value="Tier entry filters" />
                                <div class="mt-2">
                                    <EligibilityRulesEditor
                                        v-model="group.rules"
                                        scope="tier"
                                        compact
                                        :field-options="filterFieldOptions"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="tierIndex < groups.length - 1" class="my-2 flex flex-col items-center">
                        <div class="h-6 w-0.5 bg-slate-300 dark:bg-slate-600" />
                        <span class="my-1 rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                            fallback
                        </span>
                        <div class="h-6 w-0.5 bg-slate-300 dark:bg-slate-600" />
                    </div>
                </div>

                <div v-if="groups.length" class="mt-2 flex flex-col items-center">
                    <div class="h-8 w-0.5 bg-slate-300 dark:bg-slate-600" />
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-6 py-3 text-center dark:border-slate-700 dark:bg-slate-800/50">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">No tier accepts</p>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Lead marked unsold</p>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
