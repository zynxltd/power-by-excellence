<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import SystemStatusBadge from '@/Components/Marketing/SystemStatusBadge.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    canLogin: { type: Boolean, default: false },
});

const page = usePage();
const signInUrl = computed(() => page.props.urls?.marketingSignIn ?? route('login'));
const isAuthenticated = computed(() => !!page.props.auth?.user);
const systemStatus = computed(() => page.props.systemStatus);
</script>

<template>
    <footer class="marketing-chrome marketing-chrome-footer relative py-12">
        <div class="mx-auto max-w-7xl px-6">
            <div class="flex flex-col items-center justify-between gap-6 md:flex-row">
                <BrandLogo size="sm" variant="light" />
                <div class="flex flex-wrap justify-center gap-x-8 gap-y-2 text-sm text-slate-500">
                    <a href="/#features" class="transition hover:text-violet-300">Features</a>
                    <a href="/#how-it-works" class="transition hover:text-violet-300">How it works</a>
                    <a href="/#postbacks" class="transition hover:text-violet-300">Postbacks</a>
                    <Link :href="route('pricing')" class="transition hover:text-indigo-300">Pricing</Link>
                    <Link :href="route('blog.index')" class="transition hover:text-indigo-300">Blog</Link>
                    <a href="/#sdk" class="transition hover:text-cyan-300">SDK</a>
                    <Link :href="route('help.index')" class="transition hover:text-cyan-300">Help Centre</Link>
                    <Link :href="route('status.index')" class="transition hover:text-emerald-300">System status</Link>
                    <a href="/#demo" class="transition hover:text-violet-300">Book a Demo</a>
                    <Link v-if="canLogin" :href="signInUrl" class="transition hover:text-indigo-300">{{ isAuthenticated ? 'Go to Platform' : 'Sign In' }}</Link>
                </div>
            </div>
            <div class="mt-8 flex flex-col items-center gap-4 border-t border-indigo-500/20 pt-8">
                <SystemStatusBadge v-if="systemStatus" :status="systemStatus" />
                <p class="text-center text-sm text-slate-600">
                &copy; {{ new Date().getFullYear() }}
                <span class="brand-gradient-text font-semibold">PowerByExcellence</span>.
                Lead distribution platform.
                </p>
            </div>
        </div>
    </footer>
</template>
