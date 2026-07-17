<?php

declare(strict_types=1);

/*
 * De FAQ claimde dat de app offline draait — onwaar, er is geen service
 * worker. Bij een merk dat "we bouwen wat we claimen" hoog houdt, is dat een
 * eerlijkheidsgat. De claim is nu "installeerbaar", niet "offline".
 */
it('claims installable, not offline, for the PWA', function () {
    $html = (string) $this->get('/faq')->getContent();

    expect($html)->toContain('aan je beginscherm')
        ->and($html)->not->toContain('draait offline');
});
