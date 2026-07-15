<?php

return [
    'features' => [
        'oauth_github' => env('FEATURE_OAUTH_GITHUB', true),
        'oauth_gitlab' => env('FEATURE_OAUTH_GITLAB', true),
        'siwe' => env('FEATURE_SIWE', true),
        'two_factor' => env('FEATURE_2FA', true),
        'anonymous_browse' => env('FEATURE_ANON_BROWSE', true),
        'meilisearch' => env('FEATURE_MEILISEARCH', false),
        'messaging' => env('FEATURE_MESSAGING', false),
        'reputation' => env('FEATURE_REPUTATION', false),
        'sponsoring' => env('FEATURE_SPONSORING', false),
        'donations' => env('FEATURE_DONATIONS', false),
        'dac7_reporting' => env('FEATURE_DAC7', false),
        'web3_escrow' => env('FEATURE_WEB3_ESCROW', false),
        'ipfs_pinning' => env('FEATURE_IPFS', false),
        'umami_analytics' => env('FEATURE_UMAMI', false),
        'homelab_feed' => env('FEATURE_HOMELAB_FEED', true),
        'invites' => env('FEATURE_INVITES', true),
        'stats' => env('FEATURE_STATS', true),
        'trust' => env('FEATURE_TRUST', true),
        'trust_autopublish' => env('FEATURE_TRUST_AUTOPUBLISH', false),
        'deals' => env('FEATURE_DEALS', true),
        'homelab_upvotes' => env('FEATURE_HOMELAB_UPVOTES', true),
        // Close registration once the first-100 founding cohort is full and
        // collect emails on a waitlist instead. Set false to keep signups open.
        'waitlist' => env('FEATURE_WAITLIST', true),
    ],
    'photos' => [
        /*
         * The listing-photo limits, in one place because they are enforced in
         * several and the strictest wins silently.
         *
         * `max_bytes` MUST stay <= PHP's upload_max_filesize (8M, set in
         * docker/php-fpm/Dockerfile) — PHP discards a bigger file before
         * Laravel ever sees it, and the user gets "failed to upload" with no
         * clue why. nginx's client_max_body_size (88m) caps the whole request:
         * Livewire posts every selected photo in ONE request, so the browser
         * checks `max_count * max_bytes` against it before starting an upload
         * that nginx would reject after minutes on a phone.
         *
         * Changing these means changing the Dockerfile and nginx too.
         */
        'max_bytes' => 8 * 1024 * 1024,
        'max_count' => 10,
    ],
    'traffic' => [
        // Where nginx writes its access log (see docker/nginx/default.conf).
        // Configurable so tests never touch the file the live nginx master
        // owns — it runs as root and its stale log otherwise blocks the
        // www-data test fixture.
        'access_log' => env('TRAFFIC_ACCESS_LOG', storage_path('nginx/access.log')),
    ],
    'gamification' => [
        'starting_invite_credits' => 3,
        'karma_invite_activation' => 10,
    ],
    'storage' => [
        'driver' => env('LISTING_STORAGE_DRIVER', 'local'),
    ],
    'dac7' => [
        'threshold_transactions' => 30,
        'threshold_eur_cents' => 200_000,
    ],
    'oauth' => [
        'github' => [
            'client_id' => env('GITHUB_CLIENT_ID'),
            'client_secret' => env('GITHUB_CLIENT_SECRET'),
            'redirect' => env('APP_URL').'/oauth/github/callback',
        ],
        'gitlab' => [
            'client_id' => env('GITLAB_CLIENT_ID'),
            'client_secret' => env('GITLAB_CLIENT_SECRET'),
            'redirect' => env('APP_URL').'/oauth/gitlab/callback',
        ],
    ],
];
