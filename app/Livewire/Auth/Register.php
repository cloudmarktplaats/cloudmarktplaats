<?php

namespace App\Livewire\Auth;

use App\Exceptions\InviteException;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserIdentity;
use App\Models\WaitlistEntry;
use App\Rules\PastesIntoBcrypt;
use App\Services\FoundingCohort;
use App\Services\Gamification\InviteService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    /** Honeypot. Moet leeg blijven; een mens ziet dit veld nooit. */
    public string $website = '';

    /**
     * Hoeveel accounts 1 adres per uur mag aanmaken. Ruim genoeg voor een
     * kantoor of een CGNAT-provider achter 1 uitgaand IP, krap genoeg om
     * scripted aanmaak te stoppen voordat die de verzendlimiet opeet.
     */
    private const MAX_PER_HOUR = 5;

    private function throttleKey(): string
    {
        return 'register:'.request()->ip();
    }

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
            'waitlist_email' => ['required', 'email:strict', 'unique:waitlist_entries,email'],
        ]);

        WaitlistEntry::query()->create(['email' => strtolower(trim($this->waitlist_email))]);
        $this->waitlisted = true;
    }

    public function submit(): void
    {
        // Gate: once the founding cohort is full and the waitlist is on, no new
        // accounts are created — EXCEPT for someone holding a valid invite. An
        // invite is a member vouching for you, which is exactly what should
        // open a closed door; without that exception the whole invite system
        // dies the moment the cap is hit (on 2026-07-15: 100/100, 305 unused
        // credits, 2 codes handed out, 0 ever redeemed).
        //
        // The badge stays capped at the first 100 (hasFoundingSpot below) — the
        // door opens, the badge does not, so "de eerste 100" stays true.
        abort_unless(
            app(FoundingCohort::class)->isRegistrationOpen() || $this->hasUsableInvite(),
            403
        );

        // Een bot vult elk veld dat hij tegenkomt. Stil afhandelen, net als bij
        // ContactSeller en Mail\Subscribe: een foutmelding zou de val verklappen.
        // Geen tijdklem hier, anders dan bij Subscribe — de auth-velden dragen
        // sinds 01-09 autocomplete-tokens, dus een wachtwoordmanager vult dit
        // formulier sneller dan een mens ooit typt.
        if ($this->website !== '') {
            return;
        }

        // Normalise before validating, not after: `unique:users,email` compares
        // case-sensitively on Postgres, so "TAKEN@..." would pass validation
        // against a stored "taken@..." and only blow up on the insert — the
        // User::email() mutator lowercases it into a duplicate key. Validating
        // the normalised value turns that 500 into the field error it should be.
        $this->email = Str::lower(trim($this->email));

        $this->validate([
            'email' => ['required', 'email:strict', 'unique:users,email'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9_-]+$/i', 'unique:users,username'],
            'password' => ['required', 'string', 'min:10', 'confirmed', new PastesIntoBcrypt],
            'accept_tos' => ['accepted'],
        ]);

        // Ná de validatie, zodat een typefout geen poging kost en iemand zich
        // niet met een te kort wachtwoord buitensluit. Alleen een account dat
        // er echt komt telt mee, want dat is wat een verificatiemail kost.
        if (RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_PER_HOUR)) {
            throw ValidationException::withMessages([
                'email' => __('Er zijn vanaf dit adres net al accounts aangemaakt. Probeer het over een uur opnieuw, of vraag een lid om een uitnodiging.'),
            ]);
        }

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

        // Pas hier, want pas nu is er een account en gaat er een verificatiemail
        // uit. Een mislukte poging kost niets.
        RateLimiter::hit($this->throttleKey(), 3600);

        event(new Registered($user));
        auth()->login($user);

        $this->redirect('/email/verify-notice');
    }

    /**
     * Does this visitor hold an invite that could get them in?
     *
     * A hint, not a promise: InviteService::redeem() is the authority and runs
     * inside a transaction with a lock, so it still refuses a code that was
     * claimed a second ago. This only decides whether we show the form or the
     * waitlist — and letting an invited person reach a form that then rejects
     * their code is far better than showing them a waitlist they don't need.
     */
    private function hasUsableInvite(): bool
    {
        if (! (bool) config('cloudmarktplaats.features.invites')) {
            return false;
        }

        return app(InviteService::class)->isRedeemable($this->invite_code);
    }

    public function render(): View
    {
        $cohort = app(FoundingCohort::class);

        return view('livewire.auth.register', [
            // With the cohort full, an invited visitor must still see the form:
            // mount() picks the code out of /register?invite=CODE, so arriving
            // through an invite link is enough.
            'registrationOpen' => $cohort->isRegistrationOpen() || $this->hasUsableInvite(),
            'spotsLeft' => $cohort->spotsLeft(),
        ]);
    }
}
