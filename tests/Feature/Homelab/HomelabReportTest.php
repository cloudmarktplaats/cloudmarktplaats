<?php

declare(strict_types=1);

use App\Models\HomelabPost;
use App\Models\Report;
use App\Models\User;

it('lets a user report a homelab post', function () {
    $post = HomelabPost::factory()->create();
    $reporter = User::factory()->create();

    $this->actingAs($reporter)
        ->post("/reports/homelab/{$post->ulid}", ['reason' => 'spam'])
        ->assertRedirect();

    expect(Report::query()->where('reportable_type', $post->getMorphClass())->count())->toBe(1);
});

it('dedupes an open report from the same reporter', function () {
    $post = HomelabPost::factory()->create();
    $reporter = User::factory()->create();

    $this->actingAs($reporter)->post("/reports/homelab/{$post->ulid}", ['reason' => 'spam']);
    $this->actingAs($reporter)->post("/reports/homelab/{$post->ulid}", ['reason' => 'spam']);

    expect(Report::query()->count())->toBe(1);
});

it('requires login to report', function () {
    $post = HomelabPost::factory()->create();

    $this->post("/reports/homelab/{$post->ulid}", ['reason' => 'spam'])
        ->assertRedirect('/login');
});
