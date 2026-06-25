<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: '' },
    mono: { type: Boolean, default: false },
    accent: {
        type: String,
        default: 'slate',
        validator: (v) => ['indigo', 'emerald', 'amber', 'rose', 'cyan', 'violet', 'slate'].includes(v),
    },
});

const accentBorders = {
    indigo: 'border-l-indigo-500',
    emerald: 'border-l-emerald-500',
    amber: 'border-l-amber-500',
    rose: 'border-l-rose-500',
    cyan: 'border-l-cyan-500',
    violet: 'border-l-violet-500',
    slate: 'border-l-slate-400',
};
</script>

<template>
    <div
        :class="[
            'rounded-xl border border-slate-200/80 border-l-[3px] bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900',
            accentBorders[accent],
        ]"
    >
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            {{ label }}
        </p>
        <div class="mt-1.5 min-w-0">
            <slot>
                <p
                    :class="[
                        'truncate text-sm font-semibold text-slate-900 dark:text-white',
                        mono && 'font-mono text-xs font-medium tracking-tight',
                    ]"
                >
                    {{ value }}
                </p>
            </slot>
        </div>
    </div>
</template>
