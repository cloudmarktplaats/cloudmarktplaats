<?php

declare(strict_types=1);
use App\Livewire\Listings\Browse;
use Livewire\Attributes\Locked;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
 * `/c/a...b` gaf een 500, geverifieerd op productie op 01-09-2026 met 1 request.
 *
 * De routebeperking `[a-z0-9._-]+` houdt aanhalingstekens en slashes tegen,
 * maar een string die alleen uit toegestane tekens bestaat en toch geen geldige
 * ltree is, komt ongehinderd bij `whereRaw('path <@ ?::ltree')` en die werpt een
 * QueryException. Injectie is het niet: `'; DROP TABLE users; --` liep erop stuk
 * en de tabel stond er nog. Het is een gratis knop om de foutenlog mee te vullen.
 *
 * Alle 113 echte categoriepaden zijn kleine letters, cijfers en punten, hoogstens
 * 28 tekens en 2 niveaus diep. Geen enkele heeft een streepje, terwijl de route
 * dat wel toestaat. Een pad dat niet aan die vorm voldoet kan dus nooit bestaan,
 * en 404 is daar het eerlijke antwoord op.
 */

it('answers 404 instead of crashing on a path that is not a valid ltree', function (string $pad) {
    $this->get('/c/'.$pad)->assertNotFound();
})->with([
    'dubbele punt' => 'a...b',
    'punt aan het eind' => 'compute.',
    'punt aan het begin' => '.compute',
    'streepje' => 'niet-bestaand',
    'absurd lang label' => str_repeat('a', 3000),
    'absurd diep' => str_repeat('a.', 200).'a',
]);

it('still serves a real category', function () {
    $this->get('/c/compute')->assertOk();
});

it('still serves the unfiltered index', function () {
    $this->get('/listings')->assertOk();
});

/*
 * De tweede deur. `mount()` draait alleen bij de eerste paginalading; elke
 * volgende Livewire-ronde (sorteren, meer laden) gaat rechtstreeks naar
 * `render()`. Stond de controle alleen in `mount()`, dan gaf een component die
 * al leefde alsnog een 500 op een onparseerbaar pad.
 *
 * Deze test zet het pad rechtstreeks, dus zonder langs `mount()` te gaan, en
 * dat is precies de toestand waarin de fout op 01-09-2026 in de log belandde.
 */
it('also refuses an unparseable path when render is reached directly', function () {
    $component = new Browse;
    $component->categoryPath = 'a...b';

    $component->render();
})->throws(NotFoundHttpException::class);

/*
 * En het slot erop, dat in de praktijk de eerste verdediging is: het pad is een
 * routeparameter en geen invoerveld, dus een Livewire-vervolgverzoek mag hem
 * niet zetten. Gemeten toen deze test er kwam: Livewire gooit dan
 * `CannotUpdateLockedPropertyException` en `render()` wordt niet eens bereikt.
 * De controle in `render()` blijft er als tweede slot op staan, want een slot
 * dat je alleen kent uit een attribuut is makkelijk weg te halen.
 */
it('locks the category path against client-side updates', function () {
    $eigenschap = new ReflectionProperty(Browse::class, 'categoryPath');

    expect($eigenschap->getAttributes(Locked::class))->not->toBeEmpty();
});
