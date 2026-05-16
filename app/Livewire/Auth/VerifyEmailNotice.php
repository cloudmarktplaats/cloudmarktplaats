<?php

namespace App\Livewire\Auth;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class VerifyEmailNotice extends Component
{
    public string $sent = '';

    public function resend(): void
    {
        auth()->user()?->sendEmailVerificationNotification();
        $this->sent = 'Email verstuurd.';
    }

    public function render(): View
    {
        return view('livewire.auth.verify-email-notice');
    }
}
