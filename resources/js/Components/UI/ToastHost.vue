<script setup>
import { useToast } from '@/Composables/useToast';

const { toasts, remove } = useToast();

const styles = {
    success: 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300',
    error: 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300',
    info: 'border-indigo-200 bg-indigo-50 text-indigo-900 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300',
};
</script>

<template>
    <div class="pointer-events-none fixed bottom-4 right-4 z-[100] flex w-full max-w-sm flex-col gap-2">
        <transition-group name="toast">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                :class="['pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-lg', styles[toast.type] ?? styles.success]"
            >
                <p class="flex-1 break-words">{{ toast.message }}</p>
                <button type="button" class="shrink-0 opacity-60 hover:opacity-100" @click="remove(toast.id)">✕</button>
            </div>
        </transition-group>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.25s ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(0.5rem);
}
</style>
