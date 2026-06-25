<script setup>
import { useNavDropdown } from '@/Composables/useNavDropdown';
import { computed } from 'vue';

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    active: { type: Boolean, default: false },
});

const nav = useNavDropdown();

const isOpen = computed(() => nav?.isOpen(props.id) ?? false);

const toggle = () => nav?.toggle(props.id);
</script>

<template>
    <div class="relative" data-nav-dropdown>
        <button
            type="button"
            :class="[
                'flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium transition',
                isOpen
                    ? 'bg-slate-800 text-white ring-1 ring-indigo-500/50'
                    : active
                        ? 'text-indigo-300'
                        : 'text-slate-300 hover:bg-slate-800 hover:text-white',
            ]"
            :aria-expanded="isOpen"
            @click.stop="toggle"
        >
            {{ label }}
            <svg
                class="h-4 w-4 opacity-70 transition"
                :class="isOpen ? 'rotate-180' : ''"
                fill="currentColor"
                viewBox="0 0 20 20"
            >
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </button>
        <div
            v-show="isOpen"
            class="absolute left-0 top-full z-50 mt-1 min-w-[14rem] rounded-xl border border-slate-700 bg-slate-900 py-1 shadow-xl"
            @click="nav?.close()"
        >
            <slot />
        </div>
    </div>
</template>
