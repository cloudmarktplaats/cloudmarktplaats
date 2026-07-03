<?php

declare(strict_types=1);

namespace App\Livewire\Homelab;

use App\Exceptions\InvalidUploadException;
use App\Jobs\Homelab\StoreHomelabPhotoJob;
use App\Models\HomelabPost;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Publieke homelab-feed + post-formulier voor ingelogde gebruikers.
 *
 * Anonimiteitscontract: deze component (en zijn view) rendert nooit
 * iets dat de poster identificeert. Zie de spec.
 */
#[Layout('components.layouts.marketing', ['title' => 'Uit de homelabs — Cloudmarktplaats'])]
class Feed extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $photo = null;

    public string $body = '';

    #[Locked]
    public int $perPage = 12;

    #[Locked]
    public int $page = 1;

    public function mount(): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.homelab_feed'), 404);
    }

    public function submit(): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.homelab_feed'), 404);
        abort_unless(auth()->check(), 403);

        $this->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'body' => ['required', 'string', 'max:500'],
        ]);

        // 'photo' => 'required' above guarantees a file at this point;
        // narrow the type for PHPStan (public property stays nullable
        // so the form starts empty and $this->reset() can clear it).
        $photo = $this->photo;
        abort_if($photo === null, 422);

        $userId = (int) auth()->id();

        // Unconditional attempts-limiter: regardless of whether the upload
        // is valid, cap how often this account can drive the (expensive)
        // decode pipeline per hour. Distinct from the 24h success-limiter
        // below, which only fires once a post is actually persisted.
        $attemptsKey = "homelab-post-attempts:user:{$userId}";
        if (RateLimiter::tooManyAttempts($attemptsKey, maxAttempts: 5)) {
            $this->addError('photo', 'Te veel uploadpogingen. Probeer het over een uur opnieuw.');

            return;
        }
        RateLimiter::hit($attemptsKey, decaySeconds: 3600);

        $key = "homelab-post:user:{$userId}";
        if (RateLimiter::tooManyAttempts($key, maxAttempts: 1)) {
            $this->addError('body', 'Eén post per dag — probeer het morgen weer.');

            return;
        }

        try {
            $post = DB::transaction(function () use ($userId, $photo): HomelabPost {
                $post = HomelabPost::query()->create([
                    'user_id' => $userId,
                    'body' => $this->body,
                    'photo_path' => 'pending',
                ]);

                // Synchroon binnen de transactie: de rij wordt pas zichtbaar
                // (published mét echt foto-pad) zodra de pipeline slaagde.
                (new StoreHomelabPhotoJob(
                    $post->id,
                    (string) file_get_contents((string) $photo->getRealPath()),
                    (string) $photo->getMimeType(),
                ))->handle();

                return $post;
            });
        } catch (InvalidUploadException) {
            $this->addError('photo', 'Foto kon niet verwerkt worden: geen geldig beeldbestand of afmetingen buiten bereik.');

            return;
        }

        RateLimiter::hit($key, decaySeconds: 86400);

        $this->reset('photo', 'body');
        session()->flash('homelab-status', 'Je lab staat erop. Anoniem, zoals beloofd.');
    }

    public function loadMore(): void
    {
        $this->page++;
    }

    public function deleteOwn(string $ulid): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.homelab_feed'), 404);

        $post = HomelabPost::query()->where('ulid', $ulid)->published()->firstOrFail();

        abort_unless((int) auth()->id() === $post->user_id, 403);

        $post->update(['status' => 'removed']);
    }

    /**
     * @return Collection<int, HomelabPost>
     */
    public function posts(): Collection
    {
        $limit = min($this->perPage * $this->page, 480);

        return HomelabPost::query()
            ->published()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function render(): View
    {
        $posts = $this->posts();

        return view('livewire.homelab.feed', [
            'posts' => $posts,
            'hasMore' => HomelabPost::query()->published()->count() > $posts->count(),
        ]);
    }
}
