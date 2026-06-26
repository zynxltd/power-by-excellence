<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useNavDropdown } from '@/Composables/useNavDropdown';

const props = defineProps({
    tenantHub: { type: Object, required: true },
});

const nav = useNavDropdown();

const page = usePage();
const isSuperAdmin = computed(() => page.props.auth.isSuperAdmin);
const isCentralHost = computed(() => page.props.isCentralHost);
const sections = computed(() => props.tenantHub?.sections ?? []);

const switchToTenant = () => {
    if (!props.tenantHub?.id) return;
    router.post(route('accounts.switch'), { account_id: props.tenantHub.id }, { preserveScroll: true });
};

const clearTenantContext = () => router.post(route('accounts.clear'));

const closeMenu = () => nav?.close();
</script>

<template>
    <div class="max-h-[min(70vh,28rem)] overflow-y-auto py-1" @click.stop>
        <div class="border-b border-slate-800 px-4 py-2">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Platform shortcuts</p>
            <p class="mt-0.5 text-sm font-medium text-white">{{ tenantHub.name }}</p>
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
                class="block px-4 py-1.5 text-sm text-slate-200 hover:bg-slate-800"
                @click="closeMenu"
            >
                {{ link.label }}
            </Link>
        </div>

        <div v-if="isSuperAdmin && isCentralHost" class="space-y-0.5 px-2 py-2">
            <Link
                v-if="isCentralHost"
                :href="route('command-center.index')"
                class="block rounded-md px-2 py-1.5 text-sm text-slate-200 hover:bg-slate-800"
                @click="closeMenu"
            >
                Command Center
            </Link>
            <Link
                v-if="isCentralHost"
                :href="route('platform-events.index')"
                class="block rounded-md px-2 py-1.5 text-sm text-slate-200 hover:bg-slate-800"
                @click="closeMenu"
            >
                Platform events
            </Link>
            <button
                v-if="tenantHub.id && !tenantHub.is_active"
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
                All platforms (central)
            </button>
            <Link
                :href="route('accounts.index')"
                class="block rounded-md px-2 py-1.5 text-sm text-slate-200 hover:bg-slate-800"
                @click="closeMenu"
            >
                Partner platforms
            </Link>
        </div>
    </div>
</template>
