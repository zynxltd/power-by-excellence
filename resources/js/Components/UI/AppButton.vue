<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Spinner from '@/Components/UI/Spinner.vue';

const props = defineProps({
    variant: { type: String, default: 'primary' },
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    href: { type: String, default: null },
    method: { type: String, default: 'get' },
    external: { type: Boolean, default: false },
});

const variants = {
    primary: 'accent-gradient text-white shadow-lg hover:opacity-95',
    secondary: 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-slate-500 dark:hover:bg-slate-600 dark:hover:text-white',
    danger: 'bg-rose-600 text-white hover:bg-rose-500',
    ghost: 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
};

const isDisabled = computed(() => props.disabled || props.loading);

const classes = computed(() => [
    'inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-slate-900',
    variants[props.variant],
    props.loading ? 'relative' : '',
]);
</script>

<template>
    <a
        v-if="href && external"
        :href="href"
        :class="classes"
    >
        <Spinner v-if="loading" size="sm" />
        <slot />
    </a>
    <Link
        v-else-if="href && method === 'get'"
        :href="href"
        :class="classes"
        :disabled="isDisabled"
    >
        <Spinner v-if="loading" size="sm" />
        <slot />
    </Link>
    <Link
        v-else-if="href"
        :href="href"
        :method="method"
        as="button"
        :class="classes"
        :disabled="isDisabled"
    >
        <Spinner v-if="loading" size="sm" />
        <slot />
    </Link>
    <button
        v-else
        :type="type"
        :disabled="isDisabled"
        :class="classes"
    >
        <Spinner v-if="loading" size="sm" />
        <slot />
    </button>
</template>
