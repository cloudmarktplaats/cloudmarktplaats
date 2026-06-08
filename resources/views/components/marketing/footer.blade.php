<footer class="border-t border-cmp-border bg-cmp-bg2 mt-24">
    <div class="mx-auto max-w-6xl px-5 py-12 sm:px-8">
        <div class="grid grid-cols-1 gap-10 sm:grid-cols-3">
            <div>
                <div class="cmp-section-label mb-4">Over</div>
                <ul class="space-y-2 text-sm text-cmp-muted">
                    <li><a href="{{ route('about') }}" class="hover:text-cmp-text">Over ons</a></li>
                    <li><a href="{{ route('values') }}" class="hover:text-cmp-text">Onze waarden</a></li>
                    <li><a href="{{ route('faq') }}" class="hover:text-cmp-text">Veelgestelde vragen</a></li>
                    <li><a href="{{ route('sponsor') }}" class="hover:text-cmp-text">Sponsoring</a></li>
                </ul>
            </div>

            <div>
                <div class="cmp-section-label mb-4">Links</div>
                <ul class="space-y-2 text-sm text-cmp-muted">
                    <li><a href="{{ route('listings.index') }}" class="hover:text-cmp-text">Alle advertenties</a></li>
                    <li><a href="{{ route('listings.search') }}" class="hover:text-cmp-text">Zoeken</a></li>
                    <li><a href="{{ route('roadmap') }}" class="hover:text-cmp-text">Roadmap</a></li>
                    <li><a href="{{ route('register') }}" class="hover:text-cmp-text">Account aanmaken</a></li>
                    <li><a href="{{ route('login') }}" class="hover:text-cmp-text">Inloggen</a></li>
                </ul>
            </div>

            <div>
                <div class="cmp-section-label mb-4">Community</div>
                <ul class="space-y-2 text-sm text-cmp-muted">
                    <li><a href="https://github.com/cloudmarktplaats/cloudmarktplaats" class="hover:text-cmp-text" rel="noopener external">GitHub</a></li>
                    <li><a href="https://github.com/cloudmarktplaats/cloudmarktplaats/issues" class="hover:text-cmp-text" rel="noopener external">Issues &amp; bugs</a></li>
                    <li><a href="mailto:sponsoring@cloudmarktplaats.nl" class="hover:text-cmp-text">sponsoring@cloudmarktplaats.nl</a></li>
                </ul>
            </div>
        </div>

        <div class="mt-12 border-t border-cmp-border pt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <p class="font-mono text-[11px] text-cmp-faint">
                cloudmarktplaats.nl · AGPL-3.0 ·
                <a href="https://github.com/cloudmarktplaats/cloudmarktplaats" class="underline hover:text-cmp-muted" rel="noopener external">code op GitHub</a>
            </p>
            <p class="font-mono text-[11px] text-cmp-faint">
                Geen trackers. Geen cookiebanner. Geen bullshit.
            </p>
        </div>
    </div>
</footer>
