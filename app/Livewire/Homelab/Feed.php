<?php

declare(strict_types=1);

namespace App\Livewire\Homelab;

use App\Exceptions\UpvoteException;
use App\Jobs\Homelab\StoreHomelabPhotoJob;
use App\Models\HomelabPost;
use App\Models\HomelabPostUpvote;
use App\Services\Gamification\UpvoteService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
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

    /** @var array<int, UploadedFile> */
    public array $photos = [];

    public ?string $title = null;

    public string $body = '';

    public ?string $feedbackPrompt = null;

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

        $maxKb = (int) (config('cloudmarktplaats.photos.max_bytes') / 1024);
        $maxCount = (int) config('cloudmarktplaats.photos.homelab_max_count');

        $this->validate([
            'photos' => ['required', 'array', 'max:'.$maxCount],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxKb],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:500'],
            'feedbackPrompt' => ['nullable', 'string', 'max:280'],
        ], [
            'photos.required' => __('We hebben geen foto\'s ontvangen. Koos je ze wél? Dan is het uploaden misgegaan — probeer het opnieuw, eventueel met minder of kleinere foto\'s.'),
            'photos.max' => __('Maximaal :max foto\'s per homelab.'),
            'photos.*.uploaded' => __('Deze foto is niet aangekomen. Meestal is hij te groot: maximaal :max MB per foto.', ['max' => (int) (config('cloudmarktplaats.photos.max_bytes') / 1024 / 1024)]),
            'photos.*.max' => __('Deze foto is te groot. Maximaal :max MB per foto.', ['max' => (int) (config('cloudmarktplaats.photos.max_bytes') / 1024 / 1024)]),
            'photos.*.mimes' => __('Alleen JPG, PNG of WebP.'),
        ]);

        $userId = (int) auth()->id();

        // Unconditional attempts-limiter: regardless of whether the upload
        // is valid, cap how often this account can drive the (expensive)
        // decode pipeline per hour. Distinct from the 24h success-limiter
        // below, which only fires once a post is actually persisted.
        $attemptsKey = "homelab-post-attempts:user:{$userId}";
        if (RateLimiter::tooManyAttempts($attemptsKey, maxAttempts: 5)) {
            $this->addError('photos', 'Te veel uploadpogingen. Probeer het over een uur opnieuw.');

            return;
        }
        RateLimiter::hit($attemptsKey, decaySeconds: 3600);

        $key = "homelab-post:user:{$userId}";
        if (RateLimiter::tooManyAttempts($key, maxAttempts: 1)) {
            // Het is een voortschrijdende 24 uur vanaf je laatste post, geen
            // kalenderdag — dus geef het echte aantal uren, niet "morgen".
            $hours = max(1, (int) ceil(RateLimiter::availableIn($key) / 3600));
            $this->addError('body', __('Eén homelab per 24 uur. Je kunt over :hours uur weer plaatsen.', ['hours' => $hours]));

            return;
        }

        try {
            $post = DB::transaction(function () use ($userId): HomelabPost {
                $post = HomelabPost::query()->create([
                    'user_id' => $userId,
                    'title' => $this->title,
                    'body' => $this->body,
                    'feedback_prompt' => $this->feedbackPrompt,
                    'comments_open' => true,
                    'photo_disk' => (string) config('cloudmarktplaats.storage.driver', 'local'),
                    'photo_path' => 'pending',
                ]);

                // Synchroon binnen de transactie: de post wordt pas zichtbaar als
                // álle foto's geslaagd zijn. Faalt er één, dan rolt de hele
                // transactie terug — geen homelab met een gat in de galerij.
                foreach (array_values($this->photos) as $position => $photo) {
                    (new StoreHomelabPhotoJob(
                        $post->id,
                        (string) file_get_contents((string) $photo->getRealPath()),
                        (string) $photo->getMimeType(),
                        $position,
                    ))->handle();
                }

                return $post;
            });
        } catch (\Throwable $e) {
            report($e);
            $this->addError('photos', __('Het uploaden is misgegaan. Vaak zijn de foto\'s samen te groot, of viel de verbinding weg. Probeer het opnieuw met minder of kleinere foto\'s.'));

            return;
        }

        RateLimiter::hit($key, decaySeconds: 86400);

        $this->reset('photos', 'title', 'body', 'feedbackPrompt');
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

    public function upvote(string $ulid): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.homelab_upvotes'), 404);
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $key = 'homelab-upvote:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 60)) {
            $this->addError('upvote', 'Rustig aan met waarderen.');

            return;
        }
        RateLimiter::hit($key, 3600);

        $post = HomelabPost::query()->where('ulid', $ulid)->published()->firstOrFail();
        try {
            app(UpvoteService::class)->toggle($post, $user);
        } catch (UpvoteException $e) {
            $this->addError('upvote', $e->getMessage());
        }
    }

    /**
     * @return Collection<int, HomelabPost>
     */
    public function posts(): Collection
    {
        $limit = min($this->perPage * $this->page, 480);

        return HomelabPost::query()
            ->published()
            ->withCount('upvotes')
            ->with('photos')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function render(): View
    {
        $posts = $this->posts();

        $upvotedIds = auth()->check()
            ? HomelabPostUpvote::query()
                ->where('user_id', (int) auth()->id())
                ->whereIn('homelab_post_id', $posts->pluck('id'))
                ->pluck('homelab_post_id')->all()
            : [];

        return view('livewire.homelab.feed', [
            'posts' => $posts,
            'hasMore' => HomelabPost::query()->published()->count() > $posts->count(),
            'upvotedIds' => $upvotedIds,
        ]);
    }
}
