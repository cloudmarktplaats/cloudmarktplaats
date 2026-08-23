import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // LET OP: hier stond `./storage/framework/views/*.php` — de Blade-cache.
    // Dat maakte de build onbepaald: dezelfde broncode leverde 53 kB CSS met
    // een lege cache, 85 kB met een half warme en 96 kB na `view:cache`. Wie
    // `composer install` had gedraaid (dat roept `view:clear` aan) deployde dus
    // stilletjes een andere stylesheet dan zijn collega. Gevonden op 23-08,
    // toen een build ineens 32 kB kleiner uitviel.
    //
    // Het verschil was bovendien niet van ons: het waren Filament-klassen uit
    // gecompileerde vendor-views. Filament heeft geen `viteTheme` en laadt zijn
    // eigen `public/css/filament/...`, dus die 43 kB werd naar elke bezoeker
    // gestuurd zonder ooit gebruikt te worden.
    //
    // Voeg hier geen cache- of buildmap toe. Wat Tailwind scant hoort broncode
    // te zijn, anders hangt je stylesheet af van wat er toevallig in een map
    // stond.
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
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
