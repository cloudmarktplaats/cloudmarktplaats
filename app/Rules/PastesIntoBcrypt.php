<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Bcrypt kijkt niet verder dan 72 bytes. Alles daarna wordt stil weggegooid:
 * twee wachtwoorden die de eerste 72 bytes delen openen hetzelfde account.
 *
 * Als aanval stelt dat weinig voor, want wie de eerste 72 bytes al weet is
 * binnen. Het probleem is eerlijkheid. Iemand die een zin van 100 tekens
 * kiest denkt dat hij iets sterkers heeft dan bij 72, en dat is niet zo. Een
 * veld dat invoer stil negeert liegt tegen de gebruiker.
 *
 * Bytes en niet tekens: `max:72` van Laravel telt tekens, en een zin met
 * accenten of emoji zit op 72 tekens allang boven de 72 bytes. Dan zou de
 * regel precies de gevallen missen waarvoor hij bestaat.
 *
 * De andere uitweg was argon2id, dat geen grens kent. Dat vraagt om elke
 * bestaande bcrypt-hash bij de volgende login om te zetten, en dat is een
 * migratie en geen validatieregel.
 */
class PastesIntoBcrypt implements ValidationRule
{
    private const MAX_BYTES = 72;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) > self::MAX_BYTES) {
            $fail(__('Een wachtwoord telt tot 72 bytes mee; alles daarna wordt genegeerd. Kies er een die korter is, dan weet je zeker dat het hele wachtwoord telt.'));
        }
    }
}
