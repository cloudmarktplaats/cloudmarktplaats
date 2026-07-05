<footer class="border-t border-cmp-border bg-cmp-bg2 mt-24">
    <div class="mx-auto max-w-6xl px-5 py-12 sm:px-8">
        <div class="grid grid-cols-1 gap-10 sm:grid-cols-3">
            <div>
                <div class="cmp-section-label mb-4">{{ __('Over') }}</div>
                <ul class="space-y-2 text-sm text-cmp-muted">
                    <li><a href="{{ route('about') }}" class="hover:text-cmp-text">{{ __('Over ons') }}</a></li>
                    <li><a href="{{ route('values') }}" class="hover:text-cmp-text">{{ __('Onze waarden') }}</a></li>
                    <li><a href="{{ route('faq') }}" class="hover:text-cmp-text">{{ __('Veelgestelde vragen') }}</a></li>
                    <li><a href="{{ route('donate') }}" class="hover:text-cmp-text">{{ __('Doneren') }}</a></li>
                    <li><a href="{{ route('sponsor') }}" class="hover:text-cmp-text">{{ __('Sponsoring') }}</a></li>
                    <li><a href="{{ route('legal.show', 'tos') }}" class="hover:text-cmp-text">{{ __('Gebruiksvoorwaarden') }}</a></li>
                    <li><a href="{{ route('legal.show', 'privacy') }}" class="hover:text-cmp-text">{{ __('Privacyverklaring') }}</a></li>
                </ul>
            </div>

            <div>
                <div class="cmp-section-label mb-4">{{ __('Links') }}</div>
                <ul class="space-y-2 text-sm text-cmp-muted">
                    <li><a href="{{ route('listings.index') }}" class="hover:text-cmp-text">{{ __('Alle advertenties') }}</a></li>
                    <li><a href="{{ route('listings.search') }}" class="hover:text-cmp-text">{{ __('Zoeken') }}</a></li>
                    <li><a href="{{ route('roadmap') }}" class="hover:text-cmp-text">{{ __('Roadmap') }}</a></li>
                    @if (config('cloudmarktplaats.features.homelab_feed'))
                        <li><a href="{{ route('homelabs') }}" class="hover:text-cmp-text">{{ __('Homelabs') }}</a></li>
                    @endif
                    <li><a href="{{ route('register') }}" class="hover:text-cmp-text">{{ __('Account aanmaken') }}</a></li>
                    <li><a href="{{ route('login') }}" class="hover:text-cmp-text">{{ __('Inloggen') }}</a></li>
                </ul>
            </div>

            <div>
                <div class="cmp-section-label mb-4">{{ __('Community') }}</div>
                <ul class="space-y-2 text-sm text-cmp-muted">
                    <li><a href="https://github.com/cloudmarktplaats/cloudmarktplaats" class="hover:text-cmp-text" rel="noopener external">GitHub</a></li>
                    <li><a href="https://github.com/cloudmarktplaats/cloudmarktplaats/issues" class="hover:text-cmp-text" rel="noopener external">{{ __('Issues & bugs') }}</a></li>
                    <li><a href="https://github.com/sponsors/NickAldewereld" class="hover:text-cmp-text" rel="noopener external">GitHub Sponsors</a></li>
                    <li><a href="mailto:sponsoring@cloudmarktplaats.nl" class="hover:text-cmp-text">sponsoring@cloudmarktplaats.nl</a></li>
                </ul>
            </div>
        </div>

        <div class="mt-12 border-t border-cmp-border pt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <p class="font-mono text-[11px] text-cmp-faint">
                cloudmarktplaats.nl · AGPL-3.0 ·
                <a href="https://github.com/cloudmarktplaats/cloudmarktplaats" class="underline hover:text-cmp-muted" rel="noopener external">{{ __('code op GitHub') }}</a>
            </p>
            <p class="font-mono text-[11px] text-cmp-faint">
                {{ __('Geen trackers. Geen cookiebanner. Geen bullshit.') }}
            </p>
        </div>
    </div>
</footer>
