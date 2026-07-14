@props([
    'title'       => 'Cloudmarktplaats — open source marktplaats voor tech',
    'description' => 'Nederlandse, open source marktplaats voor IT-hardware. Peer-to-peer, geen trackers, code op GitHub.',
    'canonical'   => null,
    'ogImage'     => null,
    'jsonLd'      => null,
])
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">

    <!--
                                                                %###%%%%###
                                                             %#%            @
                                                            #%
                                                           #%
                                                          @#@
                                                          @#
                                                          @#
                                                           #%                %@@
                                                           ##   %%##%%#%   ##%%%
                                                          ###%  %###@%%
                                                          #%@%
             ###%     %##%  ###%    @%######@ %##%    %###%% %                %%
             #####    %##%  ###%  %#########% %###   %###@  %%%         %%% %%
             ######   %##%  ###% ####%@    %  %### %####     %#      @#%%@%% @%##@#
             ###%###@ %##%  ###%%###@         %#######%       %%   %#####%  @% @##%
             ###%%###%%##%  ###%%###          %#######         %%#@@#          @#%
             ###% %######%  ###%%###%         %###%####%        %##%%%   ###%  @#
             ###%   #####%  ###% %####%%%%%#% %###  %####@        %###%       @#%
             ###%    %###%  ###%   %#########@%###    #####          %##%   ###@
             %%@      @%%@  %%@       %%%%%   @%%@     @%%%%             @%%@
      %##%  @##   %*###*# @########  ##%  **######@######  ##### ##@   ######     %*##  ##@@##
     #*#### @##   %*#  %##@##    ##@%*#* %## ##@   ##@ ##@ ##    ##@   ##  @#*@   %*### ##@@##
     ##@@##@@##   %*#  %##@##### #####%#%**% ##### #####%  ##### ##@   ##   #*%   %*#%####@@##
    ########@#####%*####*%@####%  #### ###%  ##%## ##@###  ##### ##%## ##%%##% ##%%*#  ###@@####%
   %#%    %#%####%@###%%   ####%  %##  %##   %#### %#@ %##@####% %#### %##%#   %#%@#%   %#@@####%

    Gebouwd door Nick Aldewereld — https://nickaldewereld.nl
    Cloudmarktplaats is open source: https://github.com/cloudmarktplaats/cloudmarktplaats
    -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">

    @if($canonical)
        <link rel="canonical" href="{{ $canonical }}">
    @else
        <link rel="canonical" href="{{ url()->current() }}">
    @endif

    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Cloudmarktplaats">
    <meta property="og:locale" content="nl_NL">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
    <meta property="og:image" content="{{ $ogImage ?? asset('og-default.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $ogImage ?? asset('og-default.png') }}">

    {{-- Self-hosted fonts (IBM Plex superfamilie). No Google Fonts:
         privacy by design, and no render-blocking third party. Preload
         only the three weights above the fold; the rest swaps in. --}}
    <link rel="preload" href="{{ asset('fonts/ibm-plex-sans-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/ibm-plex-sans-condensed-latin-700-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/ibm-plex-mono-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    {{-- Inline favicon (same circuit-cloud mark) so we don't ship a separate request. --}}
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml;utf8,<svg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 44 44%27><path d=%27M10 28C6.7 28 4 25.3 4 22C4 19.1 6 16.7 8.7 16.1C8.3 15.1 8 14 8 13C8 9.1 11.1 6 15 6C16.8 6 18.4 6.7 19.6 7.8C21 6.7 22.8 6 24.8 6C29.2 6 32.8 9.2 33.4 13.4C33.6 13.3 33.8 13.3 34 13.3C37.3 13.3 40 16 40 19.3C40 22.3 37.8 24.8 34.9 25.3L34.9 28Z%27 fill=%27%23FFFFFF%27 stroke=%27%2317191B%27 stroke-width=%271.5%27/><circle cx=%2714%27 cy=%2728%27 r=%272.5%27 fill=%27%2317191B%27/><circle cx=%2726%27 cy=%2718%27 r=%272%27 fill=%27%23D9480F%27/><circle cx=%2730%27 cy=%2728%27 r=%272.5%27 fill=%27%2317191B%27/></svg>">

    @if($jsonLd)
        <script type="application/ld+json">{!! $jsonLd !!}</script>
    @endif
</head>
<body class="bg-cmp-bg text-cmp-text font-sans antialiased">

    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:bg-cmp-ink focus:text-white focus:px-3 focus:py-2 focus:rounded-sm">{{ __('Naar de inhoud') }}</a>

    <x-marketing.navbar />

    <main id="main">
        {{ $slot }}
    </main>

    <x-marketing.footer />

    @livewireScripts
</body>
</html>
