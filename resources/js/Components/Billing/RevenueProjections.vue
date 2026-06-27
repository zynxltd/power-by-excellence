<script setup>
import Panel from '@/Components/UI/Panel.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { computed, ref, watch } from 'vue';

const { formatMoney } = useMoneyFormat('GBP');

const MAX_CLIENTS = 10;

const plans = {
    starter: {
        label: 'Starter',
        price: 299,
        unitCogs: 95,
        note: '5k leads · email support',
    },
    growth: {
        label: 'Growth',
        price: 799,
        unitCogs: 245,
        note: '25k leads · fraud included',
        popular: true,
    },
    enterprise: {
        label: 'Enterprise',
        price: 2499,
        unitCogs: 620,
        note: 'Custom volume · dedicated CSM',
    },
};

const counts = ref({ starter: 2, growth: 5, enterprise: 3 });
const setupFee = ref(1500);
const setupCost = ref(450);
const platformOverhead = ref(380);
const overageUpliftPct = ref(8);

const totalClients = computed(() =>
    counts.value.starter + counts.value.growth + counts.value.enterprise,
);

const clampCounts = () => {
    let total = totalClients.value;
    if (total <= MAX_CLIENTS) {
        return;
    }

    const order = ['enterprise', 'starter', 'growth'];
    for (const key of order) {
        while (total > MAX_CLIENTS && counts.value[key] > 0) {
            counts.value[key]--;
            total--;
        }
    }
};

watch(counts, clampCounts, { deep: true });

const planRows = computed(() =>
    Object.entries(plans).map(([key, plan]) => {
        const clients = counts.value[key];
        const revenue = clients * plan.price;
        const cogs = clients * plan.unitCogs;
        const profit = revenue - cogs;
        const margin = revenue > 0 ? (profit / revenue) * 100 : 0;

        return { key, ...plan, clients, revenue, cogs, profit, margin };
    }),
);

const recurringRevenue = computed(() => planRows.value.reduce((sum, row) => sum + row.revenue, 0));

const clientCogs = computed(() => planRows.value.reduce((sum, row) => sum + row.cogs, 0));

const overageRevenue = computed(() => Math.round(recurringRevenue.value * (overageUpliftPct.value / 100)));

const overageCogs = computed(() => Math.round(overageRevenue.value * 0.35));

const monthlyRevenue = computed(() => recurringRevenue.value + overageRevenue.value);

const monthlyCogs = computed(() => clientCogs.value + platformOverhead.value + overageCogs.value);

const monthlyProfit = computed(() => monthlyRevenue.value - monthlyCogs.value);

const monthlyMargin = computed(() =>
    monthlyRevenue.value > 0 ? (monthlyProfit.value / monthlyRevenue.value) * 100 : 0,
);

const setupRevenue = computed(() => totalClients.value * setupFee.value);

const setupProfit = computed(() => setupRevenue.value - totalClients.value * setupCost.value);

const monthOneTotal = computed(() => monthlyRevenue.value + setupRevenue.value);

const monthOneProfit = computed(() => monthlyProfit.value + setupProfit.value);

const annualRecurring = computed(() => monthlyRevenue.value * 12);

const annualProfit = computed(() => monthlyProfit.value * 12 + setupProfit.value);

const summaryStrip = computed(() => [
    {
        label: 'Clients',
        value: `${totalClients.value} / ${MAX_CLIENTS}`,
        accent: 'indigo',
    },
    {
        label: 'Monthly revenue',
        value: formatMoney(monthlyRevenue.value, { decimals: 0 }),
        accent: 'emerald',
    },
    {
        label: 'Monthly costs',
        value: formatMoney(monthlyCogs.value, { decimals: 0 }),
        accent: 'amber',
    },
    {
        label: 'Monthly profit',
        value: formatMoney(monthlyProfit.value, { decimals: 0 }),
        accent: 'emerald',
    },
    {
        label: 'Margin',
        value: `${monthlyMargin.value.toFixed(1)}%`,
        accent: monthlyMargin.value >= 60 ? 'emerald' : 'amber',
    },
]);
</script>

