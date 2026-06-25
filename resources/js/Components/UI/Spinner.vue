<script setup>
defineProps({
    size: { type: String, default: 'md' },
    class: { type: String, default: '' },
});

const sizes = {
    xs: 'h-3 w-3 border',
    sm: 'h-4 w-4 border-2',
    md: 'h-5 w-5 border-2',
    lg: 'h-8 w-8 border-[3px]',
};
</script>

<template>
    <span
        role="status"
        aria-label="Loading"
        :class="[
            'inline-block shrink-0 animate-spin rounded-full border-current border-t-transparent',
            sizes[size],
            $props.class,
        ]"
    />
</template>
