<?php

declare(strict_types=1);

use App\Livewire\Listings\Wizard;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    $this->category = Category::factory()->create();
});

/**
 * Mirrors tests/Feature/Listings/WizardTest.php's photo-upload helper:
 * point Livewire's fake upload at the real EXIF fixture and report
 * image/jpeg so the wizard's mime validation passes.
 */
function photoUpload(): UploadedFile
{
    $fixtureBytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));
    $upload = UploadedFile::fake()->createWithContent('lab.jpg', $fixtureBytes);

    $reflection = new ReflectionClass($upload);
    $prop = $reflection->getProperty('mimeTypeToReport');
    $prop->setAccessible(true);
    $prop->setValue($upload, 'image/jpeg');

    return $upload;
}

it('auto-publishes a veteran\'s listing when autopublish is on', function () {
    config()->set('cloudmarktplaats.features.trust_autopublish', true);
    $veteran = User::factory()->create(['email_verified_at' => now(), 'created_at' => now()->subDays(40)]);
    Transaction::factory()->completed()->count(5)->create(['seller_user_id' => $veteran->id]);

    $this->actingAs($veteran);

    Livewire::test(Wizard::class)
        ->set('title', 'Dell PowerEdge R720 met redundante voeding')
        ->set('category_id', $this->category->id)
        ->set('condition', 'used')
        ->set('price_cents', 15000)
        ->call('next')
        ->set('description', 'Volledig getest, twee Xeon CPUs, 128GB RAM, geen geharde schijven.')
        ->set('region_postcode', '3500')
        ->set('shipping_pickup', true)
        ->call('next')
        ->set('photos', [photoUpload()])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    // The veteran already has 5 completed transactions (and their linked
    // listings) from the factory setup above, so scope to the title this
    // test just submitted.
    $listing = Listing::query()->where('user_id', $veteran->id)
        ->where('title', 'Dell PowerEdge R720 met redundante voeding')
        ->firstOrFail();
    expect($listing->fresh()->state)->toBe('published');
});

it('never auto-publishes a veteran\'s resubmission of a previously-rejected listing', function () {
    config()->set('cloudmarktplaats.features.trust_autopublish', true);
    $veteran = User::factory()->create(['email_verified_at' => now(), 'created_at' => now()->subDays(40)]);
    Transaction::factory()->completed()->count(5)->create(['seller_user_id' => $veteran->id]);

    // A listing that a moderator already rejected once, then the seller
    // reopened it in the wizard and reset it to draft for a resubmit.
    $listing = Listing::factory()->for($veteran)->create([
        'category_id' => $this->category->id,
        'state' => 'draft',
        'moderation_notes' => 'eerder afgewezen',
    ]);

    $this->actingAs($veteran);

    Livewire::test(Wizard::class, ['listing' => $listing])
        ->set('title', 'Dell PowerEdge R720 na afwijzing opnieuw ingediend')
        ->set('category_id', $this->category->id)
        ->set('condition', 'used')
        ->set('price_cents', 15000)
        ->call('next')
        ->set('description', 'Volledig getest, twee Xeon CPUs, 128GB RAM, geen geharde schijven.')
        ->set('region_postcode', '3500')
        ->set('shipping_pickup', true)
        ->call('next')
        ->set('photos', [photoUpload()])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect($listing->fresh()->state)->toBe('pending_review');
});

it('keeps a normal member\'s listing in pending_review', function () {
    config()->set('cloudmarktplaats.features.trust_autopublish', true);
    $member = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($member);

    Livewire::test(Wizard::class)
        ->set('title', 'HP ProLiant DL360 Gen9 server')
        ->set('category_id', $this->category->id)
        ->set('condition', 'used')
        ->set('price_cents', 8000)
        ->call('next')
        ->set('description', 'Werkende server, twee voedingen, rail kit inbegrepen.')
        ->set('region_postcode', '1011')
        ->set('shipping_pickup', true)
        ->call('next')
        ->set('photos', [photoUpload()])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    $listing = Listing::query()->where('user_id', $member->id)->firstOrFail();
    expect($listing->fresh()->state)->toBe('pending_review');
});
