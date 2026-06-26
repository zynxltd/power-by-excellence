<script setup>
import MarketingNav from '@/Components/Marketing/MarketingNav.vue';
import MarketingFooter from '@/Components/Marketing/MarketingFooter.vue';
import SeoHead from '@/Components/SeoHead.vue';
import SystemStatusBadge from '@/Components/Marketing/SystemStatusBadge.vue';
import ToastHost from '@/Components/UI/ToastHost.vue';
import { useMarketingTheme } from '@/Composables/useMarketingTheme';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

defineProps({
    canLogin: Boolean,
    seo: { type: Object, default: () => ({}) },
});

const page = usePage();
const { marketingTheme } = useMarketingTheme();
const systemStatus = computed(() => page.props.systemStatus);
const signInUrl = computed(() => page.props.urls?.marketingSignIn ?? route('login'));
const isAuthenticated = computed(() => !!page.props.auth?.user);

const demoForm = useForm({
    name: '',
    email: '',
    company: '',
    message: '',
});

const submitDemo = () => {
    demoForm.post(route('demo.request'), {
        preserveScroll: true,
        onSuccess: () => demoForm.reset(),
    });
};

const heroStats = [
    { value: '<50ms', label: 'Processing', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
    { value: '99.9%', label: 'Uptime', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
    { value: 'Multi-tenant', label: 'Platforms', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
    { value: 'Real-time', label: 'Ping-tree', icon: 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z' },
];

const liveBids = [
    { buyer: 'Farmers Direct', amount: 28.5, width: 88 },
    { buyer: 'Allstate Affiliates', amount: 24.0, width: 72 },
    { buyer: 'GEICO Partners', amount: 19.5, width: 58 },
];

const pingTreeTiers = [
    {
        tier: 1,
        title: 'Parallel Auction',
        mode: 'Ping-post',
        subtitle: 'Highest bid wins · Floor £10',
        detail: '3 buyers pinged in parallel',
        indent: 'ml-0',
        sold: true,
        cardClass: 'border-violet-500/35 bg-gradient-to-br from-violet-500/20 via-violet-950/30 to-slate-900/80 ring-1 ring-violet-400/20',
        iconBg: 'bg-violet-500/25 text-violet-300',
        iconPath: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
        showBids: true,
    },
    {
        tier: 2,
        title: 'Waterfall',
        mode: 'Sequential',
        subtitle: 'Priority buyers in sequence',
        detail: 'First accept wins the post',
        indent: 'ml-5 sm:ml-8',
        sold: false,
        cardClass: 'border-indigo-500/30 bg-gradient-to-br from-indigo-500/15 via-slate-900/60 to-slate-900/80',
        iconBg: 'bg-indigo-500/20 text-indigo-300',
        iconPath: 'M19 14l-7 7m0 0l-7-7m7 7V3',
        showBids: false,
    },
    {
        tier: 3,
        title: 'Store Lead',
        mode: 'Fallback',
        subtitle: 'Archive if still unsold',
        detail: 'Trigger auto-responder · retry later',
        indent: 'ml-10 sm:ml-16',
        sold: false,
        cardClass: 'border-cyan-500/30 bg-gradient-to-br from-cyan-500/15 via-slate-900/60 to-slate-900/80',
        iconBg: 'bg-cyan-500/20 text-cyan-300',
        iconPath: 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
        showBids: false,
    },
];

const stats = [
    { value: '<50ms', label: 'Lead processing' },
    { value: '99.9%', label: 'Platform uptime' },
    { value: 'Multi-tenant', label: 'Partner platforms' },
    { value: 'Real-time', label: 'Ping-tree routing' },
];

const features = [
    {
        icon: 'bolt',
        title: 'Real-Time Capture',
        desc: 'Ingest leads via REST API, CSV import, or webhooks with sub-50ms async queue processing and live sync responses.',
        color: 'from-violet-500/25 via-indigo-500/20 to-violet-500/15 text-violet-600 marketing-dark:text-violet-400',
    },
    {
        icon: 'tree',
        title: 'Ping Tree Routing',
        desc: 'Waterfall, ping-post auction, weighted distribution, round-robin, and hybrid tiered groups — all configurable per campaign.',
        color: 'from-indigo-500/25 via-cyan-500/15 to-indigo-500/20 text-indigo-600 marketing-dark:text-indigo-400',
    },
    {
        icon: 'shield',
        title: 'Validation & Dedupe',
        desc: 'Email, phone, custom field rules, suppression lists, and cross-campaign deduplication before a lead ever reaches a buyer.',
        color: 'from-cyan-500/25 via-indigo-500/15 to-cyan-500/20 text-cyan-700 marketing-dark:text-cyan-400',
    },
    {
        icon: 'building',
        title: 'Multi-Tenancy',
        desc: 'Super-admins host isolated partner platforms — each with its own subdomain, buyers, suppliers, and financials. UK, US, and other markets run as campaigns within a platform.',
        color: 'from-violet-500/20 via-indigo-500/25 to-cyan-500/15 text-violet-600 marketing-dark:text-violet-400',
    },
    {
        icon: 'users',
        title: 'Buyer & Supplier Portals',
        desc: 'Self-service portals for lead downloads, buyer feedback, returns, credit management, and supplier source analytics.',
        color: 'from-indigo-500/20 via-violet-500/20 to-indigo-500/25 text-indigo-600 marketing-dark:text-indigo-400',
    },
    {
        icon: 'chart',
        title: 'Reporting & Webhooks',
        desc: 'Revenue reports, delivery audit logs, buyer transactions, and outbound event webhooks for your entire stack.',
        color: 'from-cyan-500/20 via-indigo-500/20 to-violet-500/15 text-cyan-700 marketing-dark:text-cyan-400',
    },
    {
        icon: 'pixel',
        title: 'Postback Manager',
        desc: 'Fire affiliate pixels and conversion tracking URLs on lead accepted, sold, rejected, and delivery success — scoped per supplier or campaign.',
        color: 'from-violet-500/15 via-cyan-500/20 to-indigo-500/20 text-violet-600 marketing-dark:text-violet-400',
    },
    {
        icon: 'automation',
        title: 'Automation & Forms',
        desc: 'Auto-responders, remarketing sequences, bulk SMS, hosted form builder, and event alerts — all wired into the lead pipeline.',
        color: 'from-indigo-500/25 via-violet-500/15 to-cyan-500/15 text-indigo-600 marketing-dark:text-indigo-400',
    },
    {
        icon: 'code',
        title: 'REST API & SDK',
        desc: 'Scoped API keys, sync/async ingest, JavaScript and PHP client libraries, plus ping/post simulators for rapid integration.',
        color: 'from-cyan-500/15 via-indigo-500/25 to-violet-500/20 text-cyan-700 marketing-dark:text-cyan-400',
    },
];

const customSolution = {
    title: 'Need a custom solution?',
    desc: 'When off-the-shelf routing, integrations, or workflows are not enough, we design and deliver bespoke setups — custom ping trees, buyer adapters, data pipelines, white-label portals, and enterprise SLAs.',
    points: [
        'Custom delivery methods & buyer integrations',
        'Migration from legacy routers and spreadsheets',
        'Dedicated onboarding and solution architecture',
    ],
};

const steps = [
    { title: 'Capture', desc: 'Leads arrive via API, CSV, or supplier integration with SID/SSID source tracking.', icon: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12' },
    { title: 'Validate', desc: 'Rules engine, dedupe, and suppression checks filter junk before distribution begins.', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
    { title: 'Distribute', desc: 'Ping-tree engine routes to the highest-bidding or priority buyers in real-time.', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
    { title: 'Report', desc: 'Revenue, margins, and buyer feedback tracked per lead with a full audit trail.', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
];

const verticals = ['Auto Insurance', 'Solar', 'Home Services', 'Mortgage', 'Legal', 'Education', 'Healthcare', 'Finance', 'Loans', 'Payday', 'Life Insurance', 'Debt Relief'];

const rotatingWords = ['revenue', 'margin', 'buyers', 'profit'];
const currentWordIndex = ref(0);
const currentWord = computed(() => rotatingWords[currentWordIndex.value]);
let wordInterval;

onMounted(() => {
    wordInterval = setInterval(() => {
        currentWordIndex.value = (currentWordIndex.value + 1) % rotatingWords.length;
    }, 2800);
});

onUnmounted(() => {
    clearInterval(wordInterval);
});

const testimonials = [
    {
        quote: 'We moved three buyer networks onto PBE in a weekend. Ping-tree auctions alone lifted average revenue per lead by 18%.',
        name: 'Sarah Chen',
        role: 'Head of Partnerships',
        company: 'Apex Lead Group',
        metric: '+18% RPL',
    },
    {
        quote: 'The supplier portal and postback manager replaced three separate tools. Our affiliates finally get real-time conversion data.',
        name: 'Marcus Webb',
        role: 'Affiliate Director',
        company: 'Velocity Media',
        metric: '3 tools replaced',
    },
    {
        quote: 'Super-admin tenancy keeps partner platforms isolated — no data bleed between brands. We run UK and US on the same platform as separate campaigns, each with its own buyers and currency. Audit logs are a lifesaver.',
        name: 'Priya Nair',
        role: 'Platform Operations',
        company: 'Northstar Leads',
        metric: 'Isolated tenants',
    },
];

const integrations = [
    { name: 'REST API', desc: 'Sync & async ingest', icon: 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4' },
    { name: 'Webhooks', desc: 'Outbound events', icon: 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1' },
    { name: 'CSV Import', desc: 'Bulk lead upload', icon: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4' },
    { name: 'Bank wire', desc: 'Manual buyer top-ups', icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z' },
    { name: 'Postbacks', desc: 'Affiliate pixels', icon: 'M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122' },
    { name: 'Hosted Forms', desc: 'No-code capture', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
];

const comparisonRows = [
    { feature: 'Real-time ping-post auctions', pbe: true, legacy: false },
    { feature: 'Visual ping-tree builder', pbe: true, legacy: false },
    { feature: 'Multi-tenant partner platforms', pbe: true, legacy: false },
    { feature: 'Buyer & supplier self-service portals', pbe: true, legacy: false },
    { feature: 'Full delivery & API audit logs', pbe: true, legacy: false },
    { feature: 'Per-tier eligibility filters', pbe: true, legacy: 'Manual' },
    { feature: 'Quarantine & validation rules', pbe: true, legacy: 'Add-on' },
    { feature: 'Postback manager with field tags', pbe: true, legacy: false },
];

const securityFeatures = [
    {
        title: 'Tenant isolation',
        desc: 'Super-admin only: each partner platform runs on its own subdomain with scoped data, API keys, and financials — separate from other tenants.',
        icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
    },
    {
        title: 'Scoped API keys',
        desc: 'Granular permissions per key — leads.create, leads.read, reports, quarantine — with prefix|secret auth.',
        icon: 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z',
    },
    {
        title: 'Audit trail',
        desc: 'Access logs, change logs, security events, and per-request API logging for compliance reviews.',
        icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    },
    {
        title: 'Two-factor auth',
        desc: 'Optional 2FA for admin accounts. Billing lock enforcement when credit thresholds are breached.',
        icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    },
];

const pricingPreview = [
    { name: 'Starter', price: '£299', highlight: '5k leads/mo · fraud +£29', href: 'pricing' },
    { name: 'Growth', price: '£799', highlight: '25k leads · fraud included', popular: true, href: 'pricing' },
    { name: 'Enterprise', price: 'Custom', highlight: 'Unlimited scale', href: 'pricing' },
];

const faqs = [
    {
        q: 'How fast is lead processing?',
        a: 'Async ingest returns in under 50ms with a queue ID. Sync mode runs the full pipeline inline and returns the final status in one request — ideal for live buyer redirects.',
    },
    {
        q: 'How are partner platforms and markets organised?',
        a: 'Each partner platform is fully isolated with its own buyers, suppliers, and billing. Within one platform, run UK, US, and other markets as separate campaigns with their own currency and ping trees.',
    },
    {
        q: 'What delivery methods are supported?',
        a: 'Ping-post auctions, waterfall routing, direct API post, email alerts, and store-lead fallback — all configurable in the visual ping-tree builder with per-tier filters.',
    },
    {
        q: 'Do you support affiliate tracking?',
        a: 'Full SID/SSID source tracking, postback manager with field tag interpolation, and supplier portals with conversion reporting.',
    },
    {
        q: 'Is there an API and SDK?',
        a: 'REST API at /api/v1 with scoped keys, plus JavaScript and PHP client libraries. Built-in ping/post simulators help you test integrations without live buyers.',
    },
];

const openFaq = ref(null);
const toggleFaq = (index) => {
    openFaq.value = openFaq.value === index ? null : index;
};
</script>

<template>
    <SeoHead
        :title="seo?.title || 'PowerByExcellence — Real-Time Lead Distribution Platform'"
        :description="seo?.description || 'Ping-tree routing, real-time buyer auctions, multi-vertical capture, and enterprise reporting for agencies and lead sellers.'"
    />

    <div class="min-h-screen">
        <MarketingNav :can-login="canLogin" />

        <div :class="['marketing-content', marketingTheme === 'dark' && 'marketing-dark']">
        <!-- Hero -->
        <section class="hero-section relative overflow-hidden">
            <div class="pointer-events-none absolute inset-0">
                <div class="hero-grid absolute inset-0" />
                <div class="absolute -right-32 top-0 h-[32rem] w-[32rem] rounded-full bg-gradient-to-br from-indigo-400/20 via-violet-400/10 to-transparent blur-3xl marketing-dark:from-indigo-600/20" />
                <div class="absolute -left-24 bottom-0 h-72 w-72 rounded-full bg-gradient-to-tr from-cyan-400/15 to-transparent blur-3xl marketing-dark:from-cyan-500/10" />
                <div class="absolute left-1/2 top-1/2 h-px w-[120%] -translate-x-1/2 -translate-y-1/2 rotate-[-8deg] bg-gradient-to-r from-transparent via-indigo-300/30 to-transparent marketing-dark:via-indigo-500/20" />
            </div>

            <div class="relative mx-auto max-w-7xl px-6 pb-20 pt-28 md:pb-28 md:pt-36">
                <div class="grid items-center gap-14 lg:grid-cols-[1.05fr_0.95fr] lg:gap-12 xl:gap-16">
                    <!-- Left: content -->
                    <div class="text-center lg:text-left">
                        <div class="mb-4 flex flex-wrap items-center justify-center gap-3 lg:justify-start">
                            <div class="brand-badge inline-flex shadow-sm shadow-indigo-500/10">
                                <span class="relative flex h-2 w-2">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-cyan-400 opacity-75" />
                                    <span class="relative inline-flex h-2 w-2 rounded-full bg-gradient-to-r from-indigo-500 to-cyan-400" />
                                </span>
                                Enterprise lead distribution software
                            </div>
                            <SystemStatusBadge v-if="systemStatus" :status="systemStatus" />
                        </div>

                        <h1 class="text-balance text-4xl font-extrabold leading-[1.05] tracking-tight text-slate-900 marketing-dark:text-white sm:text-5xl lg:text-[3.4rem] lg:leading-[1.04] xl:text-6xl">
                            Turn every lead into
                            <span class="whitespace-nowrap">
                                <span
                                    :key="currentWord"
                                    class="brand-gradient-text inline-block w-[7.5ch] text-left animate-fade-in-up"
                                >{{ currentWord }}</span><span class="text-slate-700 marketing-dark:text-slate-200"> at scale</span>
                            </span>
                        </h1>

                        <p class="mx-auto mt-6 max-w-xl text-lg leading-relaxed text-slate-600 marketing-dark:text-slate-400 lg:mx-0 lg:text-[1.125rem]">
                            Capture, validate, and distribute leads in milliseconds with ping-tree routing, isolated partner platforms, and full delivery audit.
                        </p>

                        <div class="mt-9 flex flex-col items-stretch gap-3 sm:flex-row sm:items-center lg:justify-start">
                            <a href="#demo" class="brand-btn-primary group inline-flex items-center justify-center gap-2 px-8 py-4 text-base shadow-indigo-500/25">
                                Book a Demo
                                <svg class="h-5 w-5 transition group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </a>
                            <Link
                                :href="route('pricing')"
                                class="brand-btn-secondary inline-flex items-center justify-center px-8 py-4 text-base"
                            >
                                View Pricing
                            </Link>
                            <Link
                                v-if="canLogin"
                                :href="signInUrl"
                                class="brand-btn-secondary inline-flex items-center justify-center px-8 py-4 text-base"
                            >
                                {{ isAuthenticated ? 'Go to Platform' : 'Sign In' }}
                            </Link>
                        </div>

                        <a href="#features" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-600 transition hover:text-indigo-500 marketing-dark:text-indigo-400 marketing-dark:hover:text-cyan-300">
                            Explore features
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </a>

                        <div class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:gap-4">
                            <div
                                v-for="item in heroStats"
                                :key="item.label"
                                class="hero-stat-chip rounded-2xl border border-slate-200/80 bg-white/70 p-3 text-left backdrop-blur-sm marketing-dark:border-white/10 marketing-dark:bg-slate-900/40"
                            >
                                <div class="mb-2 inline-flex rounded-lg bg-indigo-500/10 p-1.5 text-indigo-600 marketing-dark:text-indigo-400">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                                    </svg>
                                </div>
                                <p class="text-lg font-bold leading-none text-slate-900 marketing-dark:text-white">{{ item.value }}</p>
                                <p class="mt-1 text-xs text-slate-500 marketing-dark:text-slate-400">{{ item.label }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Right: dashboard preview -->
                    <div class="relative mx-auto w-full max-w-xl lg:mx-0 lg:max-w-none">
                        <div class="hero-panel-glow absolute -inset-8 rounded-[2.5rem] bg-gradient-to-br from-indigo-500/20 via-violet-500/10 to-cyan-400/20 blur-3xl" />

                        <!-- Floating notification -->
                        <div class="absolute -right-2 top-8 z-20 hidden animate-float rounded-xl border border-emerald-500/30 bg-slate-900/95 px-4 py-3 shadow-xl shadow-emerald-500/10 backdrop-blur-md sm:block lg:-right-6">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-400">Lead sold</p>
                            <p class="mt-0.5 text-sm font-semibold text-white">£28.50 · Farmers Direct</p>
                            <p class="mt-0.5 text-xs text-slate-400">Ping-post · 42ms</p>
                        </div>

                        <div class="hero-panel relative overflow-hidden rounded-2xl border border-slate-200/60 bg-slate-950 shadow-2xl shadow-indigo-900/20 ring-1 ring-slate-900/10 marketing-dark:border-white/10">
                            <div class="flex items-center justify-between border-b border-white/10 bg-slate-900/90 px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="h-3 w-3 rounded-full bg-red-500/80" />
                                    <div class="h-3 w-3 rounded-full bg-amber-500/80" />
                                    <div class="h-3 w-3 rounded-full bg-emerald-500/80" />
                                    <span class="ml-2 text-xs font-medium text-slate-400">Campaign Dashboard</span>
                                </div>
                                <span class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-400">Live</span>
                            </div>

                            <div class="grid grid-cols-2 gap-3 p-4 sm:p-5">
                                <div
                                    v-for="(stat, i) in stats"
                                    :key="stat.label"
                                    class="rounded-xl border border-white/10 bg-gradient-to-br from-slate-800/80 to-slate-900/60 p-4"
                                    :class="i === 0 && 'ring-1 ring-indigo-500/30'"
                                >
                                    <p class="text-xl font-bold text-white sm:text-2xl">{{ stat.value }}</p>
                                    <p class="mt-1 text-[11px] uppercase tracking-wide text-slate-500">{{ stat.label }}</p>
                                </div>
                            </div>

                            <div class="border-t border-white/10 bg-slate-900/50 p-4 sm:p-5">
                                <div class="mb-4 flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-white">Ping-tree auction</p>
                                        <p class="text-xs text-slate-500">Auto Insurance UK · Tier 1</p>
                                    </div>
                                    <span class="flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse" />
                                        Processing
                                    </span>
                                </div>
                                <div class="space-y-2.5">
                                    <div
                                        v-for="bid in liveBids"
                                        :key="bid.buyer"
                                        class="rounded-lg bg-slate-800/60 px-3 py-2.5"
                                    >
                                        <div class="mb-1.5 flex items-center justify-between text-xs">
                                            <span class="font-medium text-slate-300">{{ bid.buyer }}</span>
                                            <span class="font-semibold text-emerald-400">£{{ bid.amount.toFixed(2) }}</span>
                                        </div>
                                        <div class="h-1.5 overflow-hidden rounded-full bg-slate-700/80">
                                            <div
                                                class="h-full rounded-full bg-gradient-to-r from-violet-500 via-indigo-500 to-cyan-400"
                                                :style="{ width: `${bid.width}%` }"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Floating metric chip -->
                        <div class="absolute -bottom-4 -left-2 z-20 hidden animate-float-delayed rounded-xl border border-emerald-500/25 bg-white/95 px-4 py-3 shadow-lg shadow-emerald-500/10 backdrop-blur-md marketing-dark:border-emerald-500/30 marketing-dark:bg-slate-900/95 sm:block lg:-left-6">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-600 marketing-dark:text-emerald-400">Route time</p>
                            <p class="text-lg font-bold text-slate-900 marketing-dark:text-white">38<span class="text-sm font-semibold text-slate-500">ms</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Verticals marquee -->
        <section class="overflow-hidden border-y border-slate-200/80 bg-slate-50/80 py-6 marketing-dark:border-white/10 marketing-dark:bg-slate-900/40">
            <div class="mb-3 text-center">
                <span class="text-xs font-semibold uppercase tracking-widest text-slate-400 marketing-dark:text-slate-500">Trusted across verticals</span>
            </div>
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 z-10 w-16 bg-gradient-to-r from-slate-50 marketing-dark:from-slate-900/80" />
                <div class="pointer-events-none absolute inset-y-0 right-0 z-10 w-16 bg-gradient-to-l from-slate-50 marketing-dark:from-slate-900/80" />
                <div class="flex w-max animate-marquee gap-4">
                    <span
                        v-for="(v, i) in [...verticals, ...verticals]"
                        :key="`${v}-${i}`"
                        class="brand-pill whitespace-nowrap"
                    >
                        {{ v }}
                    </span>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="py-24 md:py-32">
            <div class="mx-auto max-w-7xl px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="brand-kicker">Platform capabilities</p>
                    <h2 class="mt-3 text-4xl font-bold tracking-tight text-slate-900 marketing-dark:text-white md:text-5xl">Built for lead sellers, buyers &amp; brokers</h2>
                    <p class="mt-4 text-lg text-slate-600 marketing-dark:text-slate-400">Everything you need to run a high-performance lead distribution network — from ingest to payout.</p>
                </div>

                <div class="mt-16 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="f in features"
                        :key="f.title"
                        class="brand-card group relative overflow-hidden"
                    >
                        <div :class="['mb-5 inline-flex rounded-xl bg-gradient-to-br p-3', f.color]">
                            <svg v-if="f.icon === 'bolt'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <svg v-else-if="f.icon === 'tree'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                            </svg>
                            <svg v-else-if="f.icon === 'shield'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <svg v-else-if="f.icon === 'building'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <svg v-else-if="f.icon === 'users'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <svg v-else-if="f.icon === 'pixel'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                            </svg>
                            <svg v-else-if="f.icon === 'automation'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <svg v-else-if="f.icon === 'code'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                            </svg>
                            <svg v-else class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-slate-900 marketing-dark:text-white">{{ f.title }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600 marketing-dark:text-slate-400">{{ f.desc }}</p>
                    </div>
                </div>

                <div class="mt-8 overflow-hidden rounded-2xl border border-indigo-200/80 bg-gradient-to-br from-indigo-50/90 via-white to-violet-50/80 p-6 shadow-sm marketing-dark:border-indigo-500/25 marketing-dark:from-indigo-950/40 marketing-dark:via-slate-900/60 marketing-dark:to-violet-950/30 md:p-8">
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex gap-5">
                            <div class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500/25 via-violet-500/20 to-cyan-500/15 text-indigo-600 marketing-dark:text-indigo-400">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600 marketing-dark:text-indigo-400">Last resort · we can build it</p>
                                <h3 class="mt-1 text-xl font-semibold text-slate-900 marketing-dark:text-white md:text-2xl">{{ customSolution.title }}</h3>
                                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600 marketing-dark:text-slate-400">{{ customSolution.desc }}</p>
                                <ul class="mt-4 space-y-2">
                                    <li
                                        v-for="point in customSolution.points"
                                        :key="point"
                                        class="flex items-start gap-2 text-sm text-slate-700 marketing-dark:text-slate-300"
                                    >
                                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-indigo-500 marketing-dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        {{ point }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-col gap-3 sm:flex-row lg:flex-col">
                            <a href="#demo" class="brand-btn-primary inline-flex items-center justify-center px-6 py-3 text-sm">
                                Talk to us
                            </a>
                            <Link
                                :href="route('pricing')"
                                class="brand-btn-secondary inline-flex items-center justify-center px-6 py-3 text-sm"
                            >
                                Enterprise pricing
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Testimonials -->
        <section class="brand-section-alt py-24 md:py-28">
            <div class="mx-auto max-w-7xl px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="brand-kicker">Customer stories</p>
                    <h2 class="mt-3 text-4xl font-bold tracking-tight text-slate-900 marketing-dark:text-white md:text-5xl">Built for operators who move fast</h2>
                    <p class="mt-4 text-lg text-slate-600 marketing-dark:text-slate-400">Lead sellers, brokers, and affiliate networks use PBE to replace spreadsheets and legacy routers.</p>
                </div>
                <div class="mt-14 grid gap-6 md:grid-cols-3">
                    <article
                        v-for="t in testimonials"
                        :key="t.name"
                        class="brand-card relative flex flex-col"
                    >
                        <div class="mb-4 text-4xl leading-none text-indigo-300/80 marketing-dark:text-indigo-500/60">"</div>
                        <p class="flex-1 text-sm leading-relaxed text-slate-700 marketing-dark:text-slate-300">{{ t.quote }}</p>
                        <div class="mt-6 flex items-end justify-between gap-4 border-t border-slate-100 pt-5 marketing-dark:border-white/10">
                            <div>
                                <p class="font-semibold text-slate-900 marketing-dark:text-white">{{ t.name }}</p>
                                <p class="text-xs text-slate-500 marketing-dark:text-slate-400">{{ t.role }} · {{ t.company }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-600 marketing-dark:text-emerald-400">{{ t.metric }}</span>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <!-- Ping Tree -->
        <section id="ping-tree" class="brand-section-alt py-24 md:py-32">
            <div class="mx-auto max-w-7xl px-6">
                <div class="grid items-center gap-12 lg:grid-cols-2">
                    <div>
                        <p class="brand-kicker text-cyan-600 marketing-dark:text-cyan-400">Real-time distribution</p>
                        <h2 class="mt-3 text-4xl font-bold tracking-tight text-slate-900 marketing-dark:text-white">Ping-tree &amp; ping-post routing</h2>
                        <p class="mt-4 text-lg text-slate-600 marketing-dark:text-slate-400">
                            Route leads through tiered buyer groups in milliseconds. Waterfall priority, parallel auctions, sequential ping-post, weighted splits, round-robin, and hybrid tiered groups — all configurable per campaign.
                        </p>
                        <ul class="mt-6 space-y-3 text-sm text-slate-700 marketing-dark:text-slate-300">
                            <li class="flex items-center gap-2">
                                <span class="h-1.5 w-1.5 rounded-full bg-cyan-400" />
                                Live operations dashboard with ping-post audit logs
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="h-1.5 w-1.5 rounded-full bg-cyan-400" />
                                Visual ping-tree builder with tier management
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="h-1.5 w-1.5 rounded-full bg-cyan-400" />
                                Ping-post delivery method with configurable endpoints
                            </li>
                        </ul>
                    </div>
                    <div class="relative mx-auto w-full max-w-lg lg:max-w-none">
                        <div class="pointer-events-none absolute -inset-6 rounded-[2rem] bg-gradient-to-br from-violet-500/20 via-indigo-500/10 to-cyan-500/15 blur-2xl" />

                        <div class="relative overflow-hidden rounded-2xl border border-slate-200/70 bg-slate-950 shadow-2xl shadow-indigo-900/25 ring-1 ring-slate-900/5 marketing-dark:border-white/10">
                            <div class="flex items-center justify-between border-b border-white/10 bg-slate-900/90 px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="h-2.5 w-2.5 rounded-full bg-red-500/80" />
                                    <div class="h-2.5 w-2.5 rounded-full bg-amber-500/80" />
                                    <div class="h-2.5 w-2.5 rounded-full bg-emerald-500/80" />
                                    <span class="ml-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Example ping tree</span>
                                </div>
                                <span class="flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-emerald-400">
                                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400" />
                                    Live route
                                </span>
                            </div>

                            <div class="space-y-0 p-4 sm:p-5">
                                <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-slate-800/50 px-3 py-2.5">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-500/20 text-indigo-300">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-semibold text-white">Lead ingested</p>
                                        <p class="truncate text-[11px] text-slate-500">Auto Insurance UK · API · 38ms</p>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-indigo-500/15 px-2 py-0.5 text-[10px] font-semibold text-indigo-300">Enter tree</span>
                                </div>

                                <template v-for="tier in pingTreeTiers" :key="tier.tier">
                                    <div class="flex justify-center py-2">
                                        <div class="flex flex-col items-center gap-1">
                                            <div class="h-3 w-px bg-gradient-to-b from-slate-600 to-slate-500" />
                                            <span
                                                :class="[
                                                    'rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                                    tier.sold ? 'bg-emerald-500/15 text-emerald-400' : 'bg-slate-800 text-slate-500',
                                                ]"
                                            >
                                                {{ tier.sold ? 'Sold · £28.50' : 'If unsold ↓' }}
                                            </span>
                                            <div class="h-3 w-px bg-gradient-to-b from-slate-500 to-transparent" />
                                        </div>
                                    </div>

                                    <div :class="['relative', tier.indent]">
                                        <div
                                            :class="[
                                                'rounded-xl border p-4 transition',
                                                tier.cardClass,
                                                tier.sold && 'shadow-lg shadow-violet-500/10',
                                            ]"
                                        >
                                            <div class="flex items-start gap-3">
                                                <div :class="['flex h-10 w-10 shrink-0 items-center justify-center rounded-xl', tier.iconBg]">
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tier.iconPath" />
                                                    </svg>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="rounded-md bg-white/10 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-300">
                                                            Tier {{ tier.tier }}
                                                        </span>
                                                        <span class="rounded-md bg-white/5 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">{{ tier.mode }}</span>
                                                        <span
                                                            v-if="tier.sold"
                                                            class="ml-auto rounded-full bg-emerald-500/20 px-2 py-0.5 text-[10px] font-semibold text-emerald-300"
                                                        >
                                                            Winner
                                                        </span>
                                                    </div>
                                                    <p class="mt-1.5 text-sm font-semibold text-white">{{ tier.title }}</p>
                                                    <p class="mt-0.5 text-xs text-slate-400">{{ tier.subtitle }}</p>
                                                    <p class="mt-1 text-[11px] text-slate-500">{{ tier.detail }}</p>
                                                </div>
                                            </div>

                                            <div v-if="tier.showBids" class="mt-3 space-y-2 border-t border-white/10 pt-3">
                                                <div
                                                    v-for="(bid, bidIndex) in liveBids"
                                                    :key="bid.buyer"
                                                    class="rounded-lg bg-slate-900/60 px-3 py-2"
                                                >
                                                    <div class="mb-1 flex items-center justify-between text-[11px]">
                                                        <span :class="bidIndex === 0 ? 'font-semibold text-white' : 'text-slate-400'">{{ bid.buyer }}</span>
                                                        <span :class="bidIndex === 0 ? 'font-bold text-emerald-400' : 'text-slate-500'">£{{ bid.amount.toFixed(2) }}</span>
                                                    </div>
                                                    <div class="h-1 overflow-hidden rounded-full bg-slate-800">
                                                        <div
                                                            class="h-full rounded-full bg-gradient-to-r from-violet-500 via-indigo-500 to-cyan-400"
                                                            :class="bidIndex === 0 && 'shadow-sm shadow-emerald-500/30'"
                                                            :style="{ width: `${bid.width}%` }"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <div class="mt-4 flex items-center justify-between rounded-xl border border-dashed border-white/10 bg-slate-900/40 px-3 py-2.5">
                                    <p class="text-[11px] text-slate-500">Configurable per campaign · up to 10 tiers</p>
                                    <span class="text-[11px] font-semibold text-cyan-400">42ms total</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Comparison -->
        <section id="compare" class="py-24 md:py-32">
            <div class="mx-auto max-w-7xl px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="brand-kicker">Why switch</p>
                    <h2 class="mt-3 text-4xl font-bold tracking-tight text-slate-900 marketing-dark:text-white md:text-5xl">PBE vs legacy lead routers</h2>
                    <p class="mt-4 text-lg text-slate-600 marketing-dark:text-slate-400">Everything you need in one platform — not a patchwork of scripts, spreadsheets, and third-party tools.</p>
                </div>
                <div class="mt-12 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm marketing-dark:border-white/10 marketing-dark:bg-slate-900/50">
                    <div class="grid grid-cols-[1fr_auto_auto] gap-0 border-b border-slate-200 bg-slate-50 px-6 py-4 text-xs font-semibold uppercase tracking-wider text-slate-500 marketing-dark:border-white/10 marketing-dark:bg-slate-800/50 marketing-dark:text-slate-400">
                        <span>Capability</span>
                        <span class="w-24 text-center text-indigo-600 marketing-dark:text-indigo-400">PBE</span>
                        <span class="w-24 text-center">Legacy</span>
                    </div>
                    <div
                        v-for="(row, i) in comparisonRows"
                        :key="row.feature"
                        :class="[
                            'grid grid-cols-[1fr_auto_auto] items-center gap-0 px-6 py-4 text-sm',
                            i % 2 === 0 ? 'bg-white marketing-dark:bg-transparent' : 'bg-slate-50/50 marketing-dark:bg-slate-800/20',
                        ]"
                    >
                        <span class="text-slate-700 marketing-dark:text-slate-300">{{ row.feature }}</span>
                        <span class="flex w-24 justify-center">
                            <svg v-if="row.pbe === true" class="h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </span>
                        <span class="flex w-24 justify-center text-slate-400">
                            <svg v-if="row.legacy === false" class="h-5 w-5 text-slate-300 marketing-dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            <span v-else class="text-xs font-medium text-slate-500">{{ row.legacy }}</span>
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Billing -->
        <section id="billing" class="py-24 md:py-32">
            <div class="mx-auto max-w-7xl px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="brand-kicker text-emerald-600 marketing-dark:text-emerald-400">Financial controls</p>
                    <h2 class="mt-3 text-4xl font-bold tracking-tight text-slate-900 marketing-dark:text-white">Billing for every role</h2>
                    <p class="mt-4 text-lg text-slate-600 marketing-dark:text-slate-400">
                        Buyer credit ledgers, prepay enforcement, supplier payout tracking, and admin top-ups — all visible in dedicated billing sections for admins, buyers, and suppliers.
                    </p>
                </div>
                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <div class="brand-card-sm">
                        <h3 class="font-semibold text-slate-900 marketing-dark:text-white">Admin billing</h3>
                        <p class="mt-2 text-sm text-slate-600 marketing-dark:text-slate-400">Credit pool overview, per-buyer ledgers, admin top-ups, and prepay settings.</p>
                    </div>
                    <div class="brand-card-sm">
                        <h3 class="font-semibold text-slate-900 marketing-dark:text-white">Buyer portal</h3>
                        <p class="mt-2 text-sm text-slate-600 marketing-dark:text-slate-400">Credit balance, transaction history, and prepay status for every buyer account.</p>
                    </div>
                    <div class="brand-card-sm">
                        <h3 class="font-semibold text-slate-900 marketing-dark:text-white">Supplier payouts</h3>
                        <p class="mt-2 text-sm text-slate-600 marketing-dark:text-slate-400">Revenue earned per sold lead with monthly payout summaries.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Postbacks -->
        <section id="postbacks" class="brand-section-alt py-24 md:py-32">
            <div class="mx-auto max-w-7xl px-6">
                <div class="grid items-center gap-12 lg:grid-cols-2">
                    <div>
                        <p class="brand-kicker text-cyan-600 marketing-dark:text-teal-400">Affiliate tracking</p>
                        <h2 class="mt-3 text-4xl font-bold tracking-tight text-slate-900 marketing-dark:text-white">Postback Manager</h2>
                        <p class="mt-4 text-lg text-slate-600 marketing-dark:text-slate-400">
                            Fire conversion pixels and affiliate postbacks when leads are accepted, sold, rejected, or delivered. Scope rules per supplier or campaign — just like enterprise lead platforms.
                        </p>
                        <ul class="mt-8 space-y-3">
                            <li v-for="item in ['GET pixel URLs with [field] tag interpolation', 'Per-supplier and per-campaign scoping', 'Events: accepted, sold, rejected, unsold, delivery success', 'Full postback audit log with HTTP status']" :key="item" class="flex items-center gap-3 text-slate-700 marketing-dark:text-slate-300">
                                <svg class="h-5 w-5 shrink-0 text-teal-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ item }}
                            </li>
                        </ul>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-900 p-6 font-mono text-sm text-white shadow-lg marketing-dark:border-white/10">
                        <p class="text-xs text-slate-500">Example postback URL</p>
                        <p class="mt-3 break-all text-cyan-400">https://tracker.network.com/pixel?</p>
                        <p class="text-emerald-400">lead_id=[lead_uuid]&amp;revenue=[revenue]&amp;sid=[sid]</p>
                        <p class="mt-6 text-xs text-slate-500">Fires on: lead.sold · HTTP GET · 200 OK · 42ms</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Security -->
        <section id="security" class="py-24 md:py-32">
            <div class="mx-auto max-w-7xl px-6">
                <div class="grid items-center gap-12 lg:grid-cols-2">
                    <div>
                        <p class="brand-kicker text-violet-600 marketing-dark:text-violet-400">Enterprise-ready</p>
                        <h2 class="mt-3 text-4xl font-bold tracking-tight text-slate-900 marketing-dark:text-white">Security &amp; compliance built in</h2>
                        <p class="mt-4 text-lg text-slate-600 marketing-dark:text-slate-400">
                            Tenant isolation, scoped API keys, comprehensive audit logs, and billing enforcement — designed for agencies managing multiple partner networks.
                        </p>
                        <Link
                            :href="route('status.index')"
                            class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 transition hover:text-indigo-500 marketing-dark:text-indigo-400"
                        >
                            View system status
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </Link>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div
                            v-for="item in securityFeatures"
                            :key="item.title"
                            class="brand-card-sm"
                        >
                            <div class="brand-icon-wrap mb-4">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                                </svg>
                            </div>
                            <h3 class="font-semibold text-slate-900 marketing-dark:text-white">{{ item.title }}</h3>
                            <p class="mt-2 text-sm text-slate-600 marketing-dark:text-slate-400">{{ item.desc }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- How it works -->
        <section id="how-it-works" class="brand-section-alt py-24 md:py-32">
            <div class="mx-auto max-w-7xl px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="brand-kicker">The pipeline</p>
                    <h2 class="mt-3 text-4xl font-bold tracking-tight text-slate-900 marketing-dark:text-white">How it works</h2>
                    <p class="mt-4 text-lg text-slate-600 marketing-dark:text-slate-400">From first touch to buyer delivery in four automated steps.</p>
                </div>

                <div class="relative mt-16 grid gap-8 md:grid-cols-4">
                    <div class="absolute left-0 right-0 top-12 hidden h-0.5 bg-gradient-to-r from-violet-600/0 via-indigo-500/50 to-cyan-500/0 md:block" />
                    <div v-for="(step, i) in steps" :key="step.title" class="relative text-center">
                        <div class="relative z-10 mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-600 to-indigo-700 shadow-lg shadow-indigo-500/30">
                            <svg class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="step.icon" />
                            </svg>
                            <span class="absolute -right-1 -top-1 flex h-6 w-6 items-center justify-center rounded-full bg-cyan-500 text-xs font-bold text-slate-950">{{ i + 1 }}</span>
                        </div>
                        <h3 class="text-lg font-semibold text-slate-900 marketing-dark:text-white">{{ step.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 marketing-dark:text-slate-400">{{ step.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Integrations -->
        <section id="integrations" class="brand-section-alt py-20 md:py-24">
            <div class="mx-auto max-w-7xl px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="brand-kicker">Connect your stack</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 marketing-dark:text-white md:text-4xl">Integrations &amp; ingest channels</h2>
                    <p class="mt-4 text-slate-600 marketing-dark:text-slate-400">API-first architecture with the channels your suppliers and buyers already use.</p>
                </div>
                <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="item in integrations"
                        :key="item.name"
                        class="brand-card-sm flex items-start gap-4"
                    >
                        <div class="brand-icon-wrap shrink-0">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-slate-900 marketing-dark:text-white">{{ item.name }}</h3>
                            <p class="mt-1 text-sm text-slate-600 marketing-dark:text-slate-400">{{ item.desc }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SDK & API -->
        <section id="sdk" class="border-t border-slate-200 py-24 marketing-dark:border-white/10 md:py-32">
            <div class="mx-auto max-w-7xl px-6">
                <div class="grid items-center gap-12 lg:grid-cols-2">
                    <div class="order-2 lg:order-1">
                        <div class="relative">
                            <div class="absolute -inset-4 rounded-3xl bg-gradient-to-r from-sky-600/10 to-violet-500/10 blur-xl" />
                            <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-slate-900 shadow-2xl">
                                <div class="flex items-center gap-2 border-b border-white/10 px-4 py-3">
                                    <div class="h-3 w-3 rounded-full bg-red-500/80" />
                                    <div class="h-3 w-3 rounded-full bg-amber-500/80" />
                                    <div class="h-3 w-3 rounded-full bg-emerald-500/80" />
                                    <span class="ml-2 text-xs text-slate-500">ingest.js</span>
                                </div>
                                <pre class="overflow-x-auto p-6 text-sm leading-relaxed"><code class="text-slate-300"><span class="text-violet-400">import</span> { createClient } <span class="text-violet-400">from</span> <span class="text-emerald-400">'/sdk/pbe-leads.js'</span>;

<span class="text-violet-400">const</span> pbe = <span class="text-cyan-400">createClient</span>({
  apiKey: <span class="text-emerald-400">'pk_live_••••••••'</span>,
  baseUrl: <span class="text-emerald-400">'/api/v1'</span>,
});

<span class="text-violet-400">const</span> result = <span class="text-violet-400">await</span> pbe.<span class="text-cyan-400">ingestLead</span>({
  campaign_ref: <span class="text-emerald-400">'auto-insurance-uk'</span>,
  email: <span class="text-emerald-400">'lead@example.com'</span>,
  sync: <span class="text-amber-300">true</span>,
});</code></pre>
                            </div>
                        </div>
                    </div>
                    <div class="order-1 lg:order-2" id="api">
                        <p class="brand-kicker text-indigo-600 marketing-dark:text-sky-400">Developer-first</p>
                        <h2 class="mt-3 text-4xl font-bold tracking-tight text-slate-900 marketing-dark:text-white">REST API &amp; client SDK</h2>
                        <p class="mt-4 text-lg text-slate-600 marketing-dark:text-slate-400">
                            Ship integrations fast with scoped API keys, sync/async ingest, and lightweight JavaScript and PHP client libraries — no heavy SDK install required.
                        </p>
                        <ul class="mt-8 space-y-3">
                            <li v-for="item in ['JavaScript SDK at /sdk/pbe-leads.js', 'PHP client in sdk/php/PbeClient.php', 'Sync & async lead ingest', 'Ping/post auction simulators built in']" :key="item" class="flex items-center gap-3 text-slate-700 marketing-dark:text-slate-300">
                                <svg class="h-5 w-5 shrink-0 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ item }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing preview -->
        <section id="pricing-preview" class="py-24 md:py-28">
            <div class="mx-auto max-w-7xl px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="brand-kicker">Transparent pricing</p>
                    <h2 class="mt-3 text-4xl font-bold tracking-tight text-slate-900 marketing-dark:text-white md:text-5xl">Plans that scale with volume</h2>
                    <p class="mt-4 text-lg text-slate-600 marketing-dark:text-slate-400">Every tier includes ping-tree routing, API access, validation, and buyer management.</p>
                </div>
                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <div
                        v-for="tier in pricingPreview"
                        :key="tier.name"
                        :class="[
                            'brand-card relative text-center',
                            tier.popular && 'ring-2 ring-indigo-500/50 shadow-lg shadow-indigo-500/10',
                        ]"
                    >
                        <span
                            v-if="tier.popular"
                            class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-gradient-to-r from-violet-600 to-indigo-600 px-3 py-0.5 text-xs font-semibold text-white"
                        >
                            Most popular
                        </span>
                        <h3 class="text-lg font-semibold text-slate-900 marketing-dark:text-white">{{ tier.name }}</h3>
                        <p class="mt-2 text-3xl font-bold brand-stat-value">{{ tier.price }}</p>
                        <p class="mt-1 text-sm text-slate-500 marketing-dark:text-slate-400">/ month</p>
                        <p class="mt-4 text-sm font-medium text-indigo-600 marketing-dark:text-indigo-400">{{ tier.highlight }}</p>
                        <Link
                            :href="route('pricing')"
                            class="brand-btn-secondary mt-6 inline-flex w-full items-center justify-center px-4 py-2.5 text-sm"
                        >
                            See full details
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section id="faq" class="brand-section-alt py-24 md:py-28">
            <div class="mx-auto max-w-3xl px-6">
                <div class="text-center">
                    <p class="brand-kicker">FAQ</p>
                    <h2 class="mt-3 text-4xl font-bold tracking-tight text-slate-900 marketing-dark:text-white">Common questions</h2>
                </div>
                <div class="mt-12 divide-y divide-slate-200 marketing-dark:divide-white/10">
                    <div v-for="(item, i) in faqs" :key="item.q">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-4 py-5 text-left"
                            @click="toggleFaq(i)"
                        >
                            <span class="font-semibold text-slate-900 marketing-dark:text-white">{{ item.q }}</span>
                            <svg
                                :class="['h-5 w-5 shrink-0 text-slate-400 transition', openFaq === i && 'rotate-180']"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div
                            v-show="openFaq === i"
                            class="pb-5 text-sm leading-relaxed text-slate-600 marketing-dark:text-slate-400"
                        >
                            {{ item.a }}
                        </div>
                    </div>
                </div>
                <p class="mt-8 text-center text-sm text-slate-500 marketing-dark:text-slate-400">
                    More answers in the
                    <Link :href="route('help.index')" class="font-semibold text-indigo-600 hover:text-indigo-500 marketing-dark:text-indigo-400">Help Centre</Link>.
                </p>
            </div>
        </section>

        <!-- Book a Demo -->
        <section id="demo" class="brand-section-alt py-24 md:py-32">
            <div class="mx-auto max-w-7xl px-6">
                <div class="grid items-center gap-12 lg:grid-cols-2">
                    <div>
                        <p class="brand-kicker">Get started</p>
                        <h2 class="mt-3 text-4xl font-bold tracking-tight text-slate-900 marketing-dark:text-white">Book a personalised demo</h2>
                        <p class="mt-4 text-lg text-slate-600 marketing-dark:text-slate-400">
                            See ping-tree routing, multi-tenant platforms, buyer portals, and real-time distribution in action. Our team will walk you through setup for your vertical.
                        </p>
                        <ul class="mt-8 space-y-3">
                            <li v-for="item in ['30-minute live walkthrough', 'Custom vertical setup advice', 'API integration overview', 'Pricing & onboarding plan']" :key="item" class="flex items-center gap-3 text-slate-700 marketing-dark:text-slate-300">
                                <svg class="h-5 w-5 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ item }}
                            </li>
                        </ul>
                    </div>
                    <div class="brand-card">
                        <form @submit.prevent="submitDemo" class="space-y-4">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-slate-700 marketing-dark:text-slate-300">Full name</label>
                                <input v-model="demoForm.name" type="text" required class="form-input w-full" placeholder="Jane Smith" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-slate-700 marketing-dark:text-slate-300">Work email</label>
                                <input v-model="demoForm.email" type="email" required class="form-input w-full" placeholder="jane@company.com" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-slate-700 marketing-dark:text-slate-300">Company</label>
                                <input v-model="demoForm.company" type="text" required class="form-input w-full" placeholder="Your company name" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-slate-700 marketing-dark:text-slate-300">Tell us about your use case</label>
                                <textarea v-model="demoForm.message" rows="3" class="form-input w-full" placeholder="Vertical, volume, integrations..." />
                            </div>
                            <button
                                type="submit"
                                :disabled="demoForm.processing"
                                class="brand-btn-primary flex w-full items-center justify-center px-4 py-3.5 text-sm disabled:opacity-60"
                            >
                                {{ demoForm.processing ? 'Sending...' : 'Request Demo' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <!-- Multi-tenant CTA -->
        <section class="relative overflow-hidden py-24">
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-violet-100/80 via-indigo-50/80 to-cyan-50/60 marketing-dark:from-violet-950/50 marketing-dark:via-indigo-950/50 marketing-dark:to-slate-950" />
            <div class="pointer-events-none absolute left-1/2 top-0 h-64 w-96 -translate-x-1/2 rounded-full bg-indigo-300/30 blur-3xl marketing-dark:bg-indigo-600/20" />
            <div class="relative mx-auto max-w-4xl px-6 text-center">
                <h2 class="text-4xl font-bold tracking-tight text-slate-900 marketing-dark:text-white md:text-5xl">Multi-tenant partner platforms</h2>
                <p class="mx-auto mt-5 max-w-2xl text-lg text-slate-600 marketing-dark:text-slate-400">
                    Super-admins host multiple isolated partner businesses from one account — each on its own subdomain with separate buyers, suppliers, and financials. UK, US, and other geographies are handled with campaigns inside a platform, not as separate tenants.
                </p>
                <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <a href="#demo" class="brand-btn-primary px-8 py-4 text-base">Book a Demo</a>
                    <Link v-if="canLogin" :href="signInUrl" class="brand-btn-secondary px-8 py-4 text-base">
                        {{ isAuthenticated ? 'Go to Platform' : 'Sign In to Platform' }}
                    </Link>
                </div>
            </div>
        </section>
        </div>

        <MarketingFooter :can-login="canLogin" />
        <ToastHost />
    </div>
</template>
