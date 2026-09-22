<?php

declare(strict_types=1);

use App\Livewire\Listings\Wizard;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Livewire\Livewire;

it('serves the wipe page with the disclaimer above the commands', function () {
    $html = $this->get('/data-wissen')->assertOk()->getContent();

    expect($html)->toContain('Lees dit eerst')
        ->and($html)->toContain('aanvaarden geen aansprakelijkheid')
        ->and($html)->toContain('verwerkingsverantwoordelijke')
        ->and(strpos($html, 'Lees dit eerst'))->toBeLessThan(strpos($html, 'shred -n 0 -z'));
});

// De methode per schijftype is het hele punt: shred op een SSD doet niets.
it('names a different method per drive type', function () {
    $this->get('/data-wissen')
        ->assertOk()
        ->assertSee('shred -n 0 -z -v /dev/sda')
        ->assertSee('security-erase p /dev/sda')
        ->assertSee('nvme format /dev/nvme0n1 -s 2')
        ->assertSee('Een SSD overschrijven');
});

// "Bij sommige schijven weet ik het echt niet meer" is bijna nooit een schijf
// zonder SMART, maar een USB-brug of een RAID-controller ertussen. Zonder die
// terugvallen loopt de lezer dood op de enige regel die stap 2 had.
it('names a way out when smartctl returns nothing', function () {
    $this->get('/data-wissen')
        ->assertOk()
        ->assertSee('smartctl -a -d sat /dev/sdb')
        ->assertSee('smartctl -a -d megaraid,0 /dev/sda')
        ->assertSee('nvme smart-log /dev/nvme0')
        ->assertSee('draaiuren onbekend');
});

// Issue #43: de pagina leunde volledig op Linux. Windows kan drie stappen native;
// de firmware-wis niet, en dat eerlijk zeggen is het punt, geen tool verzinnen.
it('gives the Windows way and is honest about what it cannot do', function () {
    $this->get('/data-wissen')
        ->assertOk()
        ->assertSee('Get-StorageReliabilityCounter')
        ->assertSee('clean all')
        ->assertSee('dit kan Windows niet zelf');
});

it('links the wipe page from the footer and from the scope page', function () {
    $this->get('/')->assertOk()->assertSee(route('wipe'), false);
    $this->get('/wat-mag-erop')->assertOk()->assertSee(route('wipe'), false);
});

// De twijfel bij opslag ("staat er nog wat op?") ontstaat bij het kiezen van de
// categorie, dus daar hoort het antwoord. Losgekoppeld leest niemand het.
it('offers the wipe instructions in the listing wizard', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    Livewire::test(Wizard::class)
        ->assertSee(route('wipe'), false)
        ->assertSee('Data eraf, altijd.');
});

// Zonder de juiste id's in de Alpine-set blijft het paneel altijd verborgen,
// en dat is precies het soort stille storing dat niemand meldt.
it('passes every storage category id to the wizard view', function () {
    $this->seed(CategorySeeder::class);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $ids = Category::query()->where('is_active', true)
        ->where('path', 'like', 'storage%')->pluck('id');

    expect($ids)->not->toBeEmpty();

    $component = Livewire::test(Wizard::class);

    expect($component->viewData('storageCategoryIds')->all())
        ->toEqualCanonicalizing($ids->map(fn (int $id): string => (string) $id)->all());

    // Strings, want een <select> geeft nooit een int terug en dan matcht
    // includes() nooit.
    expect($component->viewData('storageCategoryIds')->first())->toBeString();
});
