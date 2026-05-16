<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
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

        $key = 'pwreset:'.strtolower($this->email);
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['email' => 'Te veel verzoeken — wacht een uur.']);
        }
        RateLimiter::hit($key, 3600);

        Password::sendResetLink(['email' => $this->email]);
        $this->status = 'Als dit emailadres bestaat, is er een reset-link verstuurd.';
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password');
    }
}
