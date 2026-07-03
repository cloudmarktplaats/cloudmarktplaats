<?php

declare(strict_types=1);

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\KarmaEvent;
use App\Models\User;
use Livewire\Livewire;

it('shows karma and inviter on the users table', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $inviter = User::factory()->create(['username' => 'thementor']);
    $invitee = User::factory()->create(['invited_by' => $inviter->id]);
    KarmaEvent::factory()->for($invitee)->create(['points' => 7]);

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->assertSee('thementor')
        ->assertSee('7');
});
