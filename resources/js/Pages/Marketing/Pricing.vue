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
        buyers: '2',
        support: 'Email',
        overage: { lead: '0.08', ping: '0.002', post: '0.04' },
        features: ['REST API ingest', 'Ping-tree routing', 'Direct post delivery', 'Basic reports & logs', 'Email validation (1k/mo)'],
    },
    {
        name: 'Growth',
        price: '799',
        leads: '25,000',
        pings: '150,000',
        posts: '25,000',
        platforms: '3',
        buyers: '10',
        support: 'Priority',
        overage: { lead: '0.05', ping: '0.0015', post: '0.03' },
        features: ['Everything in Starter', 'Real-time auctions', 'Form builder & hosted pages', 'Postback manager', 'Buyer portals', 'API request logs', 'Quarantine rules'],
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
        support: 'Dedicated CSM',
        overage: { lead: 'Negotiated', ping: 'Negotiated', post: 'Negotiated' },
        features: ['Everything in Growth', 'Multi-tenant platforms', 'Custom SLAs & routing', 'SSO & advanced security', 'Dedicated onboarding', 'Volume discounts on pings/posts'],
    },
];

const usageRows = [
    { label: 'Leads ingested / month', key: 'leads' },
    { label: 'Ping requests / month', key: 'pings' },
    { label: 'Post deliveries / month', key: 'posts' },
    { label: 'Buyer seats', key: 'buyers' },
    { label: 'Partner platforms', key: 'platforms' },
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
                    Every plan includes ping-tree routing, validation, buyer management, and API access.
                    Usage is measured on <strong class="font-semibold text-indigo-700 marketing-dark:text-indigo-300">leads</strong>, <strong class="font-semibold text-indigo-700 marketing-dark:text-indigo-300">pings</strong>, and <strong class="font-semibold text-indigo-700 marketing-dark:text-indigo-300">posts</strong>.
                </p>
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
                    <Link :href="route('help.index')" class="font-medium text-indigo-600 hover:underline marketing-dark:text-cyan-400">Help Centre →</Link>
                </p>
            </section>
        </div>

        <MarketingFooter :can-login="canLogin" />
    </div>
</template>
