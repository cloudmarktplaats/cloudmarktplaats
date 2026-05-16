<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function submit(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $key = 'login:'.request()->ip().':'.strtolower($this->email);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Te veel pogingen. Probeer over een minuut opnieuw.']);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Inloggegevens onjuist.']);
        }

        RateLimiter::clear($key);
        if (request()->hasSession()) {
            request()->session()->regenerate();
        }
        Auth::user()?->update(['last_login_at' => now(), 'last_login_ip' => request()->ip()]);
        $this->redirectIntended('/');
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
