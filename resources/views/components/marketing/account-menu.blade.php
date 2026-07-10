{{--
    Shared account dropdown for logged-in users. Used by BOTH navs — the
    marketing navbar (components/marketing/navbar.blade.php) and the app
    layout used by the listing wizard (layouts/app.blade.php) — so the two
    never drift apart again. Feature-flagged pages (stats/deals/invites)
    only appear when their flag is on, otherwise the link would 404.
--}}
@auth
    @php
        $item = 'block px-4 py-2 text-sm text-cmp-text hover:bg-cmp-bg hover:text-cmp-signal transition-colors';
    @endphp
    <div x-data="{ open: false }" class="relative" @keydown.escape.window="open = false">
        <button
            type="button"
            @click="open = ! open"
            x-bind:aria-expanded="open.toString()"
            aria-haspopup="true"
            class="cmp-btn cmp-btn-ghost inline-flex items-center gap-1.5 px-2 sm:px-4"
        >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span class="hidden max-w-[12ch] truncate font-mono text-[12px] sm:inline">{{ auth()->user()->display_name }}</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" x-bind:class="open ? 'rotate-180' : ''" class="transition-transform"><path d="m6 9 6 6 6-6"/></svg>
        </button>

        <div
            x-show="open"
            x-cloak
            x-transition.origin.top.right
            @click.outside="open = false"
            class="absolute right-0 z-40 mt-2 w-56 origin-top-right rounded-sm border border-cmp-border bg-cmp-surface py-1 shadow-lg"
            role="menu"
            aria-label="{{ __('Account') }}"
        >
            <div class="border-b border-cmp-border px-4 py-2 sm:hidden">
                <span class="font-mono text-[12px] text-cmp-muted">{{ auth()->user()->display_name }}</span>
            </div>

            <a href="{{ route('listings.mine') }}" class="{{ $item }}" role="menuitem">{{ __('Mijn advertenties') }}</a>

            @if (config('cloudmarktplaats.features.stats'))
                <a href="{{ route('profile.stats') }}" class="{{ $item }}" role="menuitem">{{ __('Statistieken') }}</a>
            @endif
            @if (config('cloudmarktplaats.features.deals'))
                <a href="{{ route('profile.deals') }}" class="{{ $item }}" role="menuitem">{{ __('Mijn deals') }}</a>
            @endif
            @if (config('cloudmarktplaats.features.invites'))
                <a href="{{ route('profile.invites') }}" class="{{ $item }}" role="menuitem">{{ __('Uitnodigingen') }}</a>
            @endif

            <a href="{{ route('profile.security') }}" class="{{ $item }}" role="menuitem">{{ __('Instellingen & beveiliging') }}</a>

            <div class="my-1 border-t border-cmp-border"></div>

            <form method="POST" action="{{ route('logout') }}" role="none">@csrf
                <button type="submit" class="{{ $item }} w-full text-left" role="menuitem">{{ __('Uitloggen') }}</button>
            </form>
        </div>
    </div>
@endauth
