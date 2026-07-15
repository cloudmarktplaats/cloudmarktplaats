<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ForgotPassword extends Component
{
    public string $email = '';

    public string $status = '';

    public function submit(): void
    {
        $this->validate(['email' => ['required', 'email']]);

        // Emails are stored lowercase (see User::email()) and Postgres compares
        // case-sensitively, so a request for "B.vaneijk@..." against a stored
        // "b.vaneijk@..." resolves to INVALID_USER: no token, no mail, and the
        // reassuring status below regardless. That is exactly how this was
        // reported — as "reset mails komen niet aan".
        $email = Str::lower(trim($this->email));

        $key = 'pwreset:'.$email;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['email' => 'Te veel verzoeken — wacht een uur.']);
        }
        RateLimiter::hit($key, 3600);

        Password::sendResetLink(['email' => $email]);
        $this->status = 'Als dit emailadres bestaat, is er een reset-link verstuurd.';
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password');
    }
}
