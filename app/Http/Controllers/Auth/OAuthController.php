<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\Auth\OAuthProviderRegistry;
use App\Services\FoundingCohort;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * Provider-agnostic OAuth controller.
 *
 * Provider whitelist lives in App\Services\Auth\OAuthProviderRegistry —
 * Big Tech identity providers are intentionally excluded and a unit test
 * enforces this exclusion at the source level.
 */
class OAuthController extends Controller
{
    public function redirect(Request $request, string $provider): SymfonyRedirectResponse|RedirectResponse
    {
        abort_unless(OAuthProviderRegistry::isAllowed($provider), 404);

        $key = 'oauth:'.$provider.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages([
                'oauth' => 'Te veel pogingen voor '.$provider.'. Probeer over een minuut opnieuw.',
            ]);
        }
        RateLimiter::hit($key, 60);

        /** @var SymfonyRedirectResponse $response */
        $response = Socialite::driver($provider)->redirect();

        return $response;
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(OAuthProviderRegistry::isAllowed($provider), 404);

        /** @var SocialiteUser $oauthUser */
        $oauthUser = Socialite::driver($provider)->user();
        $providerKey = OAuthProviderRegistry::identityProvider($provider);
        $uid = (string) $oauthUser->getId();

        // 1. Existing identity → log in returning user.
        $identity = UserIdentity::where('provider', $providerKey)
            ->where('provider_uid', $uid)
            ->first();
        if ($identity !== null) {
            $identity->update(['last_used_at' => now()]);
            $identityUser = $identity->user;
            if ($identityUser instanceof User) {
                // Gate on 2FA before completing login.
                if ($identityUser->two_factor_confirmed_at !== null) {
                    $request->session()->put('pending_2fa_user_id', $identityUser->id);

                    return redirect('/2fa/challenge');
                }

                auth()->login($identityUser);
                $this->postLogin($request, $identityUser);
            }

            return redirect('/');
        }

        $email = $oauthUser->getEmail();

        // 2. Email matches an existing local user but no identity yet.
        if ($email !== null && $email !== '') {
            $existing = User::where('email', $email)->first();
            if ($existing !== null) {
                // Allow linking only when the same email is currently authed.
                if (auth()->check() && auth()->user()?->email === $email) {
                    UserIdentity::firstOrCreate(
                        ['user_id' => auth()->id(), 'provider' => $providerKey],
                        ['provider_uid' => $uid, 'last_used_at' => now()],
                    );

                    return redirect('/profile/security')->with('status', 'identity-linked');
                }

                // No silent merge — force explicit login first.
                return redirect('/login')->withErrors([
                    'email' => "Een account met email {$email} bestaat al. Log eerst in met een bestaande methode om {$provider} te koppelen.",
                ]);
            }
        }

        // 3. Onboarding: brand-new user. Once the founding cohort is full and
        // the waitlist is on, registration is closed — send them to the
        // waitlist page rather than silently creating an account.
        if (! app(FoundingCohort::class)->isRegistrationOpen()) {
            return redirect('/register')->with('cohort_full', true);
        }

        $foundingMember = app(FoundingCohort::class)->hasFoundingSpot();

        $user = DB::transaction(function () use ($oauthUser, $providerKey, $uid, $provider, $request, $foundingMember) {
            $emailResolved = $oauthUser->getEmail() ?: "{$uid}@{$provider}.local";
            $nickname = $oauthUser->getNickname() ?: ($provider.'_'.$uid);
            $displayName = $oauthUser->getName() ?: ($oauthUser->getNickname() ?: 'New user');

            $u = User::create([
                'email' => $emailResolved,
                'username' => $this->uniqueUsername($nickname),
                'display_name' => $displayName,
                'password_hash' => null,
                'email_verified_at' => $oauthUser->getEmail() ? now() : null,
                'invite_credits' => (bool) config('cloudmarktplaats.features.invites')
                    ? (int) config('cloudmarktplaats.gamification.starting_invite_credits')
                    : 0,
                'is_founding_member' => $foundingMember,
            ]);

            UserIdentity::firstOrCreate(
                ['user_id' => $u->id, 'provider' => $providerKey],
                ['provider_uid' => $uid, 'last_used_at' => now()],
            );

            foreach (['tos', 'privacy'] as $type) {
                $doc = LegalDocument::current($type, app()->getLocale());
                if ($doc !== null) {
                    LegalAcceptance::create([
                        'user_id' => $u->id,
                        'legal_document_id' => $doc->id,
                        'accepted_at' => now(),
                        'ip_hash' => hash('sha256', ($request->ip() ?? '').config('app.key')),
                    ]);
                }
            }

            return $u;
        });

        auth()->login($user);
        $this->postLogin($request, $user);

        return redirect('/');
    }

    private function postLogin(Request $request, User $user): void
    {
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();
    }

    private function uniqueUsername(string $base): string
    {
        $clean = preg_replace('/[^a-z0-9_-]/i', '', $base);
        $clean = is_string($clean) && $clean !== '' ? strtolower($clean) : 'user';

        $candidate = $clean;
        $i = 0;
        while (User::where('username', $candidate)->exists()) {
            $i++;
            $candidate = $clean.$i;
        }

        return $candidate;
    }
}
