<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="mb-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">{{ title }}</h1>
                <p v-if="description" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ description }}</p>
                <slot name="subtitle" />
            </div>
            <div v-if="$slots.actions" class="flex shrink-0 items-center gap-3">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
