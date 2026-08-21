<?php

declare(strict_types=1);

/*
 * Claim tokens travel by mail only, but a seller who pastes his claim link
 * into a forum post turns it into an indexable page carrying his username,
 * the listing title and the price. robots.txt is the only thing standing
 * between that link and a search engine.
 */
it('disallows crawling of claim links', function () {
    $body = (string) file_get_contents(public_path('robots.txt'));

    expect($body)->toContain('Disallow: /deal/');
});
