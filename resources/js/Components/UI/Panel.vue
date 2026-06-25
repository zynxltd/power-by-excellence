<script setup>
defineProps({
    title: { type: String, default: '' },
    padding: { type: Boolean, default: true },
});
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div v-if="title || $slots.header" class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5 dark:border-slate-800">
            <h3 v-if="title" class="text-sm font-semibold text-slate-900 dark:text-white">{{ title }}</h3>
            <slot name="header" />
        </div>
        <div :class="padding ? 'p-4' : ''">
            <slot />
        </div>
    </div>
</template>
