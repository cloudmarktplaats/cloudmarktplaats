<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Cloudmarktplaats' }}</title>
    <link rel="preload" href="{{ asset('fonts/ibm-plex-sans-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/ibm-plex-mono-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml;utf8,<svg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 44 44%27><path d=%27M10 28C6.7 28 4 25.3 4 22C4 19.1 6 16.7 8.7 16.1C8.3 15.1 8 14 8 13C8 9.1 11.1 6 15 6C16.8 6 18.4 6.7 19.6 7.8C21 6.7 22.8 6 24.8 6C29.2 6 32.8 9.2 33.4 13.4C33.6 13.3 33.8 13.3 34 13.3C37.3 13.3 40 16 40 19.3C40 22.3 37.8 24.8 34.9 25.3L34.9 28Z%27 fill=%27%23FFFFFF%27 stroke=%27%2317191B%27 stroke-width=%271.5%27/><circle cx=%2714%27 cy=%2728%27 r=%272.5%27 fill=%27%2317191B%27/><circle cx=%2726%27 cy=%2718%27 r=%272%27 fill=%27%23D9480F%27/><circle cx=%2730%27 cy=%2728%27 r=%272.5%27 fill=%27%2317191B%27/></svg>">
</head>
<body class="min-h-screen bg-cmp-bg text-cmp-text">
    <header class="border-b border-cmp-border bg-cmp-surface">
        <nav class="mx-auto flex max-w-6xl items-center justify-between px-5 py-3 sm:px-8">
            <a href="/" class="flex items-center gap-2 font-display font-bold" aria-label="Cloudmarktplaats — naar de homepage">
                <x-marketing.logo :size="28" />
                <span>cloud<span class="text-cmp-signal">marktplaats</span><span class="text-cmp-muted">.nl</span></span>
            </a>
            <div class="flex items-center gap-4 text-sm">
                <span class="hidden sm:inline-flex items-center gap-1 font-mono text-[11px] text-cmp-faint">
                    <a href="{{ route('locale.switch', 'nl') }}" @class(['hover:text-cmp-ink', 'text-cmp-ink font-medium' => app()->getLocale() === 'nl'])>NL</a>
                    <span aria-hidden="true">·</span>
                    <a href="{{ route('locale.switch', 'en') }}" @class(['hover:text-cmp-ink', 'text-cmp-ink font-medium' => app()->getLocale() === 'en'])>EN</a>
                </span>
                @auth
                    <span class="font-mono text-[12px] text-cmp-muted">{{ auth()->user()->display_name }}</span>
                    <form method="POST" action="/logout" class="inline">@csrf <button class="cmp-btn-ghost text-sm">{{ __('Uitloggen') }}</button></form>
                @else
                    <a href="/login" class="cmp-btn-ghost text-sm">{{ __('Inloggen') }}</a>
                    <a href="/register" class="text-sm font-medium text-cmp-ink hover:text-cmp-signal">{{ __('Account aanmaken') }}</a>
                @endauth
            </div>
        </nav>
    </header>
    <main class="mx-auto max-w-6xl px-5 py-8 sm:px-8">
        {{ $slot ?? '' }}
        @yield('content')
    </main>
    @livewireScripts
</body>
</html>
