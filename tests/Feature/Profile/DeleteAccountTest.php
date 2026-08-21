<?php

declare(strict_types=1);

use App\Livewire\Profile\DeleteAccount;
use App\Models\HomelabPhoto;
use App\Models\HomelabPost;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Het privacybeleid belooft sinds dag één het recht op verwijdering en zegt
 * dat een account "zolang je een account hebt" bewaard blijft. Er was geen
 * knop. Een lid vroeg op 21-08 per mail om verwijdering omdat de site die
 * optie miste — dat is een AVG-verzoek dat we met de hand moesten afhandelen,
 * binnen een maand, zonder dat iemand het zag aankomen.
 */

it('shows the delete-account page to a signed-in member', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/profile/verwijderen')
        ->assertOk()
        ->assertSee('Account verwijderen');
});

it('is not reachable without logging in', function () {
    $this->get('/profile/verwijderen')->assertRedirect('/login');
});

it('refuses to delete when the typed username does not match', function () {
    $user = User::factory()->create(['username' => 'rob']);

    Livewire::actingAs($user)
        ->test(DeleteAccount::class)
        ->set('confirmUsername', 'rob ')
        ->call('destroyAccount')
        ->assertHasErrors('confirmUsername');

    expect(User::withTrashed()->find($user->id))->not->toBeNull();
});

it('deletes the account and everything hanging off it', function () {
    Storage::fake('public');

    $user = User::factory()->create(['username' => 'rob']);
    $listing = Listing::factory()->for($user)->published()->create();
    $photo = ListingPhoto::factory()->for($listing)->create(['disk' => 'local', 'mime' => 'image/jpeg']);
    $post = HomelabPost::factory()->for($user)->create();
    $identity = UserIdentity::factory()->for($user)->create();

    $dir = "listings/{$listing->ulid}/{$photo->id}";
    Storage::disk('public')->put("{$dir}/original.jpg", 'x');
    Storage::disk('public')->put("{$dir}/card.webp", 'x');
    $photo->forceFill(['path' => "{$dir}/card.webp"])->save();

    Livewire::actingAs($user)
        ->test(DeleteAccount::class)
        ->set('confirmUsername', 'rob')
        ->call('destroyAccount')
        ->assertRedirect('/');

    // withTrashed(): User gebruikt SoftDeletes, dus zonder deze regel zou een
    // half werkende verwijdering hier alsnog groen zijn — terwijl het account
    // met adres en al gewoon in de database staat.
    expect(User::withTrashed()->find($user->id))->toBeNull()
        ->and(Listing::withTrashed()->find($listing->id))->toBeNull()
        ->and(ListingPhoto::query()->find($photo->id))->toBeNull()
        ->and(HomelabPost::query()->find($post->id))->toBeNull()
        ->and(UserIdentity::query()->find($identity->id))->toBeNull();

    Storage::disk('public')->assertMissing("{$dir}/card.webp");
    Storage::disk('public')->assertMissing("{$dir}/original.jpg");
});

// De advertentiefoto's gingen wél weg en de homelab-foto's niet: die hangen
// aan `homelab_posts`, dus de cascade ruimde de rij op en liet het bestand op
// schijf achter. Van buiten ziet dat eruit als een voltooide verwijdering.
it('also erases the photo files behind a homelab post', function () {
    Storage::fake('public');

    $user = User::factory()->create(['username' => 'rob']);
    $post = HomelabPost::factory()->for($user)->create();
    $photo = HomelabPhoto::factory()->for($post, 'post')->create([
        'disk' => 'local',
        'mime' => 'image/jpeg',
    ]);

    $dir = "homelabs/{$post->ulid}/0";
    Storage::disk('public')->put("{$dir}/original.jpg", 'x');
    Storage::disk('public')->put("{$dir}/card.webp", 'x');
    Storage::disk('public')->put("{$dir}/thumb.webp", 'x');
    $photo->forceFill(['path' => "{$dir}/card.webp"])->save();

    Livewire::actingAs($user)
        ->test(DeleteAccount::class)
        ->set('confirmUsername', 'rob')
        ->call('destroyAccount');

    expect(HomelabPhoto::query()->find($photo->id))->toBeNull();

    Storage::disk('public')->assertMissing("{$dir}/original.jpg");
    Storage::disk('public')->assertMissing("{$dir}/card.webp");
    Storage::disk('public')->assertMissing("{$dir}/thumb.webp");
});

// Dit ging echt mis bij de eerste verwijdering op productie (21-08). De oudste
// homelab-rijen hebben een `mime` die niet klopt met wat er op schijf staat —
// kolom zegt image/webp, bestand heet original.jpg — én ze missen het
// position-segment in het pad. Wie de bestandsnaam uit `mime` samenstelt, wist
// `original.webp` (bestaat niet) en laat de echte foto staan: de gebruiker is
// van elk scherm verdwenen terwijl zijn foto nog geserveerd wordt.
it('erases photo files even when the mime column does not match what is on disk', function () {
    Storage::fake('public');

    $user = User::factory()->create(['username' => 'rob']);
    $post = HomelabPost::factory()->for($user)->create();
    $photo = HomelabPhoto::factory()->for($post, 'post')->create([
        'disk' => 'local',
        'mime' => 'image/webp',
    ]);

    $dir = "homelabs/{$post->ulid}";
    Storage::disk('public')->put("{$dir}/original.jpg", 'x');
    Storage::disk('public')->put("{$dir}/card.webp", 'x');
    $photo->forceFill(['path' => "{$dir}/card.webp"])->save();

    Livewire::actingAs($user)
        ->test(DeleteAccount::class)
        ->set('confirmUsername', 'rob')
        ->call('destroyAccount');

    Storage::disk('public')->assertMissing("{$dir}/original.jpg");
    Storage::disk('public')->assertMissing("{$dir}/card.webp");
});

it('logs the member out, so the session cannot outlive the account', function () {
    $user = User::factory()->create(['username' => 'rob']);

    Livewire::actingAs($user)
        ->test(DeleteAccount::class)
        ->set('confirmUsername', 'rob')
        ->call('destroyAccount');

    expect(auth()->check())->toBeFalse();
});

// De pagina moet zeggen wát er weggaat. "Weet je het zeker?" zonder inhoud is
// geen geïnformeerde toestemming, en dit is onomkeerbaar.
it('spells out what disappears before you agree to it', function () {
    $user = User::factory()->create();
    Listing::factory()->for($user)->published()->create();

    $this->actingAs($user)
        ->get('/profile/verwijderen')
        ->assertOk()
        ->assertSee('1 advertentie')
        ->assertSee('onomkeerbaar');
});

it('links to the delete page from the security page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/profile/security')
        ->assertOk()
        ->assertSee('/profile/verwijderen', false);
});
