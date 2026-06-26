import { router } from '@inertiajs/vue3';
import { pushToast } from './useToast';

let initialized = false;
let lastFlashKey = '';

function extractFlash(page) {
    if (!page) {
        return null;
    }

    const top = page.flash ?? {};
    const props = page.props?.flash ?? {};

    const success = top.success ?? props.success ?? top.demo_success ?? props.demo_success ?? null;
    const error = top.error ?? props.error ?? null;

    if (!success && !error) {
        return null;
    }

    return { success, error };
}

function showFlash(flash, dedupeKey) {
    if (!flash?.success && !flash?.error) {
        return;
    }

    if (dedupeKey && dedupeKey === lastFlashKey) {
        return;
    }

    lastFlashKey = dedupeKey ?? '';

    if (flash.success) {
        pushToast(flash.success, 'success');
    }
    if (flash.error) {
        pushToast(flash.error, 'error');
    }
}

export function initFlashToasts() {
    if (initialized) {
        return;
    }

    initialized = true;

    router.on('success', (event) => {
        const page = event.detail?.page;
        const flash = extractFlash(page);

        if (!flash) {
            return;
        }

        const dedupeKey = `${page?.url ?? ''}|${page?.version ?? ''}|${flash.success ?? ''}|${flash.error ?? ''}`;
        showFlash(flash, dedupeKey);
    });
}
