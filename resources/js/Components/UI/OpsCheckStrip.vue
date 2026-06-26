<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    checks: {
        type: Array,
        default: () => [],
    },
});

const copied = ref('');

const columnCount = computed(() => props.checks?.length ?? 0);

const gridStyle = computed(() => ({
    gridTemplateColumns: `repeat(${columnCount.value}, minmax(5.5rem, 1fr))`,
}));

const statusAccent = (status) => ({
    ok: 'emerald',
    warning: 'amber',
    critical: 'rose',
}[status] ?? undefined);

const statusValue = (status) => ({
    ok: 'OK',
    warning: 'Warn',
    critical: 'Fail',
}[status] ?? status);

const tooltip = (check) => {
    const parts = [check.message, check.hint, check.command ? `Run: ${check.command}` : null].filter(Boolean);

    return parts.join('\n');
};

const copyCommand = async (check) => {
    if (!check.command) {
        return;
    }

    await navigator.clipboard.writeText(check.command);
    copied.value = check.key;
    setTimeout(() => { copied.value = ''; }, 2000);
};
</script>

<template>
    <div
        v-if="checks?.length"
        class="w-full overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
    >
        <div
            class="grid w-full min-w-full divide-x divide-slate-200 dark:divide-slate-800"
            :style="gridStyle"
        >
            <button
                v-for="check in checks"
                :key="check.key"
                type="button"
                :title="tooltip(check)"
                :class="[
                    'flex min-w-0 flex-col items-center px-2 py-2 text-center sm:px-2.5',
                    check.command && check.status !== 'ok' ? 'cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60' : 'cursor-default',
                ]"
                @click="check.command && check.status !== 'ok' ? copyCommand(check) : null"
            >
                <span class="line-clamp-2 text-[9px] font-semibold uppercase leading-tight tracking-wide text-slate-500 dark:text-slate-400 sm:text-[10px]">
                    {{ check.label }}
                </span>
                <span
                    :class="[
                        'mt-0.5 text-xs font-bold leading-tight sm:text-sm',
                        statusAccent(check.status) === 'emerald' ? 'text-emerald-600 dark:text-emerald-400'
                        : statusAccent(check.status) === 'amber' ? 'text-amber-600 dark:text-amber-400'
                        : statusAccent(check.status) === 'rose' ? 'text-rose-600 dark:text-rose-400'
                        : 'text-slate-900 dark:text-white',
                    ]"
                >
                    {{ copied === check.key ? 'Copied' : statusValue(check.status) }}
                </span>
            </button>
        </div>
    </div>
</template>
