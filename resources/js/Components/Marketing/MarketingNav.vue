<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import MarketingThemeToggle from '@/Components/Marketing/MarketingThemeToggle.vue';
import SystemStatusBadge from '@/Components/Marketing/SystemStatusBadge.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    canLogin: { type: Boolean, default: false },
    active: { type: String, default: '' },
});

const page = usePage();
const signInUrl = computed(() => page.props.urls?.marketingSignIn ?? route('login'));
const isAuthenticated = computed(() => !!page.props.auth?.user);
const systemStatus = computed(() => page.props.systemStatus);

const mobileOpen = ref(false);
const productOpen = ref(false);
const resourcesOpen = ref(false);

const closeMobile = () => {
    mobileOpen.value = false;
};

const navLinkClass = (isActive) => [
    'rounded-lg px-3 py-2 text-sm font-medium transition',
    isActive
        ? 'bg-gradient-to-r from-violet-600/20 to-indigo-600/20 text-white ring-1 ring-indigo-500/30'
        : 'text-slate-300 hover:bg-white/5 hover:text-white',
];
</script>

<template>
    <nav class="marketing-chrome fixed inset-x-0 top-0 z-50">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
            <Link href="/" class="shrink-0">
                <BrandLogo size="sm" variant="light" />
            </Link>

            <!-- Desktop -->
            <div class="hidden items-center gap-1 lg:flex">
                <div class="relative" @mouseenter="productOpen = true" @mouseleave="productOpen = false">
                    <button
                        type="button"
                        class="flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-violet-200"
                    >
                        Product
                        <svg class="h-4 w-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div
                        v-show="productOpen"
                        class="absolute left-0 top-full z-50 mt-1 w-52 rounded-xl border border-indigo-500/20 bg-slate-900 py-2 shadow-brand-lg"
                    >
                        <a href="/#features" class="block px-4 py-2 text-sm text-slate-300 hover:bg-indigo-500/10 hover:text-cyan-300">Features</a>
                        <a href="/#ping-tree" class="block px-4 py-2 text-sm text-slate-300 hover:bg-indigo-500/10 hover:text-cyan-300">Ping Tree</a>
                        <a href="/#postbacks" class="block px-4 py-2 text-sm text-slate-300 hover:bg-indigo-500/10 hover:text-cyan-300">Postbacks</a>
                        <a href="/#sdk" class="block px-4 py-2 text-sm text-slate-300 hover:bg-indigo-500/10 hover:text-cyan-300">SDK</a>
                    </div>
                </div>

                <Link :href="route('pricing')" :class="navLinkClass(active === 'pricing')">Pricing</Link>
                <Link :href="route('blog.index')" :class="navLinkClass(active === 'blog')">Blog</Link>

                <div class="relative" @mouseenter="resourcesOpen = true" @mouseleave="resourcesOpen = false">
                    <button
                        type="button"
                        class="flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-violet-200"
                    >
                        Resources
                        <svg class="h-4 w-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div
                        v-show="resourcesOpen"
                        class="absolute left-0 top-full z-50 mt-1 w-48 rounded-xl border border-indigo-500/20 bg-slate-900 py-2 shadow-brand-lg"
                    >
                        <a href="/#how-it-works" class="block px-4 py-2 text-sm text-slate-300 hover:bg-indigo-500/10 hover:text-cyan-300">How it works</a>
                        <Link :href="route('help.index')" class="block px-4 py-2 text-sm text-slate-300 hover:bg-indigo-500/10 hover:text-cyan-300">Help Centre</Link>
                        <Link :href="route('status.index')" class="block px-4 py-2 text-sm text-slate-300 hover:bg-indigo-500/10 hover:text-cyan-300">System status</Link>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <SystemStatusBadge v-if="systemStatus" :status="systemStatus" compact class="hidden sm:inline-flex" />
                <MarketingThemeToggle class="hidden lg:flex" />
                <div class="hidden items-center gap-2 lg:flex">
                    <Link
                        v-if="canLogin"
                        :href="signInUrl"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-slate-300 transition hover:text-cyan-300"
                    >
                        {{ isAuthenticated ? 'Go to Platform' : 'Sign In' }}
                    </Link>
                    <a href="/#demo" class="brand-btn-primary px-4 py-2 text-sm shadow-indigo-500/25">
                        Book a Demo
                    </a>
                </div>
                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-300 hover:bg-white/10 lg:hidden"
                    aria-label="Menu"
                    @click="mobileOpen = !mobileOpen"
                >
                    <svg v-if="!mobileOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    <svg v-else class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>

        <div v-show="mobileOpen" class="border-t border-indigo-500/20 bg-slate-950 px-4 py-4 lg:hidden">
            <div class="mb-3 flex items-center justify-between">
                <span class="text-xs font-semibold uppercase text-indigo-400/80">Theme</span>
                <MarketingThemeToggle />
            </div>
            <div class="flex flex-col gap-1 text-sm">
                <p class="px-2 py-1 text-xs font-semibold uppercase text-indigo-400/70">Product</p>
                <a href="/#features" class="rounded-lg px-2 py-2 text-slate-300 hover:text-cyan-300" @click="closeMobile">Features</a>
                <a href="/#ping-tree" class="rounded-lg px-2 py-2 text-slate-300 hover:text-cyan-300" @click="closeMobile">Ping Tree</a>
                <a href="/#postbacks" class="rounded-lg px-2 py-2 text-slate-300 hover:text-cyan-300" @click="closeMobile">Postbacks</a>
                <a href="/#sdk" class="rounded-lg px-2 py-2 text-slate-300 hover:text-cyan-300" @click="closeMobile">SDK</a>
                <Link :href="route('pricing')" class="rounded-lg px-2 py-2 text-slate-300 hover:text-cyan-300" @click="closeMobile">Pricing</Link>
                <Link :href="route('blog.index')" class="rounded-lg px-2 py-2 text-slate-300 hover:text-cyan-300" @click="closeMobile">Blog</Link>
                <Link :href="route('help.index')" class="rounded-lg px-2 py-2 text-slate-300 hover:text-cyan-300" @click="closeMobile">Help Centre</Link>
                <Link :href="route('status.index')" class="rounded-lg px-2 py-2 text-slate-300 hover:text-cyan-300" @click="closeMobile">System status</Link>
                <a href="/#demo" class="brand-btn-primary mt-2 px-3 py-2.5 text-center text-sm" @click="closeMobile">Book a Demo</a>
                <Link v-if="canLogin" :href="signInUrl" class="px-2 py-2 text-cyan-400" @click="closeMobile">{{ isAuthenticated ? 'Go to Platform' : 'Sign In' }}</Link>
            </div>
        </div>
    </nav>
</template>
