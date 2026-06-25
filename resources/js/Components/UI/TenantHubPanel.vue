<script setup>
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    tenantHub: { type: Object, default: null },
    compact: { type: Boolean, default: false },
});

const page = usePage();
const isSuperAdmin = computed(() => page.props.auth.isSuperAdmin);

const switchToTenant = () => {
    if (!props.tenantHub?.id) return;
    router.post(route('accounts.switch'), { account_id: props.tenantHub.id }, { preserveScroll: true });
};
</script>

<template>
    <Panel v-if="tenantHub" :title="compact ? undefined : `Tenant — ${tenantHub.name}`" class="tenant-hub-panel">
        <template v-if="compact" #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Platform</p>
                    <p class="font-semibold text-slate-900 dark:text-white">{{ tenantHub.name }}</p>
                </div>
                <AppButton
                    v-if="isSuperAdmin && !tenantHub.is_active"
                    type="button"
                    @click="switchToTenant"
                >
                    Switch to this tenant
                </AppButton>
                <span
                    v-else-if="tenantHub.is_active"
                    class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
                >
                    Active tenant
                </span>
            </div>
        </template>

        <div v-if="!compact" class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Manage all portal functionality for
                    <span class="font-semibold text-slate-900 dark:text-white">{{ tenantHub.name }}</span>
                    <span v-if="tenantHub.country" class="text-slate-500"> · {{ tenantHub.country }} / {{ tenantHub.currency }}</span>
                </p>
            </div>
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

        <div :class="compact ? 'grid gap-4 sm:grid-cols-2' : 'grid gap-6 lg:grid-cols-4'">
            <div v-for="section in tenantHub.sections" :key="section.title">
                <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-500">{{ section.title }}</h4>
                <ul class="space-y-1">
                    <li v-for="link in section.links" :key="link.href + link.label">
                        <Link
                            :href="link.href"
                            class="group flex flex-col rounded-lg px-2 py-1.5 transition hover:bg-indigo-50 dark:hover:bg-indigo-950/30"
                        >
                            <span class="text-sm font-medium text-slate-800 group-hover:text-indigo-700 dark:text-slate-200 dark:group-hover:text-indigo-300">
                                {{ link.label }}
                            </span>
                            <span v-if="link.description && !compact" class="text-xs text-slate-500 dark:text-slate-400">
                                {{ link.description }}
                            </span>
                        </Link>
                    </li>
                </ul>
            </div>
        </div>
    </Panel>
</template>
