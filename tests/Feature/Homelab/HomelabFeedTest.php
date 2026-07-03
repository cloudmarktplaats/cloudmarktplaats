<?php

declare(strict_types=1);

use App\Livewire\Homelab\Feed;
use App\Models\HomelabPost;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

it('shows the feed to guests but not the post form', function () {
    HomelabPost::factory()->create(['body' => 'Mijn rack met drie NUCs']);

    $this->get('/homelabs')
        ->assertOk()
        ->assertSee('Mijn rack met drie NUCs')
        ->assertSee('Log in om jouw lab te tonen');
});

it('hides removed posts', function () {
    HomelabPost::factory()->removed()->create(['body' => 'weggemodereerde post']);

    $this->get('/homelabs')->assertOk()->assertDontSee('weggemodereerde post');
});

it('lets a logged-in user post a photo with body', function () {
    $user = User::factory()->create();
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));
    $upload = UploadedFile::fake()->createWithContent('lab.jpg', $bytes);

    Livewire::actingAs($user)
        ->test(Feed::class)
        ->set('photo', $upload)
        ->set('body', 'R730 + Unifi-switch, alles op 10G')
        ->call('submit')
        ->assertHasNoErrors();

    expect(HomelabPost::query()->count())->toBe(1)
        ->and(HomelabPost::query()->first()->user_id)->toBe($user->id);
});

it('validates photo required and body length', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Feed::class)
        ->set('body', str_repeat('a', 501))
        ->call('submit')
        ->assertHasErrors(['photo', 'body']);
});

it('rate limits to one post per 24h per account', function () {
    $user = User::factory()->create();
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));

    Livewire::actingAs($user)
        ->test(Feed::class)
        ->set('photo', UploadedFile::fake()->createWithContent('a.jpg', $bytes))
        ->set('body', 'eerste post')
        ->call('submit')
        ->assertHasNoErrors();

    Livewire::actingAs($user)
        ->test(Feed::class)
        ->set('photo', UploadedFile::fake()->createWithContent('b.jpg', $bytes))
        ->set('body', 'tweede post te snel')
        ->call('submit')
        ->assertHasErrors(['body']);

    expect(HomelabPost::query()->count())->toBe(1);

    // Pin the decay duration itself (24h), not just "blocked right now" —
    // that would also pass with a much shorter decay. `travel()` can't be
    // used here: it desyncs Carbon's test-time from the real filesystem
    // mtimes Livewire's fake-upload cleanup relies on, and a follow-up
    // upload after travelling forward gets silently emptied out.
    expect(RateLimiter::availableIn("homelab-post:user:{$user->id}"))->toBeGreaterThan(23 * 3600);
});

it('404s when the feature flag is off', function () {
    config()->set('cloudmarktplaats.features.homelab_feed', false);

    $this->get('/homelabs')->assertNotFound();
});
