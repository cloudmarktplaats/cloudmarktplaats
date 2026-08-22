<?php

declare(strict_types=1);

use App\Livewire\Listings\Wizard;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\User;
use App\Services\Listings\ListingStateService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Vooraf-moderatie is er op 22-08-2026 af. De aanleiding was Rob Turk: zijn
 * advertentie stond dagen in de wachtrij, het product was intussen elders
 * verkocht, en hij kon er niets mee. De wachtrij beschermde tegen rommel die
 * we nog nooit gezien hadden en kostte ondertussen echte verkopers.
 *
 * De vlag blijft bestaan (`features.moderation`), zodat terugzetten één
 * configregel is en geen deploy. Als het misgaat willen we dat vandaag kunnen
 * doen, niet volgende week — dat is de hele reden dat dit een vlag is en geen
 * verwijderde codepad.
 */

beforeEach(function () {
    Storage::fake('public');
    $this->category = Category::factory()->create();
    $this->user = User::factory()->create(['email_verified_at' => now()]);
});

it('publishes a submitted listing straight away, no queue', function () {
    config()->set('cloudmarktplaats.features.moderation', false);

    $this->actingAs($this->user);
    $listing = Listing::factory()->for($this->user)->create([
        'category_id' => $this->category->id,
        'state' => 'draft',
        'description' => 'Een beschrijving met ruim voldoende lengte om te slagen.',
    ]);
    ListingPhoto::factory()->for($listing)->create();

    Livewire::test(Wizard::class, ['listing' => $listing])
        ->call('next')
        ->call('next')
        ->call('submit')
        ->assertHasNoErrors();

    $listing->refresh();
    expect($listing->state)->toBe('published')
        ->and($listing->published_at)->not->toBeNull();
});

// De vlag is het terugdraaipad. Gaat dit mis, dan moet één configregel de
// wachtrij terugzetten zonder dat er code herschreven hoeft te worden.
it('puts the listing back in the queue when the flag is switched on', function () {
    config()->set('cloudmarktplaats.features.moderation', true);

    $this->actingAs($this->user);
    $listing = Listing::factory()->for($this->user)->create([
        'category_id' => $this->category->id,
        'state' => 'draft',
        'description' => 'Een beschrijving met ruim voldoende lengte om te slagen.',
    ]);
    ListingPhoto::factory()->for($listing)->create();

    Livewire::test(Wizard::class, ['listing' => $listing])
        ->call('next')
        ->call('next')
        ->call('submit')
        ->assertHasNoErrors();

    expect($listing->fresh()->state)->toBe('pending_review');
});

// Bewerken van een live advertentie zette hem terug in de wachtrij, dus een
// prijswijziging haalde je aanbod dagen offline. Zonder wachtrij is dat weg.
it('puts an edited listing straight back online', function () {
    config()->set('cloudmarktplaats.features.moderation', false);

    $this->actingAs($this->user);
    $listing = Listing::factory()->for($this->user)->published()->create([
        'category_id' => $this->category->id,
        'price_cents' => 10000,
        'description' => 'Originele beschrijving met genoeg tekens erin.',
    ]);
    ListingPhoto::factory()->for($listing)->create();

    Livewire::test(Wizard::class, ['listing' => $listing])
        ->set('price_cents', 27500)
        ->call('next')
        ->call('next')
        ->call('submit')
        ->assertHasNoErrors();

    $listing->refresh();
    expect($listing->state)->toBe('published')
        ->and($listing->price_cents)->toBe(27500);
});

/*
 * `saveDraft()` schreef `'state' => 'draft'` via `fill()->save()`, dus buiten de
 * state machine om. Wie een gepubliceerde advertentie ging bewerken haalde hem
 * daarmee stil offline, en brak hij het bewerken af, dan bleef dat zo — zonder
 * enige melding. Vijf rijen op productie stonden zo op `draft` mét een gevulde
 * `published_at`. Zonder wachtrij vooraf is er geen enkele reden meer om een
 * live advertentie te verbergen terwijl de verkoper aan het typen is.
 */
it('leaves a published listing online while its owner is editing it', function () {
    config()->set('cloudmarktplaats.features.moderation', false);

    $this->actingAs($this->user);
    $listing = Listing::factory()->for($this->user)->published()->create([
        'category_id' => $this->category->id,
        'price_cents' => 10000,
        'description' => 'Originele beschrijving met genoeg tekens erin.',
    ]);
    ListingPhoto::factory()->for($listing)->create();

    // Stap 1 passeren en dan weglopen — precies wat de verkoper deed.
    Livewire::test(Wizard::class, ['listing' => $listing])
        ->set('price_cents', 27500)
        ->call('next');

    expect($listing->fresh()->state)->toBe('published');
});

// Met de wachtrij aan hoort een bewerking wél uit het aanbod te verdwijnen: dan
// is er een menselijk oordeel dat opnieuw gegeven moet worden.
it('does take a published listing offline while editing when moderation is on', function () {
    config()->set('cloudmarktplaats.features.moderation', true);

    $this->actingAs($this->user);
    $listing = Listing::factory()->for($this->user)->published()->create([
        'category_id' => $this->category->id,
        'description' => 'Originele beschrijving met genoeg tekens erin.',
    ]);
    ListingPhoto::factory()->for($listing)->create();

    Livewire::test(Wizard::class, ['listing' => $listing])
        ->set('price_cents', 27500)
        ->call('next');

    expect($listing->fresh()->state)->toBe('draft');
});

// Reactief blijft alles staan: afwijzen en offline halen zijn nog steeds de
// gereedschappen. Alleen de wachtrij vóóraf is weg.
it('keeps the tools to take a listing down after the fact', function () {
    $listing = Listing::factory()->published()->create();

    app(ListingStateService::class)->transition($listing, 'archived');

    expect($listing->fresh()->state)->toBe('archived');
});

// De knop stond op "Indienen voor moderatie". Blijft die tekst staan terwijl de
// wachtrij eraf is, dan belooft de knop iets anders dan er gebeurt.
it('does not promise a moderation queue on the submit button', function () {
    config()->set('cloudmarktplaats.features.moderation', false);

    $this->actingAs($this->user);
    $listing = Listing::factory()->for($this->user)->create([
        'category_id' => $this->category->id,
        'state' => 'draft',
        'description' => 'Een beschrijving met ruim voldoende lengte om te slagen.',
    ]);
    ListingPhoto::factory()->for($listing)->create();

    Livewire::test(Wizard::class, ['listing' => $listing])
        ->call('next')
        ->call('next')
        ->assertDontSee('Indienen voor moderatie')
        ->assertSee('Plaatsen');
});
