<?php

declare(strict_types=1);

/*
 * RFC 9116. `/.well-known/security.txt` gaf 404, gemeten op productie op
 * 01-09-2026, terwijl `SECURITY.md` in de repo al netjes een meldadres noemt.
 * Wie een gat vindt, kijkt daar en niet in een repo.
 *
 * De `Expires:`-regel wordt bij elk verzoek berekend en niet vastgezet. Een
 * verlopen security.txt is precies het soort belofte-zonder-onderhoud waar dit
 * project zich al eerder aan brandde: het privacybeleid beloofde maandenlang
 * verwijdering die niet bestond. Een datum die vanzelf meeloopt kan niet
 * stilletjes verlopen.
 */

it('serves a security.txt at the location the RFC prescribes', function () {
    $this->get('/.well-known/security.txt')
        ->assertOk()
        ->assertHeader('content-type', 'text/plain; charset=UTF-8');
});

it('names the address that SECURITY.md already names', function () {
    // Zakt deze test, dan lopen het bestand en de site uit elkaar en stuurt
    // iemand zijn melding naar een adres dat niemand leest.
    expect(file_get_contents(base_path('SECURITY.md')))
        ->toContain('privacy@cloudmarktplaats.nl');

    $this->get('/.well-known/security.txt')
        ->assertSee('Contact: mailto:privacy@cloudmarktplaats.nl');
});

it('carries the two fields the RFC makes mandatory, with an expiry in the future', function () {
    $body = $this->get('/.well-known/security.txt')->getContent();

    expect($body)->toContain('Contact:')->toContain('Expires:');

    preg_match('/^Expires: (.+)$/m', (string) $body, $m);

    expect($m[1] ?? null)->not->toBeNull()
        ->and(Carbon\Carbon::parse($m[1])->isFuture())->toBeTrue();
});

it('points at the policy and the canonical location', function () {
    $this->get('/.well-known/security.txt')
        ->assertSee('Policy: https://github.com/cloudmarktplaats/cloudmarktplaats/blob/main/SECURITY.md')
        ->assertSee('Canonical: https://cloudmarktplaats.nl/.well-known/security.txt')
        ->assertSee('Preferred-Languages: nl, en');
});

it('also answers on the bare path people type from memory', function () {
    $this->get('/security.txt')->assertRedirect('/.well-known/security.txt');
});
