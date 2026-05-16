<?php

return [
    'features' => [
        'oauth_github'      => env('FEATURE_OAUTH_GITHUB', true),
        'oauth_gitlab'      => env('FEATURE_OAUTH_GITLAB', true),
        'siwe'              => env('FEATURE_SIWE', true),
        'two_factor'        => env('FEATURE_2FA', true),
        'anonymous_browse'  => env('FEATURE_ANON_BROWSE', true),
        'meilisearch'       => env('FEATURE_MEILISEARCH', false),
        'messaging'         => env('FEATURE_MESSAGING', false),
        'reputation'        => env('FEATURE_REPUTATION', false),
        'sponsoring'        => env('FEATURE_SPONSORING', false),
        'donations'         => env('FEATURE_DONATIONS', false),
        'dac7_reporting'    => env('FEATURE_DAC7', false),
        'web3_escrow'       => env('FEATURE_WEB3_ESCROW', false),
        'ipfs_pinning'      => env('FEATURE_IPFS', false),
        'umami_analytics'   => env('FEATURE_UMAMI', false),
    ],
    'storage' => [
        'driver' => env('LISTING_STORAGE_DRIVER', 'local'),
    ],
    'dac7' => [
        'threshold_transactions' => 30,
        'threshold_eur_cents'    => 200_000,
    ],
    'oauth' => [
        'github' => [
            'client_id'     => env('GITHUB_CLIENT_ID'),
            'client_secret' => env('GITHUB_CLIENT_SECRET'),
            'redirect'      => env('APP_URL').'/oauth/github/callback',
        ],
        'gitlab' => [
            'client_id'     => env('GITLAB_CLIENT_ID'),
            'client_secret' => env('GITLAB_CLIENT_SECRET'),
            'redirect'      => env('APP_URL').'/oauth/gitlab/callback',
        ],
    ],
];
