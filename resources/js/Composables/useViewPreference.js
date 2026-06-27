import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useViewPreference(storageKey, defaultValue = 'cards', allowed = ['cards', 'table']) {
    const page = usePage();
    const userId = page.props.auth?.user?.id ?? 'guest';
    const key = `pbe-view-${userId}-${storageKey}`;

    const isAllowed = (value) => allowed.includes(value);

    const read = () => {
        try {
            const stored = localStorage.getItem(key);
            if (stored && isAllowed(stored)) {
                return stored;
            }
        } catch {
            // localStorage unavailable
        }

        return defaultValue;
    };

    const preference = ref(read());

    const savePreference = (value) => {
        if (!isAllowed(value)) {
            return;
        }

        preference.value = value;

        try {
            localStorage.setItem(key, value);
        } catch {
            // ignore
        }
    };

    return { preference, savePreference, read, isAllowed };
}
