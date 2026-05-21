import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Livewire/**/*.php',
    ],
    theme: {
        extend: {
            colors: {
                'cmp-blue':       '#1A56FF',
                'cmp-blue-dark':  '#0F3ACC',
                'cmp-blue-light': '#4A7AFF',
                'cmp-signal':     '#00FF88',
                'cmp-amber':      '#F59E0B',
                'cmp-bg':         '#0A0D14',
                'cmp-bg2':        '#0F1320',
                'cmp-bg3':        '#151B2E',
                'cmp-surface':    '#1A2035',
                'cmp-border':     '#1E2A45',
                'cmp-text':       '#E8EDF8',
                'cmp-muted':      '#7B8DB0',
                'cmp-faint':      '#3A4560',
            },
            fontFamily: {
                sans:    ['"Space Grotesk"', ...defaultTheme.fontFamily.sans],
                mono:    ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
                display: ['"Space Grotesk"', ...defaultTheme.fontFamily.sans],
            },
            letterSpacing: {
                'display-tight':   '-0.02em',
                'display-tighter': '-0.03em',
            },
        },
    },
    plugins: [forms],
};
