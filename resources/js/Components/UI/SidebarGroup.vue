<script setup>
import { computed, ref, watch } from 'vue';
import { useSidebarGroupState } from '@/Composables/useSidebarState';

const props = defineProps({
    label: { type: String, required: true },
    storageKey: { type: String, default: null },
    defaultOpen: { type: Boolean, default: false },
    active: { type: Boolean, default: false },
});

const localOpen = ref(props.defaultOpen || props.active);
const persistedOpen = props.storageKey
    ? useSidebarGroupState(props.storageKey, props.defaultOpen, props.active)
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
                'flex w-full items-center justify-between rounded-xl px-3 py-2 text-xs font-semibold uppercase tracking-wider transition',
                active ? 'bg-indigo-500/10 text-indigo-300 ring-1 ring-indigo-500/30' : 'text-slate-500 hover:bg-slate-800 hover:text-slate-300',
            ]"
            @click="isOpen = !isOpen"
        >
            <span>{{ label }}</span>
            <svg
                class="h-4 w-4 transition-transform duration-200"
                :class="chevronClass"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </button>
        <div v-show="isOpen" class="ml-1 space-y-0.5 border-l border-slate-800 pl-2">
            <slot />
        </div>
    </div>
</template>
