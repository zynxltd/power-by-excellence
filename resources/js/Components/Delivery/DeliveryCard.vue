<script setup>
import DeliveryMethodBadge from '@/Components/UI/DeliveryMethodBadge.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    delivery: { type: Object, required: true },
    healthStyles: { type: Object, required: true },
    methodLabels: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['test']);

const { formatMoney } = useMoneyFormat();

const methodValue = (d) => d.method?.value ?? d.method;

const displayTitle = computed(() => {
    const name = props.delivery.name ?? 'Untitled delivery';
    const tier = props.delivery.tier;
    if (tier && !/^tier\s+\d+/i.test(name)) {
        return `Tier ${tier} – ${name}`;
    }

    return name;
});

const revenueLabel = computed(() => {
    const type = props.delivery.revenue_type?.replace(/_/g, ' ') ?? 'fixed';
    if (props.delivery.revenue_type === 'fixed') {
        return `${type} ${formatMoney(props.delivery.revenue_amount, { currency: props.delivery.campaign?.currency })}`;
    }

    return type;
});
</script>

<template>
    <article
        class="group relative flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:border-indigo-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-700"
    >
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <Link
                        :href="route('deliveries.show', delivery.id)"
                        class="block text-base font-semibold leading-snug text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400"
                    >
                        {{ displayTitle }}
                    </Link>
                    <p class="mt-1 truncate text-xs text-slate-500">{{ delivery.buyer?.name ?? 'No buyer linked' }}</p>
                </div>
                <div class="flex shrink-0 flex-col items-end gap-1.5">
                    <StatusBadge :status="delivery.status" />
                    <span
                        :class="[
                            'rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                            healthStyles[delivery.health] ?? healthStyles.inactive,
                        ]"
                    >
                        {{ delivery.health ?? 'inactive' }}
                    </span>
                </div>
            </div>
        </div>

        <div class="flex flex-1 flex-col space-y-3 px-5 py-4">
            <div class="flex flex-wrap items-center gap-2">
                <DeliveryMethodBadge :method="methodValue(delivery)" />
                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium capitalize text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    {{ revenueLabel }}
                </span>
            </div>

            <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                <span>Priority {{ delivery.priority ?? 0 }}</span>
                <span v-if="delivery.routing_mode" class="capitalize">{{ delivery.routing_mode.replace(/_/g, ' ') }}</span>
                <span v-if="delivery.stats?.success_rate != null">{{ delivery.stats.success_rate }}% success (24h)</span>
                <span v-if="delivery.logs_count">{{ delivery.logs_count }} runs</span>
            </div>

            <p class="mt-auto text-xs text-slate-400">
                Updated <FormattedDate :value="delivery.updated_at" format="relative" />
            </p>
        </div>

        <div class="flex border-t border-slate-100 bg-slate-50/50 dark:border-slate-800 dark:bg-slate-800/30">
            <button
                type="button"
                class="flex-1 px-4 py-2.5 text-center text-xs font-medium text-cyan-600 hover:bg-cyan-50 dark:text-cyan-400 dark:hover:bg-cyan-950/30"
                @click="emit('test', delivery.id)"
            >
                Test
            </button>
            <Link
                :href="route('deliveries.show', delivery.id)"
                class="flex-1 border-l border-slate-100 px-4 py-2.5 text-center text-xs font-medium text-indigo-600 hover:bg-indigo-50 dark:border-slate-800 dark:text-indigo-400 dark:hover:bg-indigo-950/30"
            >
                Details →
            </Link>
            <Link
                :href="route('deliveries.edit', delivery.id)"
                class="flex-1 border-l border-slate-100 px-4 py-2.5 text-center text-xs font-medium text-slate-600 hover:bg-slate-100 dark:border-slate-800 dark:text-slate-400 dark:hover:bg-slate-800"
            >
                Edit
            </Link>
        </div>
    </article>
</template>
