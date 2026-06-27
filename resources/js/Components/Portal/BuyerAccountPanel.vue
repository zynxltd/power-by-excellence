<script setup>
import Panel from '@/Components/UI/Panel.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { useBuyerPortalI18n } from '@/Composables/useBuyerPortalI18n';

const props = defineProps({
    account: { type: Object, required: true },
    currency: { type: String, default: 'GBP' },
});

const { t } = useBuyerPortalI18n();
const { formatMoney } = useMoneyFormat(props.currency);

const capLabel = (value) => (value == null || value === '' ? '—' : value);
</script>

<template>
    <Panel :title="t('account.title')">
        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
            {{ t('account.intro') }}
        </p>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('account.buyer_status') }}</dt>
                <dd class="mt-1"><StatusBadge :status="account.status" /></dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('account.billing_mode') }}</dt>
                <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white">
                    {{ account.require_prepay ? t('account.prepay_required') : t('account.postpaid') }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('account.daily_cap') }}</dt>
                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ capLabel(account.daily_cap) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('account.daily_spend_cap') }}</dt>
                <dd class="mt-1 text-sm text-slate-900 dark:text-white">
                    {{ account.daily_spend_cap != null ? formatMoney(account.daily_spend_cap) : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('account.active_deliveries') }}</dt>
                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ account.active_deliveries ?? 0 }}</dd>
            </div>
            <div v-if="account.low_credit_threshold != null">
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('account.low_credit_alert') }}</dt>
                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ formatMoney(account.low_credit_threshold) }}</dd>
            </div>
        </dl>

        <div
            v-if="account.is_low_credit"
            class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200"
        >
            {{ t('account.low_credit_warning') }}
        </div>
    </Panel>
</template>
