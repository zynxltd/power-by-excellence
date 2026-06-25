<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: { type: String, required: true },
    active: { type: Boolean, default: false },
});
</script>

<template>
    <Link
        :href="href"
        :class="[
            'block rounded-lg px-3 py-2 text-sm transition',
            active
                ? 'bg-slate-800 font-medium text-white'
                : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-200',
        ]"
    >
        <slot />
    </Link>
</template>
