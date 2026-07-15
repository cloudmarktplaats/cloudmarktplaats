<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

#[Layout('layouts.app')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function submit(): RedirectResponse|Redirector|null
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $key = 'login:'.request()->ip().':'.strtolower($this->email);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Te veel pogingen. Probeer over een minuut opnieuw.']);
        }

        // Lowercase the lookup, not just the rate-limit key above: emails are
        // stored lowercase (see User::email()), and Postgres compares
        // case-sensitively — so "Bram@..." would find nobody and read to the
        // user as a wrong password.
        $user = User::where('email', Str::lower(trim($this->email)))->first();
        if ($user === null || ! Hash::check($this->password, $user->password_hash ?? '')) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Inloggegevens onjuist.']);
        }

        RateLimiter::clear($key);

        // Gate: 2FA challenge precedes the actual login. The primary
        // credential check has passed, but we don't seat the session
        // until the second factor is verified.
        if ($user->two_factor_confirmed_at !== null) {
            session(['pending_2fa_user_id' => $user->id]);

            return redirect('/2fa/challenge');
        }

        auth()->login($user, $this->remember);
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
        return view('livewire.auth.login');
    }
}
