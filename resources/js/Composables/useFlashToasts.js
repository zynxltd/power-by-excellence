import { router } from '@inertiajs/vue3';
import { pushToast } from './useToast';

let initialized = false;
const shown = new Set();

export function initFlashToasts() {
    if (initialized) {
        return;
    }

    initialized = true;

    router.on('success', (event) => {
        const visit = event.detail?.visit;
        const flash = event.detail?.page?.props?.flash;

        if (!visit || !flash) {
            return;
        }

        const token = `${visit.url}|${visit.version ?? ''}|${flash.success ?? ''}|${flash.error ?? ''}`;
        if (!flash.success && !flash.error) {
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
    });
}
