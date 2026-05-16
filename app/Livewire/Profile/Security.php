<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\User;
use App\Models\UserIdentity;
use App\Services\Auth\IdentityService;
use App\Services\Auth\LastIdentityException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Profile → Security page. Lists the user's linked login methods and
 * lets them unlink a method, except for the very last one (which would
 * lock the account out — enforced by {@see IdentityService}).
 *
 * New methods are linked through the existing OAuth/SIWE flows; this
 * component only renders entry points and handles the unlink action.
 */
#[Layout('layouts.app')]
class Security extends Component
{
    public function unlink(int $identityId): void
    {
        /** @var User $user */
        $user = auth()->user();

        /** @var UserIdentity $identity */
        $identity = $user->identities()->findOrFail($identityId);

        try {
            app(IdentityService::class)->unlink($user, $identity);
        } catch (LastIdentityException) {
            $this->addError('identity', 'Dit is je enige login-methode — kan niet verwijderd worden.');
        }
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('livewire.profile.security', [
            'identities' => $user->identities()->orderBy('provider')->get(),
        ]);
    }
}
