<script setup>
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import DeliveryMethodBadge from '@/Components/UI/DeliveryMethodBadge.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    log: { type: Object, required: true },
    defaultExpanded: { type: Boolean, default: false },
    formatMoney: { type: Function, default: null },
});

const expanded = ref(props.defaultExpanded);

const method = computed(() => props.log.delivery?.method?.value ?? props.log.delivery?.method ?? 'direct_post');
const isPingPost = computed(() => method.value === 'ping_post');

const hasPayload = computed(() => !!(
    props.log.ping_request
    || props.log.ping_response
    || props.log.post_request
    || props.log.post_response
));

const formatJson = (data) => {
    if (!data) return null;
    try {
        return JSON.stringify(data, null, 2);
    } catch {
        return String(data);
    }
};

const revenueLabel = computed(() => {
    if (!props.log.revenue) return null;
    return props.formatMoney ? props.formatMoney(props.log.revenue) : props.log.revenue;
});
</script>

<template>
    <article class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
        <button
            type="button"
            class="flex w-full flex-wrap items-center justify-between gap-3 bg-white px-4 py-3 text-left transition hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800/80"
            @click="expanded = !expanded"
        >
            <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                <span class="text-slate-400">{{ expanded ? '▼' : '▶' }}</span>
                <span class="font-medium text-slate-900 dark:text-white">{{ log.delivery?.name ?? 'Delivery' }}</span>
                <span v-if="log.buyer?.name" class="text-sm text-slate-500">· {{ log.buyer.name }}</span>
                <span v-if="log.delivery?.tier != null" class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                    Tier {{ log.delivery.tier }}
                </span>
                <DeliveryMethodBadge :method="method" />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <StatusBadge :status="log.status" />
                <span v-if="log.http_status" class="text-xs font-mono text-slate-500">HTTP {{ log.http_status }}</span>
                <span v-if="log.duration_ms" class="text-xs text-slate-500">{{ log.duration_ms }}ms</span>
                <span v-if="revenueLabel" class="text-sm font-medium text-emerald-600">{{ revenueLabel }}</span>
                <FormattedDate :value="log.created_at" class="text-xs text-slate-500" />
            </div>
        </button>

        <div v-if="log.skipped_reason" class="border-t border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
            Skipped: {{ log.skipped_reason }}
        </div>

        <div v-show="expanded" class="border-t border-slate-200 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-950/40">
            <div v-if="!hasPayload" class="text-sm text-slate-500">
                No request/response payload recorded for this attempt.
            </div>

            <div v-else class="space-y-4">
                <div v-if="isPingPost" class="grid gap-4 lg:grid-cols-2">
                    <div v-if="log.ping_request">
                        <p class="mb-1 text-xs font-semibold uppercase text-cyan-600 dark:text-cyan-400">Ping request</p>
                        <pre class="max-h-64 overflow-auto rounded-lg bg-slate-900 p-3 text-xs leading-relaxed text-cyan-200">{{ formatJson(log.ping_request) }}</pre>
                    </div>
                    <div v-if="log.ping_response">
                        <p class="mb-1 text-xs font-semibold uppercase text-emerald-600 dark:text-emerald-400">Ping response</p>
                        <pre class="max-h-64 overflow-auto rounded-lg bg-slate-900 p-3 text-xs leading-relaxed text-emerald-200">{{ formatJson(log.ping_response) }}</pre>
                    </div>
                    <div v-if="!log.ping_request && !log.ping_response" class="text-sm text-slate-500 lg:col-span-2">
                        No ping payload recorded (attempt may have been skipped before ping).
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div v-if="log.post_request">
                        <p class="mb-1 text-xs font-semibold uppercase text-violet-600 dark:text-violet-400">
                            {{ isPingPost ? 'Post request' : 'Request payload' }}
                        </p>
                        <pre class="max-h-64 overflow-auto rounded-lg bg-slate-900 p-3 text-xs leading-relaxed text-violet-200">{{ formatJson(log.post_request) }}</pre>
                    </div>
                    <div v-if="log.post_response">
                        <p class="mb-1 text-xs font-semibold uppercase text-amber-600 dark:text-amber-400">
                            {{ isPingPost ? 'Post response' : 'Response payload' }}
                        </p>
                        <pre class="max-h-64 overflow-auto rounded-lg bg-slate-900 p-3 text-xs leading-relaxed text-amber-200">{{ formatJson(log.post_response) }}</pre>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-3 border-t border-slate-200 pt-3 dark:border-slate-700">
                <Link
                    v-if="log.delivery?.id"
                    :href="route('deliveries.show', log.delivery.id)"
                    class="text-sm text-indigo-600 hover:underline"
                >
                    Delivery config →
                </Link>
                <Link
                    :href="route('logs.delivery.show', log.id)"
                    class="text-sm text-indigo-600 hover:underline"
                >
                    Full log page →
                </Link>
            </div>
        </div>
    </article>
</template>
