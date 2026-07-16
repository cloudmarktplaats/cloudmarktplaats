<?php

declare(strict_types=1);

use App\Livewire\Homelab\Feed;
use App\Models\HomelabPhoto;
use App\Models\HomelabPost;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

it('shows the feed to guests but not the post form', function () {
    HomelabPost::factory()->withPhoto()->create(['body' => 'Mijn rack met drie NUCs']);

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
        ->set('photos', [$upload])
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
        ->assertHasErrors(['photos', 'body']);
});

it('rate limits to one post per 24h per account', function () {
    $user = User::factory()->create();
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));

    Livewire::actingAs($user)
        ->test(Feed::class)
        ->set('photos', [UploadedFile::fake()->createWithContent('a.jpg', $bytes)])
        ->set('body', 'eerste post')
        ->call('submit')
        ->assertHasNoErrors();

    Livewire::actingAs($user)
        ->test(Feed::class)
        ->set('photos', [UploadedFile::fake()->createWithContent('b.jpg', $bytes)])
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

it('accepts a title, a feedback prompt and multiple photos', function () {
    $user = User::factory()->create();
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));
    $a = UploadedFile::fake()->createWithContent('a.jpg', $bytes);
    $b = UploadedFile::fake()->createWithContent('b.jpg', $bytes);

    Livewire::actingAs($user)
        ->test(Feed::class)
        ->set('title', 'Proxmox-cluster op drie EliteDesks')
        ->set('feedbackPrompt', 'Idle-verbruik is 38W. Kan dat lager?')
        ->set('body', 'Drie nodes, Ceph, TrueNAS in een VM. Draait al maanden stabiel.')
        ->set('photos', [$a, $b])
        ->call('submit')
        ->assertHasNoErrors();

    $post = HomelabPost::query()->firstOrFail();
    expect($post->title)->toBe('Proxmox-cluster op drie EliteDesks')
        ->and($post->feedback_prompt)->toBe('Idle-verbruik is 38W. Kan dat lager?')
        ->and($post->photos()->count())->toBe(2);
});

it('rejects more than the homelab photo maximum', function () {
    $user = User::factory()->create();
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));
    $five = collect(range(1, 5))
        ->map(fn (int $i) => UploadedFile::fake()->createWithContent("p{$i}.jpg", $bytes))
        ->all();

    Livewire::actingAs($user)
        ->test(Feed::class)
        ->set('body', 'Vijf foto’s, één te veel.')
        ->set('photos', $five)
        ->call('submit')
        ->assertHasErrors(['photos']);

    expect(HomelabPost::query()->count())->toBe(0);
});

it('still posts without a title', function () {
    $user = User::factory()->create();
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));
    $photo = UploadedFile::fake()->createWithContent('lab.jpg', $bytes);

    Livewire::actingAs($user)
        ->test(Feed::class)
        ->set('body', 'Geen titel, wel een rack.')
        ->set('photos', [$photo])
        ->call('submit')
        ->assertHasNoErrors();

    expect(HomelabPost::query()->firstOrFail()->title)->toBeNull();
});

it('links each feed card to its own page', function () {
    $post = HomelabPost::factory()->create(['title' => 'Rack', 'body' => 'x']);
    HomelabPhoto::factory()->for($post, 'post')->create([
        'path' => 'homelabs/'.$post->ulid.'/0/card.webp',
        'position' => 0,
    ]);

    Livewire::test(Feed::class)
        ->assertSee("/homelabs/{$post->ulid}-{$post->slug}", escape: false);
});
