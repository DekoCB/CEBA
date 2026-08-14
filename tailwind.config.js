import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', 'Segoe UI', 'Helvetica Neue', 'Arial', 'sans-serif'],
                display: ['Quantico', 'ui-sans-serif', 'system-ui', 'Segoe UI', 'Arial', 'sans-serif'],
                mono: ['ui-monospace', 'Cascadia Code', 'SFMono-Regular', 'Consolas', 'Liberation Mono', 'monospace'],
            },
            colors: {
                bg: 'rgb(var(--color-bg) / <alpha-value>)',
                surface: 'rgb(var(--color-surface) / <alpha-value>)',
                'surface-2': 'rgb(var(--color-surface-2) / <alpha-value>)',
                border: 'rgb(var(--color-border) / <alpha-value>)',
                ink: 'rgb(var(--color-text) / <alpha-value>)',
                'ink-dim': 'rgb(var(--color-text-dim) / <alpha-value>)',
                'ink-faint': 'rgb(var(--color-text-faint) / <alpha-value>)',
                accent: 'rgb(var(--color-accent) / <alpha-value>)',
                'accent-soft': 'rgb(var(--color-accent-soft) / <alpha-value>)',
                gold: 'rgb(var(--color-gold) / <alpha-value>)',
                ok: 'rgb(var(--color-ok) / <alpha-value>)',
                warn: 'rgb(var(--color-warn) / <alpha-value>)',
                danger: 'rgb(var(--color-danger) / <alpha-value>)',
                info: 'rgb(var(--color-info) / <alpha-value>)',
            },
        },
    },

    plugins: [forms],
};
