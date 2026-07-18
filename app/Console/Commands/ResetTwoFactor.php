<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Middleware\EnforceStaffTwoFactor;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Noodluik: wis de 2FA van één gebruiker op e-mailadres.
 *
 * Bestaansrecht is de laatste-admin-lockout: verliest de enige admin zijn
 * authenticator én zijn recovery-codes, dan is de Filament-UserResource
 * (waar admins elkaars 2FA resetten) onbereikbaar. Dit commando is dan de
 * enige weg terug, zonder handmatig DB-werk.
 *
 * De gereset gebruiker wordt bij de volgende paneltoegang door
 * {@see EnforceStaffTwoFactor} weer naar de instelpagina
 * gestuurd, dus 2FA blijft verplicht — alleen opnieuw ingeschreven.
 */
class ResetTwoFactor extends Command
{
    protected $signature = 'user:reset-2fa {email : Het e-mailadres van de gebruiker}';

    protected $description = 'Wis de 2FA van een gebruiker (noodluik tegen een uitgesloten admin).';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($user === null) {
            $this->error("Geen gebruiker gevonden voor {$email}.");

            return self::FAILURE;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->info("2FA gewist voor {$user->email}; de gebruiker moet opnieuw inschrijven.");

        return self::SUCCESS;
    }
}
