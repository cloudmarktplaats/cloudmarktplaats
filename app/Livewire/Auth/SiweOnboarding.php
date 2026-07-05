<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\FoundingCohort;
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
            'accept_tos' => ['accepted'],
        ]);

        $cohort = app(FoundingCohort::class);
        if (! $cohort->isRegistrationOpen()) {
            $this->addError('email', __('De beta zit vol. Registreren kan weer zodra er een plek vrijkomt — zet je op de wachtlijst op de registratiepagina.'));

            return;
        }
        $foundingMember = $cohort->hasFoundingSpot();

        $user = DB::transaction(function () use ($foundingMember): User {
            $u = User::create([
                'email' => $this->email,
                'username' => strtolower($this->username),
                // Display name defaults to the chosen username; editable later
                // in profile settings.
                'display_name' => $this->username,
                'password_hash' => null,
                'invite_credits' => (bool) config('cloudmarktplaats.features.invites')
                    ? (int) config('cloudmarktplaats.gamification.starting_invite_credits')
                    : 0,
                'is_founding_member' => $foundingMember,
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
