<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    as: {
        type: String,
        default: undefined,
    },
    method: {
        type: String,
        default: 'get',
    },
    theme: {
        type: String,
        default: 'light',
        validator: (v) => ['light', 'dark'].includes(v),
    },
});

const linkClass = computed(() => {
    const base = [
        'block w-full px-4 py-2 text-start text-sm leading-5 transition focus:outline-none',
    ];

    if (props.as === 'button') {
        base.push('cursor-pointer border-0 bg-transparent font-inherit');
    }

    if (props.theme === 'dark') {
        base.push('text-slate-100 hover:bg-slate-800 hover:text-white focus:bg-slate-800 focus:text-white');

        return base.join(' ');
    }

    base.push(
        'text-slate-700 hover:bg-slate-100 focus:bg-slate-100',
        'dark:text-slate-200 dark:hover:bg-slate-700 dark:focus:bg-slate-700',
    );

    return base.join(' ');
});
</script>

<template>
    <Link
        :href="href"
        :as="as"
        :method="method"
        :class="linkClass"
    >
        <slot />
    </Link>
</template>
