<?php

declare(strict_types=1);

use App\Livewire\Listings\Browse;
use App\Livewire\Listings\Detail;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;

it('Browse shows only published listings', function () {
    Listing::factory()->create(['state' => 'draft', 'title' => 'Hidden draft']);
    $published = Listing::factory()->published()->create(['title' => 'Visible listing']);

    Livewire::test(Browse::class)
        ->assertSee('Visible listing')
        ->assertDontSee('Hidden draft');
    expect($published->state)->toBe('published');
});

it('Browse filters by category ltree path', function () {
    $parent = Category::factory()->create(['path' => 'computers', 'name' => 'Computers']);
    $child = Category::factory()->create(['path' => 'computers.laptops', 'name' => 'Laptops']);
    $other = Category::factory()->create(['path' => 'audio', 'name' => 'Audio']);

    $inScope = Listing::factory()->published()->create([
        'category_id' => $child->id, 'title' => 'IBM ThinkPad',
    ]);
    Listing::factory()->published()->create([
        'category_id' => $other->id, 'title' => 'Speaker Set',
    ]);

    Livewire::test(Browse::class, ['categoryPath' => 'computers'])
        ->assertSee('IBM ThinkPad')
        ->assertDontSee('Speaker Set');
    expect($parent->path)->toBe('computers');
    expect($inScope->category_id)->toBe($child->id);
});

it('Detail renders a published listing for anonymous visitors', function () {
    $listing = Listing::factory()->published()->create([
        'title' => 'Sun SPARCstation 20',
        'description' => 'Vintage workstation.',
    ]);

    Livewire::test(Detail::class, ['ulid' => (string) $listing->ulid, 'slug' => (string) $listing->slug])
        ->assertSee('Sun SPARCstation 20')
        ->assertSee('Vintage workstation');
});

it('Detail 404s for non-published listings for anonymous visitors', function () {
    $listing = Listing::factory()->create(['state' => 'draft']);

    $this->get("/listings/{$listing->ulid}-{$listing->slug}")
        ->assertNotFound();
});

it('Detail lets the owner preview their own pending listing', function () {
    $owner = User::factory()->create();
    $listing = Listing::factory()->for($owner)->create([
        'state' => 'pending_review',
        'title' => 'Wachtende advertentie',
    ]);

    Livewire::actingAs($owner)
        ->test(Detail::class, ['ulid' => (string) $listing->ulid, 'slug' => (string) $listing->slug])
        ->assertOk()
        ->assertSee('Wachtende advertentie')
        ->assertSee('In moderatie');
});

it('Detail 404s a non-owner viewing someone elses pending listing', function () {
    $listing = Listing::factory()->create(['state' => 'pending_review']);
    $stranger = User::factory()->create();

    Livewire::actingAs($stranger)
        ->test(Detail::class, ['ulid' => (string) $listing->ulid, 'slug' => (string) $listing->slug])
        ->assertNotFound();
});

it('Detail lets a moderator preview any pending listing', function () {
    $listing = Listing::factory()->create([
        'state' => 'pending_review',
        'title' => 'Moderatie-preview',
    ]);
    $moderator = User::factory()->moderator()->create();

    Livewire::actingAs($moderator)
        ->test(Detail::class, ['ulid' => (string) $listing->ulid, 'slug' => (string) $listing->slug])
        ->assertOk()
        ->assertSee('Moderatie-preview');
});

it('homepage serves the marketing page for guests', function () {
    $this->get('/')->assertStatus(200);
});

it('homepage redirects authenticated users to /listings', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect('/listings');
});

it('Detail offers the contact relay to anonymous visitors — no login wall', function () {
    $listing = Listing::factory()->published()->create();

    Livewire::test(Detail::class, ['ulid' => (string) $listing->ulid, 'slug' => (string) $listing->slug])
        ->assertSee('Stuur bericht')
        ->assertSeeLivewire('contact-seller');
});

it('Detail 301-redirects to the canonical slug if the URL slug differs', function () {
    $listing = Listing::factory()->published()->create(['slug' => 'apple-imac-g3']);

    $this->get("/listings/{$listing->ulid}-something-fake")
        ->assertStatus(301)
        ->assertRedirect("/listings/{$listing->ulid}-apple-imac-g3");
});
