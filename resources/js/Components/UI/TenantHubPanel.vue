<script setup>
import QuickLinkChips from '@/Components/UI/QuickLinkChips.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    tenantHub: { type: Object, default: null },
    /** @deprecated use variant */
    compact: { type: Boolean, default: false },
    variant: { type: String, default: 'default' }, // default | shortcuts
    defaultExpanded: { type: Boolean, default: true },
});

const page = usePage();
const isSuperAdmin = computed(() => page.props.auth.isSuperAdmin);

const sections = computed(() => props.tenantHub?.sections ?? []);

const switchToTenant = () => {
    if (!props.tenantHub?.id) return;
    router.post(route('accounts.switch'), { account_id: props.tenantHub.id }, { preserveScroll: true });
};
</script>

<template>
    <div v-if="tenantHub" class="tenant-hub-panel">
        <Panel :title="`Tenant — ${tenantHub.name}`">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Manage all portal functionality for
                    <span class="font-semibold text-slate-900 dark:text-white">{{ tenantHub.name }}</span>
                    <span v-if="tenantHub.country" class="text-slate-500"> · {{ tenantHub.country }} / {{ tenantHub.currency }}</span>
                </p>
                <div class="flex flex-wrap gap-2">
                    <AppButton
                        v-if="isSuperAdmin && !tenantHub.is_active"
                        type="button"
                        variant="secondary"
                        @click="switchToTenant"
                    >
                        Switch to this tenant
                    </AppButton>
                    <AppButton v-if="isSuperAdmin" :href="route('accounts.index')" variant="secondary">
                        All platforms
                    </AppButton>
                </div>
            </div>

            <div class="space-y-5">
                <div v-for="section in sections" :key="section.title">
                    <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-500">{{ section.title }}</h4>
                    <QuickLinkChips :links="section.links" />
                </div>
            </div>
        </Panel>
    </div>
</template>
