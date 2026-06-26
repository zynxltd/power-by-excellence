<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import ToastHost from '@/Components/UI/ToastHost.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const tenant = computed(() => page.props.tenant);

const highlights = [
    'Real-time ping-tree distribution',
    'Multi-tenant partner platforms',
    'Buyer & supplier self-service portals',
    'Full delivery audit & webhooks',
];
</script>

<template>
    <div class="flex min-h-dvh">
        <!-- Brand panel -->
        <div class="relative hidden w-[45%] overflow-hidden bg-slate-950 lg:flex lg:flex-col lg:justify-between">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -left-20 top-0 h-96 w-96 rounded-full bg-violet-600/30 blur-3xl animate-pulse-slow" />
                <div class="absolute bottom-0 right-0 h-80 w-80 rounded-full bg-cyan-500/20 blur-3xl animate-pulse-slow animation-delay-2000" />
                <div class="absolute left-1/2 top-1/2 h-64 w-64 -translate-x-1/2 -translate-y-1/2 rounded-full bg-indigo-600/20 blur-3xl" />
                <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg%20width%3D%2260%22%20height%3D%2260%22%20viewBox%3D%220%200%2060%2060%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cg%20fill%3D%22none%22%20fill-rule%3D%22evenodd%22%3E%3Cg%20fill%3D%22%23ffffff%22%20fill-opacity%3D%220.03%22%3E%3Cpath%20d%3D%22M36%2034v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6%2034v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6%204V0H4v4H0v2h4v4h2V6h4V4H6z%22%2F%3E%3C%2Fg%3E%3C%2Fg%3E%3C%2Fsvg%3E')] opacity-60" />
            </div>

            <div class="relative z-10 p-10">
                <Link href="/">
                    <BrandLogo
                        size="lg"
                        :logo-url="tenant?.logo_url"
                        :brand-name="tenant?.display_name"
                    />
                </Link>
            </div>

            <div class="relative z-10 flex flex-1 flex-col justify-center px-10 pb-10">
                <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-indigo-400">Lead Distribution Platform</p>
                <h1 class="text-4xl font-bold leading-tight tracking-tight text-white xl:text-5xl">
                    Route every lead to the
                    <span class="bg-gradient-to-r from-violet-400 via-indigo-300 to-cyan-400 bg-clip-text text-transparent"> highest bidder</span>
                </h1>
                <p class="mt-5 max-w-md text-lg text-slate-400">
                    Capture, validate, and distribute leads in milliseconds with enterprise-grade ping-tree routing.
                </p>

                <ul class="mt-10 space-y-4">
                    <li v-for="item in highlights" :key="item" class="flex items-center gap-3 text-slate-300">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-500/20 text-indigo-400">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </span>
                        {{ item }}
                    </li>
                </ul>
            </div>

            <div class="relative z-10 border-t border-white/10 px-10 py-6">
                <div class="flex gap-8 text-sm text-slate-500">
                    <div>
                        <p class="text-2xl font-bold text-white">&lt;50ms</p>
                        <p>Queue processing</p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-white">99.9%</p>
                        <p>Platform uptime</p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-white">24/7</p>
                        <p>Delivery logging</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form panel -->
        <div class="flex w-full flex-col bg-white lg:w-[55%]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-4 sm:px-6 lg:border-b-0 lg:justify-end lg:py-6">
                <Link href="/" class="min-w-0 shrink lg:hidden">
                    <BrandLogo
                        size="sm"
                        variant="dark"
                        :logo-url="tenant?.logo_url"
                        :brand-name="tenant?.display_name"
                    />
                </Link>
                <Link
                    href="/"
                    class="shrink-0 text-sm font-medium text-slate-500 transition hover:text-slate-900"
                >
                    &larr; Back to homepage
                </Link>
            </div>

            <div class="flex flex-1 flex-col items-center justify-center px-4 pb-8 pt-2 sm:px-8 sm:pb-12 lg:px-12">
                <div class="w-full max-w-md">
                    <div v-if="$slots.header" class="mb-6 sm:mb-8 [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:tracking-tight [&_h2]:text-slate-900 sm:[&_h2]:text-3xl [&_p]:mt-2 [&_p]:text-slate-500">
                        <slot name="header" />
                    </div>
                    <slot />
                </div>
            </div>

            <div class="border-t border-slate-100 px-4 py-4 text-center text-xs text-slate-400 sm:px-6">
                &copy; {{ new Date().getFullYear() }} {{ tenant?.display_name ?? 'PowerByExcellence' }}. All rights reserved.
            </div>
        </div>
        <ToastHost />
    </div>
</template>
