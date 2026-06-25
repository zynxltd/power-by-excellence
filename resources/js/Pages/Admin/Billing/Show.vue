<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import ManagementHubNav from '@/Components/UI/ManagementHubNav.vue';
import TenantContextBanner from '@/Components/UI/TenantContextBanner.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    buyer: Object,
    transactions: Object,
    ledgerTypes: Array,
    currency: String,
});

const { formatMoney } = useMoneyFormat(props.currency);
const showAdvanced = ref(false);

const form = useForm({
    amount: '',
    description: '',
    type: 'credit',
    bypass_account_lock: false,
    allow_negative: false,
    suppress_alerts: false,
    skip_ledger: false,
});

const submit = () => {
    form.post(route('billing.top-up', props.buyer.id), {
        preserveScroll: true,
        onSuccess: () => form.reset('amount', 'description'),
    });
};

const typeLabel = (type) => props.ledgerTypes?.find((t) => t.value === type)?.label ?? type;
</script>

<template>
    <Head :title="`Billing — ${buyer.name}`" />
    <AuthenticatedLayout>
        <PageHeader
            :title="buyer.name"
            :description="`Reference: ${buyer.reference} · Ledger in ${currency}`"
        >
            <template #actions>
                <AppButton :href="route('billing.export', buyer.id)" variant="secondary" external>Export CSV</AppButton>
                <AppButton :href="route('buyers.show', buyer.id)" variant="secondary">Buyer profile</AppButton>
                <AppButton :href="route('billing.index')">← All billing</AppButton>
            </template>
        </PageHeader>

        <TenantContextBanner />
        <ManagementHubNav type="buyer" :entity="buyer" />

        <div
            v-if="buyer.is_low_credit"
            class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200"
        >
            <p class="font-semibold">Low credit</p>
            <p class="mt-1">
                Balance is at or below the alert threshold
                <template v-if="buyer.low_credit_alert">({{ formatMoney(buyer.low_credit_alert) }})</template>.
                Top up to resume routing when prepay is required.
            </p>
        </div>

        <div class="grid max-w-xs gap-4">
            <StatCard label="Current Balance" :value="formatMoney(buyer.credit_balance)" accent="emerald" />
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <Panel title="Ledger adjustment" class="lg:col-span-1">
                <FormErrorSummary :errors="form.errors" />
                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <InputLabel value="Adjustment type" />
                        <select v-model="form.type" class="form-select mt-1 w-full">
                            <option v-for="t in ledgerTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel :value="`Amount (${currency})`" />
                        <TextInput v-model="form.amount" type="number" step="0.01" min="0.01" class="mt-1 w-full" required />
                        <InputError class="mt-1" :message="form.errors.amount" />
                    </div>
                    <div>
                        <InputLabel value="Description / reference" />
                        <TextInput v-model="form.description" class="mt-1 w-full" placeholder="Reason for adjustment" />
                    </div>

                    <button type="button" class="text-sm font-medium text-indigo-600" @click="showAdvanced = !showAdvanced">
                        {{ showAdvanced ? 'Hide' : 'Show' }} edge-case options
                    </button>

                    <div v-if="showAdvanced" class="space-y-2 rounded-xl border border-amber-200 bg-amber-50/50 p-3 text-sm dark:border-amber-900 dark:bg-amber-950/20">
                        <label class="flex items-center gap-2">
                            <input v-model="form.bypass_account_lock" type="checkbox" class="rounded" />
                            Bypass account billing lock
                        </label>
                        <label class="flex items-center gap-2">
                            <input v-model="form.allow_negative" type="checkbox" class="rounded" />
                            Allow negative balance
                        </label>
                        <label class="flex items-center gap-2">
                            <input v-model="form.suppress_alerts" type="checkbox" class="rounded" />
                            Suppress low-balance alerts
                        </label>
                        <label class="flex items-center gap-2">
                            <input v-model="form.skip_ledger" type="checkbox" class="rounded" />
                            Balance-only (skip ledger row)
                        </label>
                    </div>

                    <PrimaryButton :disabled="form.processing">Post to ledger</PrimaryButton>
                </form>
            </Panel>

            <Panel title="Transaction history" class="lg:col-span-2" :padding="false">
                <DataTable :empty="!transactions.data?.length">
                    <template #head>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Balance</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Description</th>
                    </template>
                    <tr v-for="t in transactions.data" :key="t.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-6 py-4"><FormattedDate :value="t.created_at" /></td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ typeLabel(t.type) }}</td>
                        <td
                            class="px-6 py-4 font-medium"
                            :class="t.amount < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'"
                        >
                            {{ formatMoney(t.amount) }}
                        </td>
                        <td class="px-6 py-4 text-slate-900 dark:text-white">{{ formatMoney(t.balance_after) }}</td>
                        <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                            {{ t.description }}
                            <span v-if="t.meta?.bypass_account_lock" class="ml-1 text-xs text-amber-600">(bypass lock)</span>
                        </td>
                    </tr>
                </DataTable>
                <Pagination :links="transactions.links" />
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
