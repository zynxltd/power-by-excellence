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
    <Panel title="Account & limits">
        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
            Delivery caps, credit top-ups, and return approvals are managed by your platform administrator.
        </p>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Buyer status</dt>
                <dd class="mt-1"><StatusBadge :status="account.status" /></dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Billing</dt>
                <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white">
                    {{ account.require_prepay ? 'Prepay — balance required' : 'Post-paid' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Daily lead cap</dt>
                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ capLabel(account.daily_cap) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Daily spend cap</dt>
                <dd class="mt-1 text-sm text-slate-900 dark:text-white">
                    {{ account.daily_spend_cap != null ? formatMoney(account.daily_spend_cap) : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Active deliveries</dt>
                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ account.active_deliveries ?? 0 }}</dd>
            </div>
            <div v-if="account.low_credit_threshold != null">
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Low-credit alert</dt>
                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ formatMoney(account.low_credit_threshold) }}</dd>
            </div>
        </dl>

        <div
            v-if="account.is_low_credit"
            class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200"
        >
            Credit is below your alert threshold. Contact your account manager to top up — buyers cannot add credit in the portal.
        </div>
    </Panel>
</template>
