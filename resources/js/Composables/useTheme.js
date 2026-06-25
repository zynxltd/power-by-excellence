import { onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const THEME_KEY = 'pbe-theme';
const ACCENT_KEY = 'pbe-accent';

const theme = ref('light');
const accent = ref('indigo');

const accentMap = {
    violet: { from: '#7c3aed', to: '#4f46e5', ring: '#8b5cf6', bg: '#7c3aed' },
    indigo: { from: '#6366f1', to: '#4f46e5', ring: '#6366f1', bg: '#4f46e5' },
    emerald: { from: '#10b981', to: '#059669', ring: '#34d399', bg: '#059669' },
    rose: { from: '#f43f5e', to: '#e11d48', ring: '#fb7185', bg: '#e11d48' },
    amber: { from: '#f59e0b', to: '#d97706', ring: '#fbbf24', bg: '#d97706' },
    cyan: { from: '#06b6d4', to: '#0891b2', ring: '#22d3ee', bg: '#0891b2' },
};

function applyTheme(value) {
    const root = document.documentElement;
    root.classList.toggle('dark', value === 'dark');
    theme.value = value;
    localStorage.setItem(THEME_KEY, value);
}

function applyAccent(value) {
    const root = document.documentElement;
    const colors = accentMap[value] ?? accentMap.indigo;
    root.dataset.accent = value;
    root.style.setProperty('--accent-from', colors.from);
    root.style.setProperty('--accent-to', colors.to);
    root.style.setProperty('--accent-ring', colors.ring);
    root.style.setProperty('--accent-bg', colors.bg);
    accent.value = value;
    localStorage.setItem(ACCENT_KEY, value);
}

function initFromStorage() {
    const storedTheme = localStorage.getItem(THEME_KEY);
    const storedAccent = localStorage.getItem(ACCENT_KEY);

    if (storedTheme === 'dark' || storedTheme === 'light') {
        applyTheme(storedTheme);
    } else {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        applyTheme(prefersDark ? 'dark' : 'light');
    }

    applyAccent(storedAccent && accentMap[storedAccent] ? storedAccent : 'indigo');
}

function syncFromServer(prefs) {
    if (!prefs) return;
    // Local toggles win over server defaults — prevents flash to light on Inertia navigation
    if (!localStorage.getItem(THEME_KEY) && prefs.theme) {
        applyTheme(prefs.theme);
    }
    if (!localStorage.getItem(ACCENT_KEY) && prefs.accent_color) {
        applyAccent(prefs.accent_color);
    }
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

    const toggle = () => applyTheme(theme.value === 'dark' ? 'light' : 'dark');
    const setTheme = (value) => applyTheme(value);
    const setAccent = (value) => applyAccent(value);

    return { theme, accent, toggle, setTheme, setAccent, accentOptions: Object.keys(accentMap) };
}

export function getThemeSnapshot() {
    return { theme: theme.value, accent: accent.value };
}
