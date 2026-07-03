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
            // Zie docs/DESIGN.md — "datasheet, geen startup". Licht en koel-
            // neutraal; safety-orange is het enige accent, blauw alleen links.
            colors: {
                'cmp-blue':       '#1447CC',
                'cmp-blue-dark':  '#0E3399',
                'cmp-blue-light': '#3B6FE8',
                'cmp-signal':     '#D9480F',
                'cmp-amber':      '#B45309',
                'cmp-bg':         '#F5F6F6',
                'cmp-bg2':        '#EDEFEF',
                'cmp-bg3':        '#E4E7E7',
                'cmp-surface':    '#FFFFFF',
                'cmp-border':     '#D9DDDE',
                'cmp-ink':        '#17191B',
                'cmp-text':       '#17191B',
                'cmp-muted':      '#5A6167',
                'cmp-faint':      '#9AA1A6',
            },
            fontFamily: {
                sans:    ['"IBM Plex Sans"', ...defaultTheme.fontFamily.sans],
                mono:    ['"IBM Plex Mono"', ...defaultTheme.fontFamily.mono],
                display: ['"IBM Plex Sans Condensed"', '"IBM Plex Sans"', ...defaultTheme.fontFamily.sans],
            },
            letterSpacing: {
                'display-tight':   '-0.01em',
                'display-tighter': '-0.015em',
            },
        },
    },
    plugins: [forms],
};
