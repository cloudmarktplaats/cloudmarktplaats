<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $roles = [
            'admin@example.local' => 'admin',
            'user@example.local' => 'user',
        ];

        foreach ($roles as $email => $role) {
            $local = str_replace('@example.local', '', $email);

            $u = User::firstOrCreate(
                ['email' => $email],
                [
                    'username' => $local,
                    'display_name' => ucfirst($local),
                    'role' => $role,
                    'password_hash' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );

            // For the `password` provider, provider_uid scopes per-user; we
            // use the user email so the (provider, provider_uid) unique
            // constraint never collides across users.
            UserIdentity::firstOrCreate(
                ['user_id' => $u->id, 'provider' => 'password'],
                ['provider_uid' => $email],
            );
        }
    }
}
