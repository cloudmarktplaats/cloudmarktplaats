<?php

declare(strict_types=1);

/*
 * /waarden zegt live dat de community het platform bezit, terwijl /sponsors
 * even live stemrecht aan betalende sponsors belooft en leden niets hebben.
 * De kop is waar — AGPL, geen aandeelhouders, geen exit, iedereen kan forken —
 * maar de uitleg eronder moet zeggen wat dat wél en niet betekent, anders leest
 * de pagina als zeggenschap die er niet is.
 */
it('says what ownership does and does not mean', function () {
    $page = $this->get('/waarden');

    $page->assertOk()
        // De kop blijft: die is waar en wordt niet afgezwakt.
        ->assertSee('De community bezit het platform.')
        // Wat eigenaarschap wél is: het recht om weg te lopen met alles.
        ->assertSee('De code is AGPL')
        ->assertSee('het recht om weg te lopen met alles')
        // En wat het niet is. Zonder deze zin leest de kop als zeggenschap.
        ->assertSee('maar we stemmen er niet over');
});

it('keeps the other eleven values intact', function () {
    // $values is één array; één regel wijzigen mag de rest niet raken.
    $page = $this->get('/waarden');

    $page->assertOk()
        ->assertSee('Privacy is een ontwerpkeuze, geen marketing.')
        ->assertSee('Open source, AGPL.')
        ->assertSee('Geen algoritmische manipulatie.')
        ->assertSee('Geen activisme-performance.');
});

it('points at the governance document', function () {
    // "Keuzes leggen we openbaar voor" is een bewering; zonder een vindbare
    // uitleg van hoe dat gaat is het een bewering zonder inhoud.
    $this->get('/waarden')
        ->assertOk()
        ->assertSee('→ Hoe we beslissen')
        ->assertSee('blob/main/docs/GOVERNANCE.md', escape: false);
});

it('ships the governance document it points at', function () {
    // De og-default.png-les: een verwijzing zonder bestand faalt nergens,
    // je ziet het alleen als je erop klikt.
    expect(base_path('docs/GOVERNANCE.md'))->toBeFile();
});
