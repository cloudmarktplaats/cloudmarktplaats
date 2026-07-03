<?php

declare(strict_types=1);

use App\Filament\Resources\HomelabPostResource\Pages\ListHomelabPosts;
use App\Models\AdminAction;
use App\Models\HomelabPost;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('lists posts with the poster visible to admins', function () {
    $poster = User::factory()->create(['username' => 'rackmaster9000']);
    HomelabPost::factory()->for($poster)->create();

    Livewire::test(ListHomelabPosts::class)
        ->assertOk()
        ->assertSee('rackmaster9000');
});

it('removes a post and writes an audit row', function () {
    $post = HomelabPost::factory()->create();

    Livewire::test(ListHomelabPosts::class)
        ->callTableAction('remove', $post);

    expect($post->refresh()->status)->toBe('removed')
        ->and(AdminAction::query()->where('action', 'homelab_post.remove')->count())->toBe(1);
});
