<script setup>
import QuickLinkChips from '@/Components/UI/QuickLinkChips.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    tenantHub: { type: Object, required: true },
});

const page = usePage();
const isSuperAdmin = computed(() => page.props.auth.isSuperAdmin);
const activeTab = ref(0);

const sections = computed(() => props.tenantHub?.sections ?? []);

const tabLabel = (title) => {
    const map = {
        Tenant: 'Tenant',
        'Campaigns & leads': 'Campaigns',
        Operations: 'Ops',
        'Finance & tools': 'Finance',
    };
    return map[title] ?? title;
};

const switchToTenant = () => {
    if (!props.tenantHub?.id) return;
    router.post(route('accounts.switch'), { account_id: props.tenantHub.id }, { preserveScroll: true });
};
</script>

<template>
    <div>
        <div class="mb-3 flex flex-wrap gap-1">
            <button
                v-for="(section, index) in sections"
                :key="section.title"
                type="button"
                :class="[
                    'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                    activeTab === index
                        ? 'bg-indigo-600 text-white'
                        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
                ]"
                @click="activeTab = index"
            >
                {{ tabLabel(section.title) }}
            </button>
        </div>
        <QuickLinkChips v-if="sections[activeTab]" :links="sections[activeTab].links" />
        <div v-if="isSuperAdmin" class="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
            <AppButton
                v-if="!tenantHub.is_active"
                type="button"
                variant="secondary"
                class="!py-1.5 !text-xs"
                @click="switchToTenant"
            >
                Switch to tenant
            </AppButton>
            <AppButton :href="route('accounts.index')" variant="secondary" class="!py-1.5 !text-xs">
                All platforms
            </AppButton>
        </div>
    </div>
</template>
