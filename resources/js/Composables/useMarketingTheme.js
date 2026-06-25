import { onMounted, ref } from 'vue';

const MARKETING_THEME_KEY = 'pbe-marketing-theme';

const marketingTheme = ref('light');

let initialized = false;

function apply(value) {
    marketingTheme.value = value === 'dark' ? 'dark' : 'light';
    if (typeof window !== 'undefined') {
        localStorage.setItem(MARKETING_THEME_KEY, marketingTheme.value);
    }
}

function init() {
    if (typeof window === 'undefined') return;
    const stored = localStorage.getItem(MARKETING_THEME_KEY);
    apply(stored === 'dark' ? 'dark' : 'light');
}

export function useMarketingTheme() {
    if (!initialized && typeof window !== 'undefined') {
        init();
        initialized = true;
    }

    const toggle = () => apply(marketingTheme.value === 'dark' ? 'light' : 'dark');
    const isDark = () => marketingTheme.value === 'dark';

    return {
        marketingTheme,
        toggle,
        isDark,
        contentClasses: () => [
            'marketing-content antialiased',
            marketingTheme.value === 'dark' ? 'marketing-dark' : '',
        ],
    };
}
