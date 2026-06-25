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
    <div class="grid gap-6 lg:grid-cols-12">
        <aside class="lg:col-span-3">
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
