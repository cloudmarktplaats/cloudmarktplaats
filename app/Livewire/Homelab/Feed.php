<?php

declare(strict_types=1);

namespace App\Livewire\Homelab;

use App\Jobs\Homelab\StoreHomelabPhotoJob;
use App\Models\HomelabPost;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
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

    public int $perPage = 12;

    public int $page = 1;

    public function mount(): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.homelab_feed'), 404);
    }

    public function submit(): void
    {
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
        $key = "homelab-post:user:{$userId}";
        if (RateLimiter::tooManyAttempts($key, maxAttempts: 1)) {
            $this->addError('body', 'Eén post per dag — probeer het morgen weer.');

            return;
        }

        $post = HomelabPost::query()->create([
            'user_id' => $userId,
            'body' => $this->body,
            'photo_path' => 'pending',
        ]);

        (new StoreHomelabPhotoJob(
            $post->id,
            (string) file_get_contents((string) $photo->getRealPath()),
            (string) $photo->getMimeType(),
        ))->handle();

        RateLimiter::hit($key, decaySeconds: 86400);

        $this->reset('photo', 'body');
        session()->flash('homelab-status', 'Je lab staat erop. Anoniem, zoals beloofd.');
    }

    public function loadMore(): void
    {
        $this->page++;
    }

    /**
     * @return Collection<int, HomelabPost>
     */
    public function posts(): Collection
    {
        return HomelabPost::query()
            ->published()
            ->orderByDesc('created_at')
            ->limit($this->perPage * $this->page)
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
