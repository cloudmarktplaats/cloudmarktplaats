<?php

namespace App\Livewire\Auth;

use App\Exceptions\InviteException;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserIdentity;
use App\Models\WaitlistEntry;
use App\Services\FoundingCohort;
use App\Services\Gamification\InviteService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Register extends Component
{
    public string $email = '';

    public string $username = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $accept_tos = false;

    public string $invite_code = '';

    public string $waitlist_email = '';

    public bool $waitlisted = false;

    public function mount(): void
    {
        $code = request()->query('invite');
        if (is_string($code)) {
            $this->invite_code = $code;
        }
    }

    /** Founding cohort full → capture an email for the waitlist instead. */
    public function joinWaitlist(): void
    {
        $this->validate([
            'waitlist_email' => ['required', 'email', 'unique:waitlist_entries,email'],
        ]);

        WaitlistEntry::query()->create(['email' => strtolower(trim($this->waitlist_email))]);
        $this->waitlisted = true;
    }

    public function submit(): void
    {
        // Hard gate: once the founding cohort is full and the waitlist is on,
        // no new accounts are created — the form shows the waitlist instead.
        abort_unless(app(FoundingCohort::class)->isRegistrationOpen(), 403);

        // Normalise before validating, not after: `unique:users,email` compares
        // case-sensitively on Postgres, so "TAKEN@..." would pass validation
        // against a stored "taken@..." and only blow up on the insert — the
        // User::email() mutator lowercases it into a duplicate key. Validating
        // the normalised value turns that 500 into the field error it should be.
        $this->email = Str::lower(trim($this->email));

        $this->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9_-]+$/i', 'unique:users,username'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
            'accept_tos' => ['accepted'],
        ]);

        $invitesOn = (bool) config('cloudmarktplaats.features.invites');
        $startingCredits = $invitesOn ? (int) config('cloudmarktplaats.gamification.starting_invite_credits') : 0;
        $code = trim($this->invite_code);

        $foundingMember = app(FoundingCohort::class)->hasFoundingSpot();

        try {
            $user = DB::transaction(function () use ($startingCredits, $invitesOn, $code, $foundingMember): User {
                $u = User::create([
                    'email' => $this->email,
                    'username' => strtolower($this->username),
                    // Display name defaults to the chosen username; it can be
                    // changed later in profile settings. One name field at
                    // signup keeps registration friction low.
                    'display_name' => $this->username,
                    'password_hash' => Hash::make($this->password),
                    'invite_credits' => $startingCredits,
                    'is_founding_member' => $foundingMember,
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
                if ($invitesOn && $code !== '') {
                    app(InviteService::class)->redeem($code, $u);
                }

                return $u;
            });
        } catch (InviteException $e) {
            $this->addError('invite_code', $e->getMessage());

            return;
        }

        event(new Registered($user));
        auth()->login($user);

        $this->redirect('/email/verify-notice');
    }

    public function render(): View
    {
        $cohort = app(FoundingCohort::class);

        return view('livewire.auth.register', [
            'registrationOpen' => $cohort->isRegistrationOpen(),
            'spotsLeft' => $cohort->spotsLeft(),
        ]);
    }
}
