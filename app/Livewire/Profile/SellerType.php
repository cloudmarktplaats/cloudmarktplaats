<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\User;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Particulier of zakelijk verkopen.
 *
 * Bewust niet bij registratie gevraagd: dat verhoogt de drempel voor de
 * overgrote meerderheid die particulier is. Hier staat het, en de wizard wijst
 * ernaar op het moment dat het uitmaakt.
 *
 * Wij verifiëren het KvK-nummer niet. Dat is een keuze, geen omissie: een
 * gecontroleerd vinkje suggereert een garantie die we niet kunnen dragen. Wat
 * we wel doen is het vragen, het tonen, en handhaven op melding.
 */
#[Layout('components.layouts.marketing', ['title' => 'Verkopen als bedrijf — Cloudmarktplaats'])]
class SellerType extends Component
{
    public bool $isBusiness = false;

    public string $businessName = '';

    public string $businessRegistration = '';

    public string $businessVat = '';

    public bool $saved = false;

    public function mount(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $this->isBusiness = $user->seller_type === 'business';
        $this->businessName = (string) ($user->business_name ?? '');
        $this->businessRegistration = (string) ($user->business_registration ?? '');
        $this->businessVat = (string) ($user->business_vat ?? '');
    }

    public function save(): void
    {
        $this->saved = false;

        if ($this->isBusiness) {
            $this->validate([
                'businessName' => ['required', 'string', 'min:2', 'max:120'],
                'businessRegistration' => ['required', 'string', 'min:6', 'max:32'],
                'businessVat' => ['nullable', 'string', 'max:32'],
            ], [
                'businessName.required' => __('Vul de naam in waaronder je onderneming staat ingeschreven.'),
                'businessRegistration.required' => __('Een KvK-nummer (of buitenlands equivalent) is verplicht voor zakelijke verkopers.'),
            ]);
        }

        /** @var User $user */
        $user = auth()->user();
        $user->forceFill([
            'seller_type' => $this->isBusiness ? 'business' : 'private',
            'business_name' => $this->isBusiness ? trim($this->businessName) : null,
            'business_registration' => $this->isBusiness ? trim($this->businessRegistration) : null,
            'business_vat' => $this->isBusiness && trim($this->businessVat) !== '' ? trim($this->businessVat) : null,
        ])->save();

        $this->saved = true;
    }

    public function render(): View
    {
        return view('livewire.profile.seller-type');
    }
}
