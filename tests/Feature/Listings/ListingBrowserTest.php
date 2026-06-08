<?php

declare(strict_types=1);

use App\Livewire\Listings\Browse;
use App\Models\Listing;
use Livewire\Livewire;

it('orders newest-first by default', function () {
    Listing::factory()->published()->create([
        'title' => 'Oude advertentie', 'published_at' => now()->subDays(3),
    ]);
    Listing::factory()->published()->create([
        'title' => 'Verse advertentie', 'published_at' => now()->subMinute(),
    ]);

    Livewire::test(Browse::class)
        ->assertSeeInOrder(['Verse advertentie', 'Oude advertentie']);
});

it('shows only one page worth of listings until loadMore is called', function () {
    // 12 per page; the 13th must be hidden until we ask for more.
    Listing::factory()->count(12)->published()->sequence(
        fn ($s) => ['title' => 'Zichtbaar '.$s->index, 'published_at' => now()->subMinutes($s->index)],
    )->create();
    Listing::factory()->published()->create([
        'title' => 'Verborgen dertiende', 'published_at' => now()->subDays(1),
    ]);

    Livewire::test(Browse::class)
        ->assertDontSee('Verborgen dertiende')
        ->call('loadMore')
        ->assertSee('Verborgen dertiende');
});

it('seeds a stable random order when switching to the surprise-me sort', function () {
    Listing::factory()->published()->create(['title' => 'Willekeurig item']);

    Livewire::test(Browse::class)
        ->assertSet('seed', null)
        ->call('setSort', 'shuffle')
        ->assertSet('sort', 'shuffle')
        ->assertNotSet('seed', null)
        ->assertSee('Willekeurig item');
});

it('renders the snuffel empty state when nothing is published', function () {
    Livewire::test(Browse::class)
        ->assertSee('Nog niks te snuffelen');
});

it('renders colour-coded condition badges in Dutch', function () {
    Listing::factory()->published()->create(['title' => 'Nieuw spul', 'condition' => 'new']);
    Listing::factory()->published()->create(['title' => 'Sloop spul', 'condition' => 'for_parts']);

    Livewire::test(Browse::class)
        ->assertSee('Nieuw')
        ->assertSee('Voor onderdelen');
});
