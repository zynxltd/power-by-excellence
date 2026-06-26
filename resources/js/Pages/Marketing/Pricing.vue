<script setup>
import SeoHead from '@/Components/SeoHead.vue';
import MarketingNav from '@/Components/Marketing/MarketingNav.vue';
import MarketingFooter from '@/Components/Marketing/MarketingFooter.vue';
import { useMarketingTheme } from '@/Composables/useMarketingTheme';
import { Link } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    seo: { type: Object, default: () => ({}) },
});

const { marketingTheme } = useMarketingTheme();

const tiers = [
    {
        name: 'Starter',
        price: '299',
        leads: '5,000',
        pings: '25,000',
        posts: '5,000',
        platforms: '1',
        buyers: '10',
        suppliers: '10',
        support: 'Email',
        overage: { lead: '0.08', ping: '0.002', post: '0.04' },
        fraud: {
            included: false,
            addon: '29',
            validatedLeads: '5,000',
            checks: 'Email, phone HLR & IP/proxy/VPN',
            overage: '0.04',
        },
        features: [
            'REST API ingest',
            'Ping-tree routing',
            'Direct post delivery',
            'Basic reports & logs',
            'Fraud Protection add-on (+£29/mo)',
        ],
    },
    {
        name: 'Growth',
        price: '799',
        leads: '25,000',
        pings: '150,000',
        posts: '25,000',
        platforms: '3',
        buyers: 'Unlimited',
        suppliers: 'Unlimited',
        support: 'Priority',
        overage: { lead: '0.05', ping: '0.0015', post: '0.03' },
        fraud: {
            included: true,
            validatedLeads: '25,000',
            checks: 'Email, phone HLR, IP/proxy/VPN & URL scan',
            overage: '0.03',
        },
        features: [
            'Everything in Starter',
            'Fraud Protection included',
            'Real-time auctions',
            'Form builder & hosted pages',
            'Postback manager',
            'Buyer portals',
            'API request logs',
            'Quarantine rules',
        ],
        popular: true,
    },
    {
        name: 'Enterprise',
        price: 'Custom',
        leads: 'Unlimited',
        pings: 'Custom',
        posts: 'Custom',
        platforms: 'Unlimited',
        buyers: 'Unlimited',
        suppliers: 'Unlimited',
        support: 'Dedicated CSM',
        overage: { lead: 'Negotiated', ping: 'Negotiated', post: 'Negotiated' },
        fraud: {
            included: true,
            validatedLeads: 'Custom volume',
            checks: 'Full fraud suite, custom thresholds & SLAs',
            overage: 'Negotiated',
        },
        features: [
            'Everything in Growth',
            'Fraud Protection at scale',
            'Multi-tenant platforms',
            'Custom SLAs & routing',
            'SSO & advanced security',
            'Dedicated onboarding',
            'Volume discounts on pings/posts',
        ],
    },
];

const fraudChecks = [
    { label: 'Email verification (deliverability & disposable)', plans: 'All with Fraud' },
    { label: 'Phone validation + HLR', plans: 'All with Fraud' },
    { label: 'IP / proxy / VPN / Tor detection', plans: 'All with Fraud' },
    { label: 'Residential proxy detection', plans: 'Growth, Enterprise' },
    { label: 'Lead quality score & quarantine', plans: 'Growth, Enterprise' },
];

const usageRows = [
    { label: 'Leads ingested / month', key: 'leads' },
    { label: 'Ping requests / month', key: 'pings' },
    { label: 'Post deliveries / month', key: 'posts' },
    { label: 'Buyer seats', key: 'buyers' },
    { label: 'Supplier seats', key: 'suppliers' },
    { label: 'Partner platforms', key: 'platforms' },
    {
        label: 'Fraud Protection',
        format: (t) => (t.fraud.included ? 'Included' : `+£${t.fraud.addon}/mo add-on`),
    },
    { label: 'Fraud-validated leads / month', key: 'fraud.validatedLeads' },
    {
        label: 'Fraud overage / validated lead',
        format: (t) => (t.fraud.overage === 'Negotiated' ? 'Negotiated' : `£${t.fraud.overage}`),
    },
    { label: 'Overage — per lead', key: 'overage.lead', format: (t) => (t.overage.lead === 'Negotiated' ? 'Negotiated' : `£${t.overage.lead}`) },
    { label: 'Overage — per ping', key: 'overage.ping', format: (t) => (t.overage.ping === 'Negotiated' ? 'Negotiated' : `£${t.overage.ping}`) },
    { label: 'Overage — per post', key: 'overage.post', format: (t) => (t.overage.post === 'Negotiated' ? 'Negotiated' : `£${t.overage.post}`) },
];

