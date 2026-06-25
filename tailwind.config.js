import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                display: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50: '#f5f3ff',
                    100: '#ede9fe',
                    200: '#ddd6fe',
                    300: '#c4b5fd',
                    400: '#a78bfa',
                    500: '#8b5cf6',
                    600: '#7c3aed',
                    700: '#6d28d9',
                    indigo: '#6366f1',
                    cyan: '#22d3ee',
                },
            },
            backgroundImage: {
                'brand-gradient': 'linear-gradient(to right, #8b5cf6, #6366f1, #22d3ee)',
                'brand-gradient-br': 'linear-gradient(to bottom right, #a78bfa, #6366f1, #22d3ee)',
                'brand-mesh-light': 'linear-gradient(180deg, #fafbff 0%, #f8fafc 45%, #f1f5f9 100%), radial-gradient(ellipse 70% 55% at 100% 0%, rgba(99,102,241,0.07), transparent 55%), radial-gradient(ellipse 55% 45% at 0% 100%, rgba(34,211,238,0.06), transparent 50%)',
                'brand-mesh-dark': 'radial-gradient(ellipse 90% 60% at 10% -5%, rgba(124,58,237,0.25), transparent 55%), radial-gradient(ellipse 70% 50% at 95% 5%, rgba(34,211,238,0.12), transparent 50%), radial-gradient(ellipse 80% 40% at 50% 100%, rgba(99,102,241,0.15), transparent 55%)',
            },
            boxShadow: {
                brand: '0 4px 24px -4px rgba(99, 102, 241, 0.25), 0 0 0 1px rgba(167, 139, 250, 0.1)',
                'brand-lg': '0 8px 40px -8px rgba(99, 102, 241, 0.35), 0 0 0 1px rgba(34, 211, 238, 0.08)',
            },
            animation: {
                'pulse-slow': 'pulse-slow 6s ease-in-out infinite',
                float: 'float 8s ease-in-out infinite',
            },
        },
    },

    plugins: [
        forms,
        function ({ addVariant }) {
            addVariant('marketing-dark', '.marketing-dark &');
        },
    ],
};
