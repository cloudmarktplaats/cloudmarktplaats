<?php

declare(strict_types=1);

use App\Livewire\News\Reader;
use App\Models\FeedItem;
use App\Models\FeedSource;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('shows all active sources to a member who never chose', function () {
    $a = FeedSource::factory()->create();
    $b = FeedSource::factory()->create();
    FeedItem::factory()->for($a, 'source')->create(['title' => 'Van A']);
    FeedItem::factory()->for($b, 'source')->create(['title' => 'Van B']);

    Livewire::actingAs(User::factory()->create())
        ->test(Reader::class)
        ->assertSee('Van A')
        ->assertSee('Van B');
});

it('persists a source toggle and filters the stream', function () {
    $a = FeedSource::factory()->create(['name' => 'Bron A']);
    $b = FeedSource::factory()->create(['name' => 'Bron B']);
    FeedItem::factory()->for($a, 'source')->create(['title' => 'Van A']);
    FeedItem::factory()->for($b, 'source')->create(['title' => 'Van B']);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Reader::class)
        ->call('toggleSource', $b->id) // materialiseert [A,B] en haalt B eruit
        ->assertSee('Van A')
        ->assertDontSee('Van B');

    expect(DB::table('user_feed_sources')->where('user_id', $user->id)->pluck('feed_source_id')->all())
        ->toEqualCanonicalizing([$a->id]);
});

it('counts unread items past the per-source watermark and clears them on mark read', function () {
    $source = FeedSource::factory()->create();
    FeedItem::factory()->for($source, 'source')->create(['published_at' => now()->subHour()]);
    FeedItem::factory()->for($source, 'source')->create(['published_at' => now()->subMinutes(5)]);
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test(Reader::class);
    expect($component->viewData('unreadCounts')[$source->id])->toBe(2);

    $component->call('markRead', $source->id);
    expect($component->viewData('unreadCounts')[$source->id])->toBe(0);

    expect(DB::table('user_feed_reads')->where('user_id', $user->id)->where('feed_source_id', $source->id)->exists())
        ->toBeTrue();
});

// Follow-up review #3: een gekozen bron die daarna gedeactiveerd wordt, hoort
// niet meer in de stroom van het lid dat hem koos (anoniem zag hem al niet).
it('drops a chosen source from the stream once it is deactivated', function () {
    $a = FeedSource::factory()->create(['name' => 'Bron A']);
    $b = FeedSource::factory()->create(['name' => 'Bron B']);
    FeedItem::factory()->for($a, 'source')->create(['title' => 'Van A']);
    FeedItem::factory()->for($b, 'source')->create(['title' => 'Van B']);
    $user = User::factory()->create();
    $user->feedSources()->sync([$a->id, $b->id]);

    $b->update(['is_active' => false]);

    $component = Livewire::actingAs($user)->test(Reader::class)
        ->assertSee('Van A')
        ->assertDontSee('Van B');

    expect($component->viewData('selectedSourceIds'))->toEqualCanonicalizing([$a->id]);
});

// Follow-up review #4: het laatste vinkje kan niet uit, anders klapt de lege
// pivot ("geen keuze = alles") terug naar alle bronnen tonen.
it('keeps at least one source and never collapses back to all', function () {
    $a = FeedSource::factory()->create();
    $b = FeedSource::factory()->create();
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test(Reader::class)
        ->call('toggleSource', $b->id); // materialiseert [A,B], haalt B eruit -> [A]

    expect(DB::table('user_feed_sources')->where('user_id', $user->id)->pluck('feed_source_id')->all())
        ->toEqualCanonicalizing([$a->id]);

    $component->call('toggleSource', $a->id); // laatste uitvinken is een no-op

    expect(DB::table('user_feed_sources')->where('user_id', $user->id)->pluck('feed_source_id')->all())
        ->toEqualCanonicalizing([$a->id]);
});
