<script setup>
import Panel from '@/Components/UI/Panel.vue';

defineProps({
    steps: { type: Array, required: true },
    currentStep: { type: String, required: true },
    stepStatus: { type: Function, required: true },
    title: { type: String, default: 'Setup steps' },
});

const emit = defineEmits(['go']);
</script>

<template>
    <Panel :title="title">
        <ol class="space-y-1">
            <li v-for="(s, i) in steps" :key="s.id">
                <button
                    type="button"
                    :class="[
                        'flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm transition',
                        stepStatus(s.id) === 'active'
                            ? 'bg-indigo-100 font-semibold text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200'
                            : stepStatus(s.id) === 'complete'
                                ? 'text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950/30'
                                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
                    ]"
                    @click="emit('go', s.id)"
                >
                    <span
                        :class="[
                            'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                            stepStatus(s.id) === 'active'
                                ? 'bg-indigo-600 text-white'
                                : stepStatus(s.id) === 'complete'
                                    ? 'bg-emerald-500 text-white'
                                    : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                        ]"
                    >
                        <svg
                            v-if="stepStatus(s.id) === 'complete'"
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="3"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span v-else>{{ s.num ?? i + 1 }}</span>
                    </span>
                    {{ s.label }}
                </button>
            </li>
        </ol>
    </Panel>
</template>
