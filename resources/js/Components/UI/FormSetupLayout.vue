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
    <div class="grid items-start gap-6 lg:grid-cols-12">
        <aside class="space-y-4 lg:col-span-3 lg:sticky lg:top-20 lg:max-h-[calc(100vh-5.5rem)] lg:self-start lg:overflow-y-auto">
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
