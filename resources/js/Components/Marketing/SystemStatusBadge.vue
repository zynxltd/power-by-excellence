<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    status: { type: Object, default: null },
    compact: { type: Boolean, default: false },
});

const resolved = computed(() => props.status ?? { status: 'operational', label: 'All systems operational' });

const dotClass = computed(() => ({
    operational: 'bg-emerald-400',
    degraded: 'bg-amber-400 animate-pulse',
    outage: 'bg-rose-500 animate-pulse',
}[resolved.value.status] ?? 'bg-slate-400'));

const textClass = computed(() => ({
    operational: 'text-emerald-400',
    degraded: 'text-amber-400',
    outage: 'text-rose-400',
}[resolved.value.status] ?? 'text-slate-400'));
</script>

<template>
    <Link
        :href="route('status.index')"
        :class="[
            'inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium transition hover:border-indigo-500/40 hover:bg-white/10',
            compact && 'px-2 py-1',
        ]"
        :title="resolved.label"
    >
        <span class="relative flex h-2 w-2 shrink-0">
            <span
                v-if="resolved.status !== 'operational'"
                class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75"
                :class="dotClass"
            />
            <span class="relative inline-flex h-2 w-2 rounded-full" :class="dotClass" />
        </span>
        <span :class="textClass">{{ compact ? resolved.status : resolved.label }}</span>
    </Link>
</template>
