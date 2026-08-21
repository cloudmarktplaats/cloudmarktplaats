<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\Listing;
use App\Models\User;
use App\Services\Profile\AccountRemovalService;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Profile → Account verwijderen.
 *
 * The privacy statement has always promised the right to erasure, but there
 * was no button anywhere: a member who wanted out had to find an address, and
 * the only address the site showed was the one for sponsoring. That turned an
 * ordinary wish into a formal AVG request, handled by hand, on a one-month
 * clock. This is that request, self-service.
 *
 * Confirmation is by typing the username rather than the password: a large
 * share of members sign in through OAuth or SIWE and have no password at all
 * ({@see User::$password_hash} is nullable), so a password prompt
 * would lock exactly those people out of their own erasure right.
 */
#[Layout('components.layouts.marketing', ['title' => 'Account verwijderen — Cloudmarktplaats'])]
class DeleteAccount extends Component
{
    public string $confirmUsername = '';

    public function destroyAccount(): void
    {
        /** @var User $user */
        $user = auth()->user();

        if ($this->confirmUsername !== $user->username) {
            $this->addError('confirmUsername', __('Dat is niet je gebruikersnaam. Typ hem precies over om te bevestigen.'));

            return;
        }

        app(AccountRemovalService::class)->remove($user);

        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();
        session()->flash('account_deleted', true);

        $this->redirect('/');
    }

    public function render(): View
    {
        return view('livewire.profile.delete-account', [
            'listingCount' => Listing::query()->where('user_id', auth()->id())->count(),
        ]);
    }
}