const cellValue = (tier, row) => {
    if (row.format) return row.format(tier);
    if (row.key.includes('.')) {
        const [a, b] = row.key.split('.');
        return tier[a]?.[b] ?? '—';
    }
    return tier[row.key] ?? '—';
};
</script>

<template>
    <SeoHead
        :title="seo.title || 'Pricing — PowerByExcellence Lead Distribution'"
        :description="seo.description || 'Transparent pricing for lead distribution. Plans include leads, pings, posts, and overage rates for ping-tree routing and real-time bidding.'"
    />

    <div class="min-h-screen">
        <MarketingNav :can-login="canLogin" active="pricing" />

        <div :class="['marketing-content', marketingTheme === 'dark' && 'marketing-dark']">
            <section class="mx-auto max-w-7xl px-4 pt-28 pb-16 text-center sm:px-6">
                <p class="brand-kicker mb-4">Plans & usage</p>
                <h1 class="text-4xl font-bold text-slate-900 marketing-dark:text-white md:text-5xl">
                    Pricing that <span class="brand-gradient-text">scales</span> with volume
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-600 marketing-dark:text-slate-400">
                    Every plan includes ping-tree routing, buyer management, and API access.
                    <strong class="font-semibold text-indigo-700 marketing-dark:text-indigo-300">Growth</strong> includes
                    <strong class="font-semibold text-indigo-700 marketing-dark:text-indigo-300">Fraud Protection</strong>
                    on every lead — email, phone, IP &amp; URL fraud checks on ingest.
                </p>
            </section>

            <section class="mx-auto max-w-5xl px-4 pb-10 sm:px-6">
                <div class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 via-violet-50/80 to-white p-6 md:p-8 marketing-dark:border-indigo-500/30 marketing-dark:from-indigo-500/10 marketing-dark:via-violet-500/5 marketing-dark:to-slate-900/40">
                    <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
                        <div class="max-w-xl">
                            <p class="brand-kicker mb-2">Fraud Protection</p>
                            <h2 class="text-2xl font-bold text-slate-900 marketing-dark:text-white">Real-time lead quality, built in</h2>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600 marketing-dark:text-slate-400">
                                Validate email deliverability, mobile reachability, proxy/VPN/Tor signals, and malicious URLs on ingest.
                                Failed checks can quarantine or reject before buyers see bad traffic.
                            </p>
                            <p class="mt-3 text-sm font-medium text-indigo-800 marketing-dark:text-indigo-200">
                                Growth plan: <span class="font-semibold">included</span> for all 25,000 leads/mo.
                                Starter: add for <span class="font-semibold">+£29/mo</span> (up to 5,000 validated leads).
                            </p>
                        </div>
                        <ul class="min-w-[240px] space-y-2 text-sm text-slate-700 marketing-dark:text-slate-300">
                            <li v-for="check in fraudChecks" :key="check.label" class="flex gap-2">
                                <span class="text-indigo-500 marketing-dark:text-cyan-400">✓</span>
                                <span>
                                    {{ check.label }}
                                    <span class="block text-xs text-slate-500">{{ check.plans }}</span>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="mx-auto grid max-w-7xl gap-8 px-4 pb-16 sm:px-6 md:grid-cols-3">
                <div
                    v-for="tier in tiers"
                    :key="tier.name"
                    :class="[
                        'relative flex flex-col rounded-2xl border p-8',
                        tier.popular
                            ? 'border-indigo-400 bg-gradient-to-b from-violet-50 via-indigo-50/80 to-white shadow-brand-lg marketing-dark:border-indigo-500 marketing-dark:from-indigo-500/15 marketing-dark:via-violet-500/10 marketing-dark:to-slate-900/50'
                            : 'brand-card',
                    ]"
                >
                    <span v-if="tier.popular" class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-gradient-to-r from-violet-600 to-indigo-600 px-3 py-1 text-xs font-semibold text-white shadow-indigo-500/30">Most popular</span>
                    <h2 class="text-xl font-bold text-slate-900 marketing-dark:text-white">{{ tier.name }}</h2>
                    <p class="mt-4">
                        <span class="brand-stat-value text-4xl">{{ tier.price === 'Custom' ? tier.price : `£${tier.price}` }}</span>
                        <span v-if="tier.price !== 'Custom'" class="text-slate-500 marketing-dark:text-slate-400">/month</span>
                    </p>
                    <div class="mt-4 space-y-1 rounded-xl border border-violet-100 bg-violet-50/50 p-4 text-sm marketing-dark:border-white/10 marketing-dark:bg-slate-950/50">
                        <p><span class="text-slate-500">Leads</span> <span class="font-semibold text-indigo-800 marketing-dark:text-white">{{ tier.leads }}</span>/mo</p>
                        <p><span class="text-slate-500">Pings</span> <span class="font-semibold text-cyan-700 marketing-dark:text-cyan-300">{{ tier.pings }}</span>/mo</p>
                        <p><span class="text-slate-500">Posts</span> <span class="font-semibold text-violet-700 marketing-dark:text-violet-300">{{ tier.posts }}</span>/mo</p>
                        <p><span class="text-slate-500">Buyers</span> <span class="font-semibold text-slate-800 marketing-dark:text-white">{{ tier.buyers }}</span></p>
                        <p><span class="text-slate-500">Suppliers</span> <span class="font-semibold text-slate-800 marketing-dark:text-white">{{ tier.suppliers }}</span></p>
                        <p class="border-t border-violet-100 pt-2 marketing-dark:border-white/10">
                            <span class="text-slate-500">Fraud</span>
                            <span v-if="tier.fraud.included" class="font-semibold text-emerald-700 marketing-dark:text-emerald-300"> Included</span>
                            <span v-else class="font-semibold text-amber-700 marketing-dark:text-amber-300"> +£{{ tier.fraud.addon }}/mo</span>
                        </p>
                    </div>
                    <ul class="mt-6 flex-1 space-y-2 text-sm text-slate-700 marketing-dark:text-slate-300">
                        <li v-for="f in tier.features" :key="f" class="flex gap-2">
                            <span class="text-indigo-500 marketing-dark:text-cyan-400">✓</span> {{ f }}
                        </li>
                    </ul>
                    <a href="/#demo" class="brand-btn-primary mt-8 block px-4 py-3 text-center text-sm">
                        {{ tier.price === 'Custom' ? 'Contact sales' : 'Book a demo' }}
                    </a>
                </div>
            </section>

            <section class="brand-section-light mx-auto max-w-7xl px-4 pb-24 sm:px-6">
                <h2 class="mb-6 text-center text-2xl font-bold text-slate-900 marketing-dark:text-white">Usage & overage comparison</h2>
                <div class="overflow-x-auto rounded-2xl border border-violet-100 shadow-brand marketing-dark:border-white/10 marketing-dark:shadow-none">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="border-b border-violet-100 bg-violet-50/60 marketing-dark:border-white/10 marketing-dark:bg-slate-900/80">
                            <tr>
                                <th class="px-6 py-4 font-semibold text-slate-500">Included / overage</th>
                                <th v-for="tier in tiers" :key="tier.name" class="px-6 py-4 font-semibold text-indigo-800 marketing-dark:text-white">{{ tier.name }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in usageRows" :key="row.label" class="border-b border-violet-50 marketing-dark:border-white/5">
                                <td class="px-6 py-4 text-slate-500">{{ row.label }}</td>
                                <td v-for="tier in tiers" :key="tier.name + row.label" class="px-6 py-4 text-slate-800 marketing-dark:text-slate-200">{{ cellValue(tier, row) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-4 text-center text-sm text-slate-500">
                    Pings count each buyer ping in waterfall or auction tiers. Posts count successful lead deliveries.
                    Fraud Protection runs up to 4 fraud lookups per lead (email, phone, IP, URL when enabled).
                    Starter adds Fraud Protection for <strong class="font-medium text-slate-700 marketing-dark:text-slate-300">+£29/mo</strong> (5,000 validated leads included).
                    Growth includes fraud on all plan leads.
                    <Link :href="route('help.index')" class="font-medium text-indigo-600 hover:underline marketing-dark:text-cyan-400">Help Centre →</Link>
                </p>
            </section>
        </div>

        <MarketingFooter :can-login="canLogin" />
    </div>
</template>
