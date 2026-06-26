import { router } from '@inertiajs/vue3';
import { pushToast } from './useToast';

let initialized = false;
const shown = new Set();

function showFlash(flash, token) {
    if (!flash?.success && !flash?.error) {
        return;
    }

    if (shown.has(token)) {
        return;
    }

    shown.add(token);

    if (shown.size > 50) {
        shown.clear();
    }

    if (flash.success) {
        pushToast(flash.success, 'success');
    }
    if (flash.error) {
        pushToast(flash.error, 'error');
    }
}

function flashFromPage(page) {
    if (!page) {
        return null;
    }

    return page.flash ?? page.props?.flash ?? null;
}

export function initFlashToasts() {
    if (initialized) {
        return;
    }

    initialized = true;

    // Inertia v2 dedicated flash event (Inertia::flash / session flash data)
    router.on('flash', (event) => {
        const flash = event.detail?.flash;
        const token = `flash:${JSON.stringify(flash)}`;
        showFlash(flash, token);
    });

    // Fallback for shared props.flash after redirects (e.g. ->with('success'))
    router.on('success', (event) => {
        const page = event.detail?.page;
        const flash = flashFromPage(page);

        if (!flash) {
            return;
        }

        const token = `${page?.url ?? ''}|${page?.version ?? ''}|${flash.success ?? ''}|${flash.error ?? ''}`;
        showFlash(flash, token);
    });
}
