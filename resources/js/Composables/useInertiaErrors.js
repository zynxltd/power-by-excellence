import { router } from '@inertiajs/vue3';
import { pushToast } from './useToast';

let initialized = false;

async function extractErrorMessage(response) {
    try {
        const contentType = response.headers.get('content-type') ?? '';
        if (contentType.includes('application/json')) {
            const json = await response.clone().json();
            if (json?.message) {
                return json.message;
            }
        }

        const text = await response.clone().text();
        const jsonMatch = text.match(/"message"\s*:\s*"([^"]+)"/);
        if (jsonMatch) {
            return jsonMatch[1];
        }

        const titleMatch = text.match(/<title[^>]*>([^<]+)<\/title>/i);
        if (titleMatch) {
            return titleMatch[1].replace(/^\d+\s*/, '').trim() || titleMatch[1];
        }

        const bodyMatch = text.match(/>\s*(\d{3}\s+[^<]{5,120})\s*</);
        if (bodyMatch) {
            return bodyMatch[1].trim();
        }
    } catch {
        // ignore parse errors
    }

    return `Request failed (${response.status})`;
}

export function initInertiaErrorHandler() {
    if (initialized) {
        return;
    }

    initialized = true;

    router.on('invalid', async (event) => {
        const response = event.detail?.response;
        if (!response || response.status < 400) {
            return;
        }

        event.preventDefault();

        const message = await extractErrorMessage(response);
        pushToast(message, 'error');
    });

    router.on('exception', () => {
        pushToast('Network error — please check your connection and try again.', 'error');
    });
}
