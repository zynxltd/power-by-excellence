<script setup>
defineProps({
    align: { type: String, default: 'left' },
});
</script>

<template>
    <th
        :class="[
            'px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500',
            align === 'right' ? 'text-right' : 'text-left',
        ]"
    >
        <slot />
    </th>
</template>