<template>
    <Panel title="Earnings projection (illustrative)" class="mb-6">
        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
            Model monthly platform revenue vs estimated delivery costs for up to {{ MAX_CLIENTS }} partner clients.
            Adjust plan mix, setup fees, and assumptions - figures are planning estimates, not live billing data.
        </p>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,280px)_1fr]">
            <div class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/40">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Client mix</p>
                <div v-for="(plan, key) in plans" :key="key" class="space-y-1">
                    <InputLabel :value="`${plan.label} (£${plan.price}/mo)${plan.popular ? ' · popular' : ''}`" />
                    <input
                        v-model.number="counts[key]"
                        type="range"
                        min="0"
                        :max="MAX_CLIENTS"
                        class="w-full accent-indigo-600"
                    />
                    <p class="text-xs text-slate-500">{{ counts[key] }} client{{ counts[key] === 1 ? '' : 's' }} · {{ plan.note }}</p>
                </div>

                <div class="border-t border-slate-200 pt-4 dark:border-slate-700">
                    <InputLabel value="Setup fee (per new client)" />
                    <TextInput v-model.number="setupFee" type="number" min="0" step="100" class="mt-1 w-full" />
                </div>
                <div>
                    <InputLabel value="Setup delivery cost (per client)" />
                    <TextInput v-model.number="setupCost" type="number" min="0" step="50" class="mt-1 w-full" />
                </div>
                <div>
                    <InputLabel value="Platform overhead / month" />
                    <TextInput v-model.number="platformOverhead" type="number" min="0" step="50" class="mt-1 w-full" />
                </div>
                <div>
                    <InputLabel value="Overage uplift % of MRR" />
                    <TextInput v-model.number="overageUpliftPct" type="number" min="0" max="30" step="1" class="mt-1 w-full" />
                    <p class="mt-1 text-xs text-slate-500">Estimated ping/post/lead overage on top of plan fees.</p>
                </div>
            </div>

            <div class="space-y-4">
                <CompactStatStrip :items="summaryStrip" :columns="5" />

                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:bg-slate-800/50">
                            <tr>
                                <th class="px-4 py-3">Plan</th>
                                <th class="px-4 py-3 text-right">Clients</th>
                                <th class="px-4 py-3 text-right">Unit price</th>
                                <th class="px-4 py-3 text-right">Revenue</th>
                                <th class="px-4 py-3 text-right">COGS</th>
                                <th class="px-4 py-3 text-right">Profit</th>
                                <th class="px-4 py-3 text-right">Margin</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="row in planRows" :key="row.key" :class="row.popular && 'bg-indigo-50/50 dark:bg-indigo-950/20'">
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">
                                    {{ row.label }}
                                    <span v-if="row.popular" class="ml-1 text-[10px] font-bold uppercase text-indigo-600 dark:text-indigo-400">Popular</span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ row.clients }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatMoney(row.price, { decimals: 0 }) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-emerald-700 dark:text-emerald-400">{{ formatMoney(row.revenue, { decimals: 0 }) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-amber-700 dark:text-amber-400">{{ formatMoney(row.cogs, { decimals: 0 }) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium">{{ formatMoney(row.profit, { decimals: 0 }) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ row.margin.toFixed(1) }}%</td>
                            </tr>
                            <tr v-if="overageRevenue > 0" class="text-slate-600 dark:text-slate-400">
                                <td class="px-4 py-3">Overage (est.)</td>
                                <td class="px-4 py-3 text-right">-</td>
                                <td class="px-4 py-3 text-right">-</td>
                                <td class="px-4 py-3 text-right tabular-nums text-emerald-700 dark:text-emerald-400">{{ formatMoney(overageRevenue, { decimals: 0 }) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-amber-700 dark:text-amber-400">{{ formatMoney(overageCogs, { decimals: 0 }) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium">{{ formatMoney(overageRevenue - overageCogs, { decimals: 0 }) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">~65%</td>
                            </tr>
                            <tr class="bg-slate-50/80 dark:bg-slate-900/40">
                                <td class="px-4 py-3 font-medium">Platform overhead</td>
                                <td class="px-4 py-3 text-right" colspan="3">Central infra, monitoring, support</td>
                                <td class="px-4 py-3 text-right tabular-nums text-amber-700 dark:text-amber-400">{{ formatMoney(platformOverhead, { decimals: 0 }) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-rose-600 dark:text-rose-400">−{{ formatMoney(platformOverhead, { decimals: 0 }) }}</td>
                                <td class="px-4 py-3" />
                            </tr>
                            <tr class="border-t-2 border-slate-200 font-semibold dark:border-slate-600">
                                <td class="px-4 py-3">Monthly recurring</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ totalClients }}</td>
                                <td class="px-4 py-3" />
                                <td class="px-4 py-3 text-right tabular-nums text-emerald-700 dark:text-emerald-400">{{ formatMoney(monthlyRevenue, { decimals: 0 }) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-amber-700 dark:text-amber-400">{{ formatMoney(monthlyCogs, { decimals: 0 }) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatMoney(monthlyProfit, { decimals: 0 }) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ monthlyMargin.toFixed(1) }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-indigo-200 bg-indigo-50/60 p-4 dark:border-indigo-500/30 dark:bg-indigo-950/30">
                        <p class="text-xs font-semibold uppercase tracking-wider text-indigo-700 dark:text-indigo-300">Month 1 (all clients onboard)</p>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-slate-600 dark:text-slate-400">Setup fees ({{ totalClients }} × {{ formatMoney(setupFee, { decimals: 0 }) }})</dt>
                                <dd class="font-medium tabular-nums text-emerald-700 dark:text-emerald-400">+{{ formatMoney(setupRevenue, { decimals: 0 }) }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-slate-600 dark:text-slate-400">Setup delivery cost</dt>
                                <dd class="font-medium tabular-nums text-amber-700 dark:text-amber-400">−{{ formatMoney(totalClients * setupCost, { decimals: 0 }) }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-slate-600 dark:text-slate-400">First month MRR</dt>
                                <dd class="font-medium tabular-nums">{{ formatMoney(monthlyRevenue, { decimals: 0 }) }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 border-t border-indigo-200 pt-2 font-semibold dark:border-indigo-500/30">
                                <dt>Month 1 total profit</dt>
                                <dd class="tabular-nums text-indigo-900 dark:text-indigo-100">{{ formatMoney(monthOneProfit, { decimals: 0 }) }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 text-xs text-slate-500">
                                <dt>Gross in month 1</dt>
                                <dd class="tabular-nums">{{ formatMoney(monthOneTotal, { decimals: 0 }) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-900/40">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Year 1 outlook</p>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-slate-600 dark:text-slate-400">Recurring revenue (12 mo)</dt>
                                <dd class="font-medium tabular-nums">{{ formatMoney(annualRecurring, { decimals: 0 }) }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-slate-600 dark:text-slate-400">One-time setup (year)</dt>
                                <dd class="font-medium tabular-nums text-emerald-700 dark:text-emerald-400">+{{ formatMoney(setupRevenue, { decimals: 0 }) }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 border-t border-slate-200 pt-2 font-semibold dark:border-slate-700">
                                <dt>Est. year 1 profit</dt>
                                <dd class="tabular-nums text-slate-900 dark:text-white">{{ formatMoney(annualProfit, { decimals: 0 }) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <details class="rounded-lg border border-slate-200 px-4 py-3 text-xs text-slate-500 dark:border-slate-700">
                    <summary class="cursor-pointer font-semibold text-slate-700 dark:text-slate-300">COGS assumptions per client / month</summary>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li><strong>Starter (£95):</strong> shared hosting, email support, basic ops.</li>
                        <li><strong>Growth (£245):</strong> fraud API (~25k validations), priority support, higher infra.</li>
                        <li><strong>Enterprise (£620):</strong> scaled fraud, dedicated CSM time, custom SLAs.</li>
                        <li><strong>Platform overhead:</strong> central DB, Redis, Horizon, monitoring, super-admin ops.</li>
                        <li><strong>Setup:</strong> onboarding, DNS, campaign template, buyer training (fee {{ formatMoney(setupFee, { decimals: 0 }) }}, cost {{ formatMoney(setupCost, { decimals: 0 }) }}).</li>
                    </ul>
                </details>
            </div>
        </div>
    </Panel>
</template>
