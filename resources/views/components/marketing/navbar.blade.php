<header class="border-b border-cmp-border bg-cmp-surface/90 backdrop-blur supports-[backdrop-filter]:bg-cmp-surface/75 sticky top-0 z-30">
    <nav class="mx-auto flex max-w-6xl items-center justify-between gap-2 px-4 py-4 sm:px-8">
        <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2 sm:gap-3" aria-label="Cloudmarktplaats — naar de homepage">
            <x-marketing.logo :size="54" class="h-9 w-9 shrink-0 sm:h-[54px] sm:w-[54px]" />
            <span class="truncate font-display text-lg font-bold tracking-display-tight text-cmp-text sm:text-[1.6875rem]">
                cloud<span class="text-cmp-signal">marktplaats</span><span class="hidden text-cmp-muted sm:inline">.nl</span>
            </span>
        </a>

        <div class="flex shrink-0 items-center gap-1 sm:gap-3">
            {{-- Subtle wayfinding links; the grid and auth CTAs stay the focus. --}}
            <a href="{{ route('donate') }}" class="hidden text-sm text-cmp-muted hover:text-cmp-signal sm:inline">{{ __('Doneren') }}</a>
            <a href="{{ route('roadmap') }}" class="hidden text-sm text-cmp-muted hover:text-cmp-text sm:inline">{{ __('Roadmap') }}</a>
            <span class="hidden sm:inline-flex items-center gap-1 font-mono text-[11px] text-cmp-faint">
                <a href="{{ route('locale.switch', 'nl') }}" @class(['hover:text-cmp-ink', 'text-cmp-ink font-medium' => app()->getLocale() === 'nl'])>NL</a>
                <span aria-hidden="true">·</span>
                <a href="{{ route('locale.switch', 'en') }}" @class(['hover:text-cmp-ink', 'text-cmp-ink font-medium' => app()->getLocale() === 'en'])>EN</a>
            </span>
            @auth
                {{-- Short label on mobile, full label from sm: up. --}}
                <a href="{{ route('listings.create') }}" class="cmp-btn cmp-btn-primary px-3 sm:px-5">
                    <span class="sm:hidden">{{ __('Plaatsen') }}</span>
                    <span class="hidden sm:inline">{{ __('Advertentie plaatsen') }}</span>
                </a>
                <form method="POST" action="{{ route('logout') }}" class="inline">@csrf
                    <button type="submit" class="cmp-btn cmp-btn-ghost px-2 sm:px-5">{{ __('Uitloggen') }}</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="cmp-btn cmp-btn-ghost px-2 sm:px-5">{{ __('Inloggen') }}</a>
                <a href="{{ route('register') }}" class="cmp-btn cmp-btn-primary px-3 sm:px-5">
                    <span class="sm:hidden">{{ __('Plaatsen') }}</span>
                    <span class="hidden sm:inline">{{ __('Advertentie plaatsen') }}</span>
                </a>
            @endauth
        </div>
    </nav>
</header>
