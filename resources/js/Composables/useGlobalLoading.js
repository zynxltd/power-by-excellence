import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

export const isNavigating = ref(false);

let initialized = false;

export function initGlobalLoading() {
    if (initialized) {
        return;
    }

    initialized = true;

    router.on('start', () => {
        isNavigating.value = true;
    });

    router.on('finish', () => {
        isNavigating.value = false;
    });

    router.on('error', () => {
        isNavigating.value = false;
    });
}
