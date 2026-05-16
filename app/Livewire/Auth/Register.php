<?php

namespace App\Livewire\Auth;

use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Register extends Component
{
    public string $email = '';

    public string $username = '';

    public string $display_name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $accept_tos = false;

    public function submit(): void
    {
        $this->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9_-]+$/i', 'unique:users,username'],
            'display_name' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
            'accept_tos' => ['accepted'],
        ]);

        $user = DB::transaction(function (): User {
            $u = User::create([
                'email' => $this->email,
                'username' => strtolower($this->username),
                'display_name' => $this->display_name,
                'password_hash' => Hash::make($this->password),
            ]);
            UserIdentity::create([
                'user_id' => $u->id,
                'provider' => 'password',
                'provider_uid' => (string) $u->id,
            ]);
            foreach (['tos', 'privacy'] as $type) {
                $doc = LegalDocument::current($type, app()->getLocale());
                if ($doc) {
                    LegalAcceptance::create([
                        'user_id' => $u->id,
                        'legal_document_id' => $doc->id,
                        'accepted_at' => now(),
                        'ip_hash' => hash('sha256', request()->ip().config('app.key')),
                    ]);
                }
            }

            return $u;
        });

        event(new Registered($user));
        auth()->login($user);

        $this->redirect('/email/verify-notice');
    }

    public function render(): View
    {
        return view('livewire.auth.register');
    }
}
