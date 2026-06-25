<script setup>
import { computed, ref, watch } from 'vue';
import { useSidebarGroupState } from '@/Composables/useSidebarState';

const props = defineProps({
    label: { type: String, required: true },
    storageKey: { type: String, default: null },
    active: { type: Boolean, default: false },
});

const localOpen = ref(props.active);
const persistedOpen = props.storageKey
    ? useSidebarGroupState(props.storageKey, props.active, props.active)
    : null;

watch(() => props.active, (isActive) => {
    if (isActive) {
        if (persistedOpen) {
            persistedOpen.value = true;
        } else {
            localOpen.value = true;
        }
    }
});

const isOpen = computed({
    get: () => (persistedOpen ? persistedOpen.value : localOpen.value),
    set: (val) => {
        if (persistedOpen) {
            persistedOpen.value = val;
        } else {
            localOpen.value = val;
        }
    },
});

const chevronClass = computed(() => (isOpen.value ? 'rotate-90' : ''));
</script>

<template>
    <div class="space-y-0.5">
        <button
            type="button"
            :class="[
                'flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm font-medium transition',
                active ? 'bg-slate-800/80 text-indigo-300' : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-200',
            ]"
            @click="isOpen = !isOpen"
        >
            <span>{{ label }}</span>
            <svg
                class="h-3.5 w-3.5 transition-transform duration-200"
                :class="chevronClass"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </button>
        <div v-show="isOpen" class="ml-3 space-y-0.5 border-l border-slate-700 pl-2">
            <slot />
        </div>
    </div>
</template>
