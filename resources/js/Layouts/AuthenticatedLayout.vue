<script setup>
import AdminTopNav from '@/Components/UI/AdminTopNav.vue';
import ToastHost from '@/Components/UI/ToastHost.vue';
import BillingAlert from '@/Components/UI/BillingAlert.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import NavigationLoader from '@/Components/UI/NavigationLoader.vue';
import LiveStatsBar from '@/Components/UI/LiveStatsBar.vue';
import { isNavigating } from '@/Composables/useGlobalLoading';
import { provideLiveStats } from '@/Composables/useLiveStats';
import { usePage } from '@inertiajs/vue3';
import { computed, provide } from 'vue';

const page = usePage();
const impersonator = computed(() => page.props.auth?.impersonator);
const godMode = computed(() => page.props.auth?.godMode);
const showSessionBanner = computed(() => impersonator.value || godMode.value);
const showLiveStats = computed(() => page.props.auth?.showLiveStats ?? false);

const liveStats = provideLiveStats();
provide(liveStats.provideKey, liveStats);
provide('isNavigating', isNavigating);
</script>

<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
        <NavigationLoader />
        <div
            v-if="showSessionBanner"
            class="flex flex-wrap items-center justify-center gap-2 border-b border-amber-300 bg-amber-50 px-4 py-2 text-center text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100"
        >
            <template v-if="impersonator">
                Viewing as <strong>{{ page.props.auth.user?.name }}</strong>
                (impersonated by {{ impersonator.name }}).
            </template>
            <template v-else>
                God mode - viewing <strong>{{ page.props.auth.account?.display_name ?? 'tenant platform' }}</strong> as super admin.
            </template>
            <AppButton :href="route('impersonate.stop')" method="post" class="inline-flex shrink-0" variant="secondary">
                {{ impersonator ? 'End impersonation' : 'Exit god mode' }}
            </AppButton>
        </div>
        <AdminTopNav />
        <main class="mx-auto max-w-[1600px] overflow-x-clip p-3 sm:p-4 lg:p-5">
            <LiveStatsBar v-if="showLiveStats" />
            <BillingAlert />
            <slot />
        </main>
        <ToastHost />
    </div>
</template>
