<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    hub: { type: Object, required: true },
});

const page = usePage();
const isSuperAdmin = computed(() => page.props.auth.isSuperAdmin);
const isCentralHost = computed(() => page.props.isCentralHost);
const sections = computed(() => props.hub?.sections ?? []);

const switchToTenant = () => {
    if (! props.hub?.id) {
        return;
    }

    router.post(route('accounts.switch'), { account_id: props.hub.id }, { preserveScroll: true });
};

const clearTenantContext = () => router.post(route('accounts.clear'));

const linkClass = 'block px-4 py-1.5 text-sm text-slate-200 hover:bg-slate-800';
</script>

<template>
    <div class="max-h-[min(70vh,32rem)] overflow-y-auto py-1" @click.stop>
        <div class="border-b border-slate-800 px-4 py-2.5">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Platform shortcuts</p>
            <p class="mt-0.5 text-sm font-medium text-white">{{ hub.name }}</p>
            <p v-if="hub.is_central" class="mt-0.5 text-[11px] text-slate-400">Click a destination - or switch a tenant from Partner platforms</p>
        </div>

        <div v-for="section in sections" :key="section.title" class="border-b border-slate-800/80 py-1 last:border-0">
            <p class="px-4 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                {{ section.title }}
            </p>
            <Link
                v-for="link in section.links"
                :key="link.href + link.label"
                :href="link.href"
                :title="link.description"
                :class="linkClass"
            >
                {{ link.label }}
            </Link>
        </div>

        <div v-if="isSuperAdmin && isCentralHost && hub.id" class="space-y-0.5 border-t border-slate-800 px-2 py-2">
            <button
                v-if="!hub.is_active"
                type="button"
                class="block w-full rounded-md px-2 py-1.5 text-left text-sm text-slate-200 hover:bg-slate-800"
                @click="switchToTenant"
            >
                Switch to this tenant
            </button>
            <button
                v-if="page.props.auth.account"
                type="button"
                class="block w-full rounded-md px-2 py-1.5 text-left text-sm text-slate-200 hover:bg-slate-800"
                @click="clearTenantContext"
            >
                Clear tenant context
            </button>
            <Link :href="route('accounts.index')" :class="linkClass">Partner platforms</Link>
        </div>
    </div>
</template>
