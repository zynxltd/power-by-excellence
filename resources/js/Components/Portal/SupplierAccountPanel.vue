<script setup>
import Panel from '@/Components/UI/Panel.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps({
    account: { type: Object, required: true },
    currency: { type: String, default: 'GBP' },
});

const { formatMoney } = useMoneyFormat(props.currency);

const capLabel = (value) => (value == null || value === '' ? '—' : value);
</script>

<template>
    <Panel title="Account & tracking">
        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
            Payout rates, caps, and postback configuration are managed by your platform administrator.
        </p>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Supplier status</dt>
                <dd class="mt-1"><StatusBadge :status="account.status" /></dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Reference</dt>
                <dd class="mt-1 font-mono text-sm text-slate-900 dark:text-white">{{ account.reference }}</dd>
            </div>
            <div v-if="account.rev_share_percent != null">
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Rev share</dt>
                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ account.rev_share_percent }}%</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Sources (SIDs)</dt>
                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ account.source_count ?? 0 }}</dd>
            </div>
            <div v-if="account.sub_affiliate_count > 0">
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Sub-affiliates</dt>
                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ account.sub_affiliate_count }}</dd>
            </div>
            <div v-if="account.default_postback_url">
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Postback URL</dt>
                <dd class="mt-1 truncate text-xs text-indigo-600 dark:text-indigo-400">{{ account.default_postback_url }}</dd>
            </div>
        </dl>

        <div v-if="account.sources?.length" class="mt-4 space-y-2 border-t border-slate-100 pt-4 dark:border-slate-800">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Configured sources</p>
            <div
                v-for="source in account.sources"
                :key="source.sid"
                class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/50"
            >
                <div>
                    <span class="font-mono font-semibold text-indigo-600 dark:text-indigo-400">{{ source.sid }}</span>
                    <span class="ml-2 text-slate-600 dark:text-slate-400">{{ source.name }}</span>
                </div>
                <span class="text-xs text-slate-500">
                    <template v-if="source.daily_cap != null">Cap {{ capLabel(source.daily_cap) }}/day</template>
                    <template v-else-if="source.payout_override != null">{{ formatMoney(source.payout_override) }}</template>
                </span>
            </div>
        </div>
    </Panel>
</template>
