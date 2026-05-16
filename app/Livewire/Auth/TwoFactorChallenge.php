<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;
use PragmaRX\Google2FA\Google2FA;

/**
 * 2FA login challenge.
 *
 * Reached when primary authentication (password, OAuth, or SIWE) succeeded
 * for an account that has `two_factor_confirmed_at` set. The primary flow
 * stores `pending_2fa_user_id` in the session and redirects here instead
 * of calling `auth()->login(...)`. We accept either:
 *   - a 6-digit numeric TOTP code, verified via Google2FA, OR
 *   - one of the user's recovery codes, which is consumed on success.
 *
 * On success the pending flag is cleared, the user is logged in, the
 * session is regenerated, and `last_login_at` / `last_login_ip` updated.
 */
#[Layout('layouts.app')]
class TwoFactorChallenge extends Component
{
    public string $code = '';

    public function mount(): void
    {
        if (session('pending_2fa_user_id') === null) {
            abort(401);
        }
    }

    public function submit(): RedirectResponse|Redirector|null
    {
        $userId = session('pending_2fa_user_id');
        if (! is_int($userId) && ! (is_string($userId) && ctype_digit($userId))) {
            abort(401);
        }

        $key = '2fa:challenge:'.$userId;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Te veel pogingen. Probeer over een minuut opnieuw.',
            ]);
        }
        RateLimiter::hit($key, 60);

        $user = User::findOrFail($userId);
        $secret = $user->two_factor_secret;

        // 6 digits → TOTP, anything else → recovery code.
        if (strlen($this->code) === 6 && ctype_digit($this->code)) {
            if (is_string($secret) && (new Google2FA)->verifyKey($secret, $this->code)) {
                return $this->complete($user);
            }
        } else {
            /** @var array<int,string> $codes */
            $codes = $user->two_factor_recovery_codes ?? [];
            if (in_array($this->code, $codes, true)) {
                $remaining = array_values(array_diff($codes, [$this->code]));
                $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();

                return $this->complete($user);
            }
        }

        $this->addError('code', 'Ongeldige code.');

        return null;
    }

    private function complete(User $user): RedirectResponse|Redirector
    {
        session()->forget('pending_2fa_user_id');
        RateLimiter::clear('2fa:challenge:'.$user->id);

        auth()->login($user);
        if (request()->hasSession()) {
            request()->session()->regenerate();
        }
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->save();

        return redirect()->intended('/');
    }

    public function render(): View
    {
        return view('livewire.auth.two-factor-challenge');
    }
}
