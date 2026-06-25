<script setup>
import { isNavigating } from '@/Composables/useGlobalLoading';
import Spinner from '@/Components/UI/Spinner.vue';
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="isNavigating"
            class="pointer-events-none fixed left-3 top-3 z-[100]"
            aria-live="polite"
            aria-busy="true"
        >
            <Spinner size="sm" />
        </div>
    </Transition>
    <div
        v-if="isNavigating"
        class="pointer-events-none fixed inset-x-0 top-0 z-[99] h-0.5 overflow-hidden bg-indigo-100 dark:bg-indigo-950"
    >
        <div class="h-full w-1/3 animate-[shimmer_1s_ease-in-out_infinite] bg-gradient-to-r from-indigo-500 via-violet-500 to-indigo-500" />
    </div>
</template>

<style scoped>
@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(400%);
    }
}
</style>
