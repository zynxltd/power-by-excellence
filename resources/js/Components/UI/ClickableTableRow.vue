<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
    href: { type: String, required: true },
});
</script>

<template>
    <tr
        class="cursor-pointer transition hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20"
        role="link"
        tabindex="0"
        @click="router.visit(props.href)"
        @keydown.enter="router.visit(props.href)"
    >
        <slot />
    </tr>
</template>
