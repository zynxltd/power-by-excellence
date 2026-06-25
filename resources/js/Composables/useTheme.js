import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const THEME_KEY = 'pbe-theme';
const ACCENT_KEY = 'pbe-accent';

const theme = ref('light');
const accent = ref('indigo');

export const accentMap = {
    violet: { from: '#7c3aed', to: '#4f46e5', ring: '#8b5cf6', bg: '#7c3aed' },
    indigo: { from: '#6366f1', to: '#4f46e5', ring: '#6366f1', bg: '#4f46e5' },
    emerald: { from: '#10b981', to: '#059669', ring: '#34d399', bg: '#059669' },
    rose: { from: '#f43f5e', to: '#e11d48', ring: '#fb7185', bg: '#e11d48' },
    amber: { from: '#f59e0b', to: '#d97706', ring: '#fbbf24', bg: '#d97706' },
    cyan: { from: '#06b6d4', to: '#0891b2', ring: '#22d3ee', bg: '#0891b2' },
};

function applyTheme(value, { persist = true } = {}) {
    const root = document.documentElement;
    root.classList.toggle('dark', value === 'dark');
    theme.value = value;
    if (persist) {
        localStorage.setItem(THEME_KEY, value);
    }
}

function applyAccent(value, { persist = true } = {}) {
    const root = document.documentElement;
    const colors = accentMap[value] ?? accentMap.indigo;
    root.dataset.accent = value;
    root.style.setProperty('--accent-from', colors.from);
    root.style.setProperty('--accent-to', colors.to);
    root.style.setProperty('--accent-ring', colors.ring);
    root.style.setProperty('--accent-bg', colors.bg);
    accent.value = value;
    if (persist) {
        localStorage.setItem(ACCENT_KEY, value);
    }
}

function applyAccentVisual(value) {
    const colors = accentMap[value] ?? accentMap.indigo;
    const root = document.documentElement;
    root.dataset.accent = value;
    root.style.setProperty('--accent-from', colors.from);
    root.style.setProperty('--accent-to', colors.to);
    root.style.setProperty('--accent-ring', colors.ring);
    root.style.setProperty('--accent-bg', colors.bg);
    accent.value = value;
}

function initFromStorage() {
    const storedTheme = localStorage.getItem(THEME_KEY);
    const storedAccent = localStorage.getItem(ACCENT_KEY);

    if (storedTheme === 'dark' || storedTheme === 'light') {
        applyTheme(storedTheme);
    } else {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.classList.toggle('dark', prefersDark);
        theme.value = prefersDark ? 'dark' : 'light';
    }

    if (storedAccent && accentMap[storedAccent]) {
        applyAccent(storedAccent);
    } else {
        applyAccentVisual('indigo');
    }
}

function syncFromServer(prefs) {
    if (!prefs) {
        return;
    }

    const hasLocalTheme = localStorage.getItem(THEME_KEY) === 'dark' || localStorage.getItem(THEME_KEY) === 'light';
    const storedAccent = localStorage.getItem(ACCENT_KEY);
    const hasLocalAccent = storedAccent && accentMap[storedAccent];

    if (!hasLocalTheme && (prefs.theme === 'dark' || prefs.theme === 'light')) {
        applyTheme(prefs.theme);
    }

    if (!hasLocalAccent && prefs.accent_color && accentMap[prefs.accent_color]) {
        applyAccent(prefs.accent_color);
    }
}

function persistPreferencesToServer(nextTheme = theme.value) {
    const page = usePage();
    if (!page.props.auth?.user) {
        return;
    }

    router.patch(
        route('profile.preferences'),
        { theme: nextTheme, accent_color: accent.value },
        { preserveState: true, preserveScroll: true },
    );
}

let initialized = false;

export function useTheme() {
    if (!initialized && typeof window !== 'undefined') {
        initFromStorage();
        initialized = true;
    }

    const page = usePage();
    watch(
        () => page.props.auth?.preferences,
        (prefs) => syncFromServer(prefs),
        { immediate: true, deep: true },
    );

    const toggle = () => {
        const next = theme.value === 'dark' ? 'light' : 'dark';
        applyTheme(next);
        persistPreferencesToServer(next);
    };
    const setTheme = (value) => applyTheme(value);
    const setAccent = (value) => applyAccent(value);

    return { theme, accent, toggle, setTheme, setAccent, accentOptions: Object.keys(accentMap) };
}

export function getThemeSnapshot() {
    return { theme: theme.value, accent: accent.value };
}
