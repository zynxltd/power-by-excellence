<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
    href: { type: String, default: '' },
    id: { type: String, default: undefined },
});

const visit = () => {
    if (props.href) {
        router.visit(props.href);
    }
};
</script>

<template>
    <tr
        :id="id"
        :class="href ? 'cursor-pointer transition hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20' : ''"
        :role="href ? 'link' : undefined"
        :tabindex="href ? 0 : undefined"
        @click="visit"
        @keydown.enter="visit"
    >
        <slot />
    </tr>
</template>
