<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Listing;

/**
 * Single source of truth for share URLs and their UTM tagging.
 *
 * UTM parameters measure where traffic *comes from*, and they belong on the
 * destination — our own listing URL. Tagging our own link with
 * `utm_source=cloudmarktplaats` would measure itself; the source is the
 * platform the visitor clicked *from*.
 *
 * Every share URL is built on the canonical `listings.detail` route with the
 * listing's current slug. Detail::mount() permanently redirects a mismatched
 * slug, and a crawler is not guaranteed to follow that hop — so a stale slug
 * silently costs us the preview.
 */
class ShareLinkBuilder
{
    public const CAMPAIGN_SHARE = 'seller_share';

    public const CAMPAIGN_PUBLISHED = 'listing_published';

    public function listingUrl(Listing $listing, string $source, string $medium, string $campaign): string
    {
        $url = route('listings.detail', [
            'ulid' => $listing->ulid,
            'slug' => $listing->slug,
        ]);

        return $url.'?'.http_build_query([
            'utm_source' => $source,
            'utm_medium' => $medium,
            'utm_campaign' => $campaign,
        ]);
    }

    public function linkedIn(Listing $listing): string
    {
        $target = $this->listingUrl($listing, 'linkedin', 'social', self::CAMPAIGN_SHARE);

        // LinkedIn ignores title/summary/text on share-offsite (since ~2021) —
        // the post is rendered entirely from the target page's OG tags.
        return 'https://www.linkedin.com/sharing/share-offsite/?url='.urlencode($target);
    }

    /**
     * MainDeck has no confirmed share-intent endpoint: /share and /compose exist
     * but sit behind /login, and the login redirect drops the query string. v1
     * links to the site; shareText() carries the copyable text.
     */
    public function mainDeckUrl(): string
    {
        return 'https://maindeck.eu/';
    }

    public function emailUrl(Listing $listing): string
    {
        return $this->listingUrl($listing, 'email', 'email', self::CAMPAIGN_PUBLISHED);
    }

    public function copyUrl(Listing $listing): string
    {
        return $this->listingUrl($listing, 'copy', 'social', self::CAMPAIGN_SHARE);
    }

    public function shareText(Listing $listing): string
    {
        return sprintf(
            '%s — € %s op Cloudmarktplaats: %s',
            $listing->title,
            number_format($listing->price_cents / 100, 2, ',', '.'),
            $this->copyUrl($listing),
        );
    }
}
