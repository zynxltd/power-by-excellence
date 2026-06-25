<script setup>
import MarketingNav from '@/Components/Marketing/MarketingNav.vue';
import MarketingFooter from '@/Components/Marketing/MarketingFooter.vue';
import SeoHead from '@/Components/SeoHead.vue';
import { useMarketingTheme } from '@/Composables/useMarketingTheme';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    canLogin: Boolean,
    seo: { type: Object, default: () => ({}) },
});

const page = usePage();
const { marketingTheme } = useMarketingTheme();
const demoSuccess = computed(() => page.props.flash?.demo_success);
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
        desc: 'Isolated partner platforms with their own campaigns, buyers, suppliers, API keys, and financial reporting.',
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

const steps = [
    { title: 'Capture', desc: 'Leads arrive via API, CSV, or supplier integration with SID/SSID source tracking.', icon: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12' },
    { title: 'Validate', desc: 'Rules engine, dedupe, and suppression checks filter junk before distribution begins.', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
    { title: 'Distribute', desc: 'Ping-tree engine routes to the highest-bidding or priority buyers in real-time.', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
    { title: 'Report', desc: 'Revenue, margins, and buyer feedback tracked per lead with a full audit trail.', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
];

const verticals = ['Auto Insurance', 'Solar', 'Home Services', 'Mortgage', 'Legal', 'Education', 'Healthcare', 'Finance'];
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
                        <div class="brand-badge mb-7 inline-flex shadow-sm shadow-indigo-500/10">
                            <span class="relative flex h-2 w-2">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-cyan-400 opacity-75" />
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-gradient-to-r from-indigo-500 to-cyan-400" />
                            </span>
                            Enterprise lead distribution software
                        </div>

                        <h1 class="text-balance text-4xl font-extrabold leading-[1.05] tracking-tight text-slate-900 marketing-dark:text-white sm:text-5xl lg:text-[3.4rem] lg:leading-[1.04] xl:text-6xl">
                            Turn every lead into
                            <span class="mt-1 block brand-gradient-text sm:inline sm:mt-0">revenue</span>
                            <span class="text-slate-700 marketing-dark:text-slate-200"> at scale</span>
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
                        <div class="absolute -bottom-4 -left-2 z-20 hidden animate-float-delayed rounded-xl border border-indigo-500/25 bg-white/95 px-4 py-3 shadow-lg shadow-indigo-500/10 backdrop-blur-md marketing-dark:border-indigo-500/30 marketing-dark:bg-slate-900/95 sm:block lg:-left-6">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-500">Queue depth</p>
                            <p class="text-lg font-bold text-slate-900 marketing-dark:text-white">2 pending</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats bar -->
        <section class="border-y border-slate-200/80 bg-slate-50/80 py-8 marketing-dark:border-white/10 marketing-dark:bg-slate-900/40">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-10 gap-y-3 px-6 text-sm text-slate-500 marketing-dark:text-slate-400">
                <span class="font-semibold uppercase tracking-widest text-slate-400 marketing-dark:text-slate-500">Trusted across</span>
                <span v-for="v in verticals.slice(0, 5)" :key="v" class="font-medium text-slate-600 marketing-dark:text-slate-300">{{ v }}</span>
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
                    <div class="brand-card-sm">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Example ping tree</p>
                        <div class="mt-4 space-y-3">
                            <div class="rounded-xl border border-violet-500/30 bg-violet-500/10 p-4">
                                <p class="text-sm font-semibold text-violet-300">Tier 1 — Parallel Auction</p>
                                <p class="mt-1 text-xs text-slate-400">Highest bid wins · Floor £10</p>
                            </div>
                            <div class="ml-6 rounded-xl border border-indigo-500/30 bg-indigo-500/10 p-4">
                                <p class="text-sm font-semibold text-indigo-300">Tier 2 — Waterfall</p>
                                <p class="mt-1 text-xs text-slate-400">Priority buyers in sequence</p>
                            </div>
                            <div class="ml-12 rounded-xl border border-cyan-500/30 bg-cyan-500/10 p-4">
                                <p class="text-sm font-semibold text-cyan-300">Tier 3 — Store Lead</p>
                                <p class="mt-1 text-xs text-slate-400">Fallback storage if unsold</p>
                            </div>
                        </div>
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

        <!-- Verticals -->
        <section class="py-16">
            <div class="mx-auto max-w-7xl px-6 text-center">
                <p class="text-sm font-semibold uppercase tracking-widest text-slate-500">Supported verticals</p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    <span
                        v-for="v in verticals"
                        :key="v"
                        class="brand-pill"
                    >
                        {{ v }}
                    </span>
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
                        <div v-if="demoSuccess" class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400">
                            {{ demoSuccess }}
                        </div>
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
                    Host multiple isolated lead networks from a single super-admin account. Each platform gets its own campaigns, buyers, suppliers, and financials.
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
    </div>
</template>
