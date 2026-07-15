<?php

declare(strict_types=1);

use App\Livewire\Listings\Wizard;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    $this->category = Category::factory()->create();
    $this->user = User::factory()->create(['email_verified_at' => now()]);
});

/*
 * `photos` ends up empty for two very different reasons: the seller picked
 * nothing, or their upload failed and Livewire silently left the property
 * empty. Laravel's default message ("The photos field is required") only
 * describes the first, so a seller whose upload died was told they had
 * forgotten photos they had just picked. Yvan reported exactly that.
 */
it('blames the upload, not the seller, when photos never arrived', function () {
    $this->actingAs($this->user);

    $component = Livewire::test(Wizard::class)
        ->set('title', 'Cisco Catalyst 2960')
        ->set('category_id', $this->category->id)
        ->set('condition', 'used')
        ->set('price_cents', 5000)
        ->call('next')
        ->set('description', 'Fully working 24-port switch with all original modules.')
        ->set('region_postcode', '3500')
        ->call('next')
        ->set('photos', [])
        ->call('submit')
        ->assertHasErrors(['photos' => 'required']);

    // The seller must be able to tell the two cases apart from the message.
    $component->assertSee('uploaden misgegaan')
        ->assertDontSee('The photos field is required');
});

it('walks step 1 → 2 → 3 and persists a draft listing per step', function () {
    $this->actingAs($this->user);

    $component = Livewire::test(Wizard::class)
        ->set('title', 'Vintage Sun Sparc Server')
        ->set('category_id', $this->category->id)
        ->set('condition', 'used')
        ->set('price_cents', 25000)
        ->set('is_trade_allowed', false)
        ->call('next');

    $component->assertSet('step', 2);

    $draft = Listing::query()->where('user_id', $this->user->id)->firstOrFail();
    expect($draft->state)->toBe('draft')
        ->and($draft->title)->toBe('Vintage Sun Sparc Server')
        ->and($draft->price_cents)->toBe(25000)
        // Step 1 saves the draft without forcing a placeholder description.
        // The migration `add_nullable_description_to_listings` makes the
        // column nullable so a draft can honestly say "no description yet".
        ->and($draft->description)->toBeNull();

    $component
        ->set('description', 'Werkende UltraSPARC met origineel rack.')
        ->set('region_postcode', '1011')
        ->set('shipping_pickup', true)
        ->set('shipping_post', false)
        ->call('next')
        ->assertSet('step', 3);

    expect($draft->refresh()->description)->toContain('Werkende UltraSPARC');
});

it('submits the wizard: dispatches photo job + transitions to pending_review', function () {
    $this->actingAs($this->user);

    // Livewire's WithFileUploads accepts an `UploadedFile::fake()` which
    // is enough to satisfy the validation pipeline. We then point the
    // wizard at our real EXIF fixture by writing the fake's tmp file
    // content from the fixture so the photo job exercises the real
    // EXIF-strip path end-to-end.
    $fixtureBytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));
    $upload = UploadedFile::fake()->createWithContent('photo.jpg', $fixtureBytes);
    // The fake reports application/octet-stream by default — override
    // so Laravel's `mimes:jpg,...` validator sees image/jpeg.
    $reflection = new ReflectionClass($upload);
    $prop = $reflection->getProperty('mimeTypeToReport');
    $prop->setAccessible(true);
    $prop->setValue($upload, 'image/jpeg');

    Livewire::test(Wizard::class)
        ->set('title', 'Cisco Catalyst 2960')
        ->set('category_id', $this->category->id)
        ->set('condition', 'used')
        ->set('price_cents', 5000)
        ->call('next')
        ->set('description', 'Fully working 24-port switch with all original modules.')
        ->set('region_postcode', '3500')
        ->set('shipping_pickup', true)
        ->call('next')
        ->set('photos', [$upload])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    $listing = Listing::query()->where('user_id', $this->user->id)->firstOrFail();
    expect($listing->state)->toBe('pending_review')
        ->and($listing->photos()->count())->toBe(1);
});

it('edits a published listing: updates price + description, no new photos needed, back to moderation', function () {
    $this->actingAs($this->user);

    $listing = Listing::factory()->for($this->user)->published()->create([
        'category_id' => $this->category->id,
        'price_cents' => 10000,
        'description' => 'Originele beschrijving met genoeg tekens erin.',
    ]);
    ListingPhoto::factory()->for($listing)->create();

    Livewire::test(Wizard::class, ['listing' => $listing])
        ->assertSet('editing', true)
        ->set('price_cents', 27500)
        ->call('next')
        ->assertHasNoErrors()
        ->set('description', 'Bijgewerkte beschrijving met ruim voldoende lengte om te slagen.')
        ->call('next')
        ->assertHasNoErrors()
        // No new upload — existing photo is enough.
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    $listing->refresh();
    expect($listing->price_cents)->toBe(27500)
        ->and($listing->description)->toContain('Bijgewerkte beschrijving')
        ->and($listing->state)->toBe('pending_review')
        ->and($listing->photos()->count())->toBe(1);
});

it('rejects step 1 without required fields', function () {
    $this->actingAs($this->user);

    // Component ships with safe defaults (condition=used, price_cents=0),
    // so we only test the truly empty/null fields here.
    Livewire::test(Wizard::class)
        ->call('next')
        ->assertHasErrors(['title', 'category_id']);
});
