import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const getNested = (object, path) => path.split('.').reduce((current, key) => current?.[key], object);

export function useBuyerPortalI18n() {
    const page = usePage();
    const buyerPortal = computed(() => page.props.buyerPortal ?? null);

    const t = (key, replacements = {}) => {
        let text = getNested(buyerPortal.value?.strings, key) ?? key;

        Object.entries(replacements).forEach(([name, value]) => {
            text = String(text).replaceAll(`:${name}`, String(value));
        });

        return text;
    };

    const locale = computed(() => buyerPortal.value?.locale ?? 'en');

    return { t, locale, buyerPortal };
}
