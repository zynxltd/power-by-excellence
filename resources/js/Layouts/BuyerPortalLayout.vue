<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BrandLogo from '@/Components/BrandLogo.vue';
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    portalBranding: { type: Object, default: null },
});

const page = usePage();

const branding = computed(() => {
    const fromPage = props.portalBranding ?? page.props.portalBranding;
    if (fromPage) {
        return fromPage;
    }

    return page.props.buyerPortal?.branding ?? null;
});

const accentColor = computed(() => branding.value?.primary_color || null);
const showChrome = computed(() => Boolean(branding.value?.logo_url || branding.value?.welcome_text));

const applyTheme = () => {
    const root = document.documentElement;

    if (accentColor.value) {
        root.style.setProperty('--buyer-portal-primary', accentColor.value);
    } else {
        root.style.removeProperty('--buyer-portal-primary');
    }
};

watch(accentColor, applyTheme, { immediate: true });
onBeforeUnmount(() => {
    document.documentElement.style.removeProperty('--buyer-portal-primary');
});
</script>

<template>
    <AuthenticatedLayout>
        <div
            v-if="showChrome"
            class="-mx-3 -mt-3 mb-4 border-b border-slate-800 bg-slate-950 px-3 py-3 sm:-mx-4 sm:-mt-4 sm:px-4 lg:-mx-5 lg:-mt-5 lg:px-5"
            :style="accentColor ? { borderBottomColor: accentColor } : undefined"
        >
            <div class="flex flex-wrap items-center gap-4">
                <div v-if="branding?.logo_url" class="shrink-0">
                    <BrandLogo
                        size="sm"
                        variant="light"
                        :logo-url="branding.logo_url"
                        :brand-name="page.props.auth?.user?.buyer?.name ?? 'Buyer portal'"
                        :show-text="false"
                    />
                </div>
                <p v-if="branding?.welcome_text" class="text-sm text-slate-200">
                    {{ branding.welcome_text }}
                </p>
            </div>
        </div>

        <div class="buyer-portal-shell">
            <slot />
        </div>
    </AuthenticatedLayout>
</template>

<style>
.buyer-portal-shell .buyer-portal-accent {
    background-color: var(--buyer-portal-primary, rgb(79 70 229)) !important;
}

.buyer-portal-shell .buyer-portal-accent-text {
    color: var(--buyer-portal-primary, rgb(79 70 229)) !important;
}

.buyer-portal-shell .buyer-portal-accent-border {
    border-color: var(--buyer-portal-primary, rgb(79 70 229)) !important;
}
</style>
