<header class="border-b border-cmp-border bg-cmp-surface/90 backdrop-blur supports-[backdrop-filter]:bg-cmp-surface/75 sticky top-0 z-30">
    <nav class="mx-auto flex max-w-6xl items-center justify-between px-5 py-4 sm:px-8">
        <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="Cloudmarktplaats — naar de homepage">
            <x-marketing.logo :size="36" />
            <span class="font-display text-lg font-bold tracking-display-tight text-cmp-text">
                cloud<span class="text-cmp-signal">marktplaats</span><span class="text-cmp-muted">.nl</span>
            </span>
        </a>

        <div class="flex items-center gap-2 sm:gap-3">
            {{-- Subtle wayfinding link; the grid and auth CTAs stay the focus. --}}
            <a href="{{ route('roadmap') }}" class="hidden text-sm text-cmp-muted hover:text-cmp-text sm:inline">Roadmap</a>
            @auth
                <a href="{{ route('listings.create') }}" class="cmp-btn cmp-btn-primary">Advertentie plaatsen</a>
                <form method="POST" action="{{ route('logout') }}" class="inline">@csrf
                    <button type="submit" class="cmp-btn cmp-btn-ghost">Uitloggen</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="cmp-btn cmp-btn-ghost">Inloggen</a>
                <a href="{{ route('register') }}" class="cmp-btn cmp-btn-primary">Advertentie plaatsen</a>
            @endauth
        </div>
    </nav>
</header>
