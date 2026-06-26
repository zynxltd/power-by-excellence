<script setup>
import FormStepSidebar from '@/Components/UI/FormStepSidebar.vue';

defineProps({
    steps: { type: Array, required: true },
    currentStep: { type: String, required: true },
    stepStatus: { type: Function, required: true },
    sidebarTitle: { type: String, default: 'Setup steps' },
});

const emit = defineEmits(['go']);
</script>

<template>
    <div class="grid items-start gap-4 pt-4 lg:grid-cols-12 lg:gap-6 lg:pt-6">
        <div class="flex gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-800 dark:bg-slate-900 lg:hidden [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <button
                v-for="(s, i) in steps"
                :key="s.id"
                type="button"
                :class="[
                    'shrink-0 rounded-lg px-3 py-2 text-left text-xs font-medium transition',
                    stepStatus(s.id) === 'active'
                        ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200'
                        : stepStatus(s.id) === 'complete'
                            ? 'text-emerald-700 dark:text-emerald-300'
                            : 'text-slate-600 dark:text-slate-400',
                ]"
                @click="emit('go', s.id)"
            >
                {{ s.num ?? i + 1 }}. {{ s.label }}
            </button>
        </div>

        <aside class="hidden space-y-4 lg:col-span-3 lg:block lg:sticky lg:top-32 lg:max-h-[calc(100vh-9rem)] lg:self-start lg:overflow-y-auto">
            <FormStepSidebar
                :steps="steps"
                :current-step="currentStep"
                :step-status="stepStatus"
                :title="sidebarTitle"
                @go="emit('go', $event)"
            />
            <slot name="sidebar" />
        </aside>

        <div class="space-y-6 lg:col-span-9">
            <slot />
        </div>
    </div>
</template>
