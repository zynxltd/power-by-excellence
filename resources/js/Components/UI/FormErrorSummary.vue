<script setup>
import { computed } from 'vue';

const props = defineProps({
    errors: { type: Object, default: () => ({}) },
});

const messages = computed(() => Object.values(props.errors).filter(Boolean));
const hasErrors = computed(() => messages.value.length > 0);
</script>

<template>
    <div
        v-if="hasErrors"
        class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 dark:border-rose-500/30 dark:bg-rose-500/10"
    >
        <p class="text-sm font-semibold text-rose-800 dark:text-rose-400">Please fix the following:</p>
        <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-rose-700 dark:text-rose-300">
            <li v-for="(msg, i) in messages" :key="i">{{ msg }}</li>
        </ul>
    </div>
</template>
