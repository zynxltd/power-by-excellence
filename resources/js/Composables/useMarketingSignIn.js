import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useMarketingSignIn() {
    const page = usePage();

    const signInUrl = computed(() => page.props.urls?.marketingSignIn ?? route('login'));
    const isAuthenticated = computed(() => !!page.props.auth?.user);

    const isExternalSignIn = computed(() => {
        const url = signInUrl.value;
        if (! url?.startsWith('http')) {
            return false;
        }

        try {
            return new URL(url).host !== window.location.host;
        } catch {
            return false;
        }
    });

    return { signInUrl, isAuthenticated, isExternalSignIn };
}
