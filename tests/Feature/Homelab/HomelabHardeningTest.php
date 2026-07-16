<?php

declare(strict_types=1);

use App\Livewire\Homelab\Feed;
use App\Models\HomelabPost;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('clamps tampered pagination', function () {
    expect(fn () => Livewire::test(Feed::class)->set('page', 999999))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('throttles repeated failed upload attempts', function () {
    $user = User::factory()->create();

    // A structurally valid PNG that passes the `mimes:` validation rule
    // but is too small (below MIN_DIM) to pass the job's dimension gate —
    // this is what exercises the attempts limiter added in Fix 2a, since
    // the limiter sits after validate() but before the dimension check.
    $tmp = tempnam(sys_get_temp_dir(), 'homelab-tiny').'.png';
    imagepng(imagecreatetruecolor(50, 50), $tmp);
    $bytes = (string) file_get_contents($tmp);
    unlink($tmp);

    for ($i = 0; $i < 5; $i++) {
        Livewire::actingAs($user)
            ->test(Feed::class)
            ->set('photos', [UploadedFile::fake()->createWithContent("tiny{$i}.png", $bytes)])
            ->set('body', 'te klein plaatje')
            ->call('submit')
            ->assertHasErrors(['photos']);
    }

    Livewire::actingAs($user)
        ->test(Feed::class)
        ->set('photos', [UploadedFile::fake()->createWithContent('tiny-6.png', $bytes)])
        ->set('body', 'te klein plaatje')
        ->call('submit')
        ->assertHasErrors(['photos']);

    $test = Livewire::actingAs($user)
        ->test(Feed::class)
        ->set('photos', [UploadedFile::fake()->createWithContent('tiny-7.png', $bytes)])
        ->set('body', 'te klein plaatje')
        ->call('submit');

    expect($test->errors()->first('photos'))->toContain('Te veel uploadpogingen');
    expect(HomelabPost::query()->count())->toBe(0);
});

it('rejects posting when the flag is turned off mid-session', function () {
    $user = User::factory()->create();
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));

    $component = Livewire::actingAs($user)->test(Feed::class);

    config()->set('cloudmarktplaats.features.homelab_feed', false);

    $component
        ->set('photos', [UploadedFile::fake()->createWithContent('lab.jpg', $bytes)])
        ->set('body', 'na uitschakelen van de flag')
        ->call('submit')
        ->assertStatus(404);

    expect(HomelabPost::query()->count())->toBe(0);
});

it('404s deleteOwn on a removed post', function () {
    $user = User::factory()->create();
    $post = HomelabPost::factory()->for($user)->removed()->create();

    // The `published()` scope means a removed post simply isn't found —
    // Livewire's test harness only converts HttpException/AuthorizationException
    // into HTTP responses, so the underlying ModelNotFoundException from
    // firstOrFail() surfaces directly here (it maps to a real 404 response
    // in production via Laravel's exception handler).
    expect(fn () => Livewire::actingAs($user)
        ->test(Feed::class)
        ->call('deleteOwn', $post->ulid))
        ->toThrow(ModelNotFoundException::class);

    expect($post->refresh()->status)->toBe('removed');
});
