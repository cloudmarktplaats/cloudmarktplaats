<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
 * Axios zat in elke paginalading zonder dat iets het gebruikte. Het stond in
 * `bootstrap.js`, de standaardregel die Laravel bij een nieuw project meelevert:
 * `window.axios` zetten plus een `X-Requested-With`-header. Livewire praat met
 * `fetch`, en in de hele repo stond geen enkele aanroep.
 *
 * Gemeten op productie op 01-09-2026, vóór het weghalen: `window.axios`
 * verwijderd en vervangen door een val die elke opvraging telt. Daarna de site
 * gebruikt (Livewire-roundtrip op de advertentielijst, lightbox openen en
 * doorbladeren op een detailpagina). Uitkomst beide keren: **0 opvragingen, 0
 * JS-fouten**. Ook `->ajax()`, `expectsJson()` en `X-Requested-With` komen in
 * `app/`, `routes/`, `config/` en `bootstrap/` nergens voor.
 *
 * Deze test bewaakt de bron, niet de bundel: `public/build` is een
 * bouwresultaat en staat in `.gitignore`, dus daar valt in CI niets over te
 * zeggen. Wat hij wél voorkomt is dat de standaardregel er ooit ongemerkt weer
 * in glijdt, bijvoorbeeld bij een upgrade die `bootstrap.js` terugzet.
 */

it('keeps axios out of the frontend sources', function () {
    $overtreders = collect(File::allFiles(resource_path('js')))
        ->filter(fn ($f) => str_contains($f->getContents(), 'axios'))
        ->map(fn ($f) => $f->getRelativePathname())
        ->values()
        ->all();

    expect($overtreders)->toBe([]);
});

it('keeps axios out of package.json', function () {
    $pkg = json_decode((string) file_get_contents(base_path('package.json')), true);

    expect(array_keys($pkg['dependencies'] ?? []))->not->toContain('axios')
        ->and(array_keys($pkg['devDependencies'] ?? []))->not->toContain('axios');
});

/*
 * De twee modules die er wel toe doen moeten blijven staan. Zakt deze test, dan
 * is er meer weggehaald dan de bedoeling was: de lightbox draagt de fotoweergave
 * op elke advertentiepagina.
 */
it('still has the modules that do carry their weight', function () {
    expect(file_get_contents(resource_path('js/app.js')))
        ->toContain('./easter-eggs')
        ->toContain('./photo-lightbox')
        ->and(file_get_contents(resource_path('js/photo-lightbox.js')))
        ->toContain('photoLightbox');
});
