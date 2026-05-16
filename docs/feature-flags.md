# Feature flags

All Foundation feature flags live in `config/cloudmarktplaats.php` under the `features` key and read from `.env` via `env()` so that ops can flip them without redeploying. Every flag below documents:

- **Default** — the value `config('cloudmarktplaats.features.<name>')` returns when no env var is set.
- **Activerend sub-project** — the post-Foundation module that turns this flag on by default and ships the implementation behind it.
- **Beoogde impact** — what changes on the user-facing surface when the flag is true.

| Flag | Env var | Default | Activerend sub-project | Beoogde impact |
|---|---|---|---|---|
| `anonymous_browse` | `FEATURE_ANON_BROWSE` | `true` | Foundation | Niet-ingelogde bezoekers mogen `/listings`, `/c/...` en `/listings/{ulid}-{slug}` benaderen. Zet op `false` voor closed-beta of demo-deployments. |
| `oauth_github` | `FEATURE_OAUTH_GITHUB` | `true` | Foundation | Toont de "Inloggen met GitHub"-knop en activeert `/oauth/github/*` routes. Vereist `GITHUB_CLIENT_ID/SECRET`. |
| `oauth_gitlab` | `FEATURE_OAUTH_GITLAB` | `true` | Foundation | Idem voor GitLab. Vereist `GITLAB_CLIENT_ID/SECRET`. |
| `siwe` | `FEATURE_SIWE` | `true` | Foundation | Activeert SIWE (Sign-In With Ethereum) onboarding + nonce/verify endpoints. |
| `two_factor` | `FEATURE_2FA` | `true` | Foundation | Maakt `/profile/security/2fa` en de 2FA-challenge bereikbaar. Schakel uit voor onboarding-demos waar je 2FA niet wilt opdringen. |
| `messaging` | `FEATURE_MESSAGING` | `false` | **#2 Messaging** | Vervangt de "messaging-coming-soon" notice door de echte chat-surface. |
| `meilisearch` | `FEATURE_MEILISEARCH` | `false` | **#3 Search-upgrade** | Bindt `SearchInterface` aan de Meilisearch-implementatie i.p.v. de Postgres-tsvector default. |
| `reputation` | `FEATURE_REPUTATION` | `false` | **#4 Reviews** | Toont rating + review-aantal op listings + profielen; activeert `/listings/{ulid}/review`. |
| `sponsoring` | `FEATURE_SPONSORING` | `false` | **#5 Sponsoring** | Toont sponsor-tier-CTA in de footer en `/sponsor` route. |
| `donations` | `FEATURE_DONATIONS` | `false` | **#5 Sponsoring** | Activeert de Mollie/Stripe one-off donatie-flow. |
| `dac7_reporting` | `FEATURE_DAC7` | `false` | **#6 DAC7-module** | Activeert de jaarlijkse XML-export via `php artisan dac7:export`. Zie `docs/dac7-position.md`. |
| `web3_escrow` | `FEATURE_WEB3_ESCROW` | `false` | **#7 Web3** | Toont "Beheer escrow" op listings en activeert `Web3Service` met echte on-chain calls. |
| `ipfs_pinning` | `FEATURE_IPFS` | `false` | **#7 Web3** | Bindt `StorageInterface` aan een IPFS-pinning driver voor permanent foto-archief. |
| `umami_analytics` | `FEATURE_UMAMI` | `false` | **#8 Analytics** | Voegt het Umami-tracker-script toe aan `layouts/app`. Zelf-gehost in compose. |

## Toggle conventies

- **Foundation flags** (true by default) zijn voor functionaliteit die nu live is en alleen wordt uitgezet als een operator een specifieke reden heeft (compliance, geo-blocking, demo).
- **Sub-project flags** (false by default) blijven uit tot het sub-project shipping is en de bijbehorende tests + docs erbij zitten.

## Hoe ze worden gebruikt

In code:

```php
if (config('cloudmarktplaats.features.messaging')) {
    // …show real chat surface
}
```

In Blade:

```blade
@if(config('cloudmarktplaats.features.web3_escrow'))
    <livewire:listings.escrow-panel :listing="$listing" />
@endif
```

Wij gebruiken **geen** dedicated feature-flag-library (Pennant, LaunchDarkly) totdat de complexiteit daar reden voor geeft. Voor nu: gewoon `config('…')` checks.
