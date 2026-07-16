<?php

declare(strict_types=1);

use App\Models\HomelabPhoto;
use App\Models\HomelabPost;
use App\Models\User;

function homelabWithPhoto(array $attributes = []): HomelabPost
{
    $post = HomelabPost::factory()->create($attributes);
    HomelabPhoto::factory()->for($post, 'post')->create([
        'path' => 'homelabs/'.$post->ulid.'/0/card.webp',
        'position' => 0,
        'mime' => 'image/jpeg',
    ]);

    return $post;
}

it('renders a homelab page at its own url', function () {
    $post = homelabWithPhoto(['title' => 'Proxmox-cluster', 'body' => 'Drie nodes.']);

    $this->get("/homelabs/{$post->ulid}-{$post->slug}")
        ->assertOk()
        ->assertSee('Proxmox-cluster')
        ->assertSee('Drie nodes.');
});

it('never shows who built it', function () {
    $owner = User::factory()->create(['username' => 'marco', 'email' => 'marco@example.com']);
    $post = homelabWithPhoto(['user_id' => $owner->id, 'title' => 'Rack', 'body' => 'Mijn lab.']);

    $this->get("/homelabs/{$post->ulid}-{$post->slug}")
        ->assertOk()
        ->assertDontSee('marco')
        ->assertDontSee('marco@example.com');
});

it('shows the feedback prompt only when set', function () {
    $with = homelabWithPhoto(['body' => 'A', 'feedback_prompt' => 'Kan het zuiniger?']);
    $this->get("/homelabs/{$with->ulid}-{$with->slug}")->assertSee('Kan het zuiniger?');

    $without = homelabWithPhoto(['body' => 'B', 'feedback_prompt' => null]);
    $this->get("/homelabs/{$without->ulid}-{$without->slug}")->assertDontSee('feedback');
});

it('301-redirects a wrong slug to the canonical url', function () {
    $post = homelabWithPhoto(['title' => 'Cluster', 'body' => 'x']);

    $this->get("/homelabs/{$post->ulid}-verkeerd")
        ->assertRedirect("/homelabs/{$post->ulid}-{$post->slug}");
});

it('renders a titleless post using a body fragment as heading', function () {
    $post = homelabWithPhoto(['title' => null, 'body' => 'Een klein maar fijn rack in de meterkast.']);

    $this->get("/homelabs/{$post->ulid}-{$post->slug}")
        ->assertOk()
        ->assertSee('Een klein maar fijn rack');
});

it('404s a removed post', function () {
    $post = homelabWithPhoto(['status' => 'removed', 'body' => 'weg']);

    $this->get("/homelabs/{$post->ulid}-{$post->slug}")->assertNotFound();
});

it('sets og:image to the jpg original', function () {
    $post = homelabWithPhoto(['title' => 'Rack', 'body' => 'x']);

    $this->get("/homelabs/{$post->ulid}-{$post->slug}")
        ->assertOk()
        ->assertSee('original.jpg', escape: false);
});
