<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
    subtitle: { type: String, default: null },
    logoUrl: { type: String, default: null },
    region: { type: Object, default: () => ({ emoji: '🌍', label: 'Unknown' }) },
});

const initials = computed(() => {
    const parts = String(props.name || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
});
</script>

<template>
    <div class="flex items-center gap-3">
        <div class="relative shrink-0">
            <div
                class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-100 dark:border-slate-700 dark:bg-slate-800"
            >
                <img
                    v-if="logoUrl"
                    :src="logoUrl"
                    :alt="`${name} logo`"
                    class="h-full w-full object-cover"
                />
                <span v-else class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ initials }}</span>
            </div>
            <span
                class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full border border-white bg-white text-xs shadow-sm dark:border-slate-900 dark:bg-slate-900"
                :title="region.label"
            >
                {{ region.emoji }}
            </span>
        </div>
        <div class="min-w-0">
            <p class="truncate font-medium text-slate-900 dark:text-white">{{ name }}</p>
            <p v-if="subtitle" class="truncate text-xs text-slate-500">{{ subtitle }}</p>
        </div>
    </div>
</template>
