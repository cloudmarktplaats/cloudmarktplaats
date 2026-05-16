<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use PragmaRX\Google2FA\Google2FA;

/**
 * Component for the Profile → 2FA pages.
 *
 * Drives three flows:
 *  - start() / confirm()       : enable 2FA (generates secret + 8 recovery codes)
 *  - disable()                 : turn 2FA off (TOTP + password if user has a password identity)
 *  - regenerate()              : rotate recovery codes (requires fresh TOTP)
 *
 * Recovery codes are presented exactly once after generation and stored as an
 * encrypted JSON array on the User model. The TOTP secret uses Laravel's
 * `encrypted` cast — Google2FA helpers receive the plain value automatically.
 */
#[Layout('layouts.app')]
class TwoFactorSetup extends Component
{
    public string $code = '';

    public string $password = '';

    public ?string $secret = null;

    /** @var array<int,string> */
    public array $recovery = [];

    public bool $confirmed = false;

    public function start(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $key = '2fa:enable:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages([
                'code' => 'Te veel pogingen om 2FA in te schakelen. Probeer over een minuut opnieuw.',
            ]);
        }
        RateLimiter::hit($key, 60);

        $g = new Google2FA;
        $this->secret = $g->generateSecretKey();
        // The `encrypted` and `datetime` casts make these assignments safe
        // even though Larastan only sees the underlying string columns.
        $user->forceFill([
            'two_factor_secret' => $this->secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->recovery = collect()->times(8, fn () => Str::random(10))->all();
        $this->confirmed = false;
        $this->code = '';
    }

    public function confirm(): void
    {
        $this->validate(['code' => ['required', 'digits:6']]);

        /** @var User $user */
        $user = auth()->user();
        $secret = $user->two_factor_secret;
        if (! is_string($secret) || $secret === '') {
            $this->addError('code', 'Start eerst de 2FA-setup.');

            return;
        }

        if (! (new Google2FA)->verifyKey($secret, $this->code)) {
            $this->addError('code', 'TOTP klopt niet.');

            return;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => $this->recovery,
            'two_factor_confirmed_at' => now(),
        ])->save();
        $this->confirmed = true;
        $this->code = '';
    }

    public function disable(): void
    {
        /** @var User $user */
        $user = auth()->user();
        if ($user->two_factor_confirmed_at === null) {
            $this->addError('code', '2FA staat niet aan.');

            return;
        }

        $this->validate(['code' => ['required', 'string']]);

        $secret = $user->two_factor_secret;
        if (! is_string($secret) || ! (new Google2FA)->verifyKey($secret, $this->code)) {
            $this->addError('code', 'TOTP klopt niet.');

            return;
        }

        // If the user has a password identity, require fresh password proof.
        $hasPasswordIdentity = $user->identities()
            ->where('provider', 'password')
            ->exists();
        if ($hasPasswordIdentity) {
            $this->validate(['password' => ['required', 'string']]);
            if (! Hash::check($this->password, $user->password_hash ?? '')) {
                $this->addError('password', 'Wachtwoord klopt niet.');

                return;
            }
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->secret = null;
        $this->recovery = [];
        $this->confirmed = false;
        $this->code = '';
        $this->password = '';
    }

    public function regenerate(): void
    {
        /** @var User $user */
        $user = auth()->user();
        if ($user->two_factor_confirmed_at === null) {
            $this->addError('code', '2FA staat niet aan.');

            return;
        }

        $this->validate(['code' => ['required', 'digits:6']]);

        $secret = $user->two_factor_secret;
        if (! is_string($secret) || ! (new Google2FA)->verifyKey($secret, $this->code)) {
            $this->addError('code', 'TOTP klopt niet.');

            return;
        }

        $this->recovery = collect()->times(8, fn () => Str::random(10))->all();
        $user->forceFill(['two_factor_recovery_codes' => $this->recovery])->save();
        $this->confirmed = true;
        $this->code = '';
    }

    public function qrUri(): string
    {
        /** @var User $user */
        $user = auth()->user();
        $secret = $this->secret ?? $user->two_factor_secret;

        if (! is_string($secret) || $secret === '') {
            return '';
        }

        return (new Google2FA)->getQRCodeUrl(
            'cloudmarktplaats.nl',
            (string) $user->username,
            $secret,
        );
    }

    public function qrSvg(): string
    {
        $uri = $this->qrUri();
        if ($uri === '') {
            return '';
        }

        $renderer = new ImageRenderer(
            new RendererStyle(220),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($uri);
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('livewire.profile.two-factor-setup', [
            'enabled' => $user->two_factor_confirmed_at !== null,
        ]);
    }
}
