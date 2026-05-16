<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * SIWE onboarding form for a brand-new wallet user.
 *
 * Reached from the wallet adapter after `/auth/web3/verify` returns
 * `onboarding_required: true` for an unknown address. The component
 * collects the username, display name, and (optional) email needed to
 * create a User row plus the matching `siwe` UserIdentity. Legal
 * acceptance is recorded here — it is intentionally NOT written by the
 * verify endpoint to keep that endpoint a pure signature check.
 *
 * Password is left null: this user authenticates exclusively via the
 * signed nonce flow until they later attach a password identity from
 * /profile/security.
 */
#[Layout('layouts.app')]
class SiweOnboarding extends Component
{
    public string $address = '';

    public string $email = '';

    public string $username = '';

    public string $display_name = '';

    public bool $accept_tos = false;

    public function mount(string $address): void
    {
        $this->address = strtolower($address);
    }

    public function submit(): void
    {
        $this->validate([
            'address' => ['required', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'email' => ['required', 'email', 'unique:users,email'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9_-]+$/i', 'unique:users,username'],
            'display_name' => ['required', 'string', 'max:64'],
            'accept_tos' => ['accepted'],
        ]);

        $user = DB::transaction(function (): User {
            $u = User::create([
                'email' => $this->email,
                'username' => strtolower($this->username),
                'display_name' => $this->display_name,
                'password_hash' => null,
            ]);

            UserIdentity::create([
                'user_id' => $u->id,
                'provider' => 'siwe',
                'provider_uid' => strtolower($this->address),
                'last_used_at' => now(),
            ]);

            foreach (['tos', 'privacy'] as $type) {
                $doc = LegalDocument::current($type, app()->getLocale());
                if ($doc !== null) {
                    LegalAcceptance::create([
                        'user_id' => $u->id,
                        'legal_document_id' => $doc->id,
                        'accepted_at' => now(),
                        'ip_hash' => hash('sha256', (string) (request()->ip() ?? '').config('app.key')),
                    ]);
                }
            }

            return $u;
        });

        auth()->login($user);
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->save();

        $this->redirect('/');
    }

    public function render(): View
    {
        return view('livewire.auth.siwe-onboarding');
    }
}
