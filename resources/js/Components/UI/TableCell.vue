<script setup>
defineProps({
    align: { type: String, default: 'left' },
    strong: { type: Boolean, default: false },
    mono: { type: Boolean, default: false },
});
</script>

<template>
    <td
        :class="[
            'px-3 py-1.5 text-xs',
            strong ? 'font-medium text-slate-900 dark:text-white' : 'text-slate-600 dark:text-slate-400',
            mono ? 'font-mono' : '',
            align === 'right' ? 'text-right' : 'text-left',
        ]"
    >
        <slot />
    </td>
</template>
