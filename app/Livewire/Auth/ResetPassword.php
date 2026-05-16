<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ResetPassword extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token = '', string $email = ''): void
    {
        $this->token = $token;
        $this->email = $email;
    }

    public function submit(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:10', 'confirmed'],
        ]);

        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (User $user, string $password): void {
                $user->password_hash = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                $user->save();
                UserIdentity::firstOrCreate(
                    ['user_id' => $user->id, 'provider' => 'password'],
                    ['provider_uid' => (string) $user->id],
                );
                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $this->redirect('/login');

            return;
        }
        $this->addError('email', (string) __($status));
    }

    public function render(): View
    {
        return view('livewire.auth.reset-password');
    }
}
