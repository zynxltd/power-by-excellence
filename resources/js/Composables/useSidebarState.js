import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useSidebarGroupState(storageKey, defaultOpen = false, active = false) {
    const page = usePage();
    const userId = page.props.auth?.user?.id ?? 'guest';
    const key = `pbe-sidebar-${userId}-${storageKey}`;

    let initial = defaultOpen || active;
    try {
        const stored = localStorage.getItem(key);
        if (stored !== null) {
            initial = stored === 'true';
        }
    } catch {
        // localStorage unavailable
    }

    const open = ref(initial);

    watch(open, (val) => {
        try {
            localStorage.setItem(key, String(val));
        } catch {
            // ignore
        }
    });

    watch(
        () => active,
        (isActive) => {
            if (isActive) {
                open.value = true;
            }
        },
    );

    return open;
}
