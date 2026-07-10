<?php

declare(strict_types=1);

namespace App\Livewire\Listings;

use App\Jobs\Listings\StoreListingPhotoJob;
use App\Models\Category;
use App\Models\Listing;
use App\Services\Admin\AdminActionLogger;
use App\Services\Gamification\TrustLevelService;
use App\Services\Listings\InvalidStateTransition;
use App\Services\Listings\ListingStateService;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * 3-step listing creation/edit wizard.
 *
 *   Step 1 (basics)   — category, title, condition, price, trade-allowed
 *   Step 2 (details)  — description, region postcode, shipping options
 *   Step 3 (photos)   — 1..10 uploads, dispatched through StoreListingPhotoJob
 *
 * Drafts are persisted after every successful step transition so a user
 * can refresh the page or close the tab without losing work. The draft
 * lives in the `listings` table with `state = 'draft'`; the final
 * `submit()` action moves it to `pending_review` via the state machine,
 * which guarantees the moderation pipeline can't be bypassed.
 *
 * Photo uploads dispatch {@see StoreListingPhotoJob} synchronously today
 * (Foundation phase) so the user gets immediate feedback. A future
 * queue worker swap to `dispatch()` is a one-line change.
 */
#[Layout('layouts.app')]
class Wizard extends Component
{
    use WithFileUploads;

    public ?Listing $listing = null;

    /**
     * True when the wizard was opened to edit an existing listing (rather
     * than create a new one). Editing a published listing re-submits it for
     * moderation on save — see {@see submit()} — so the copy and the photo
     * step adapt accordingly (existing photos are kept, new ones optional).
     */
    public bool $editing = false;

    public int $step = 1;

    // Step 1 fields
    public string $title = '';

    public ?int $category_id = null;

    public string $condition = 'used';

    public int $price_cents = 0;

    public bool $is_trade_allowed = false;

    // Step 2 fields
    public string $description = '';

    public ?string $region_postcode = null;

    public bool $shipping_pickup = true;

    public bool $shipping_post = false;

    /** @var array<int, UploadedFile> */
    public array $photos = [];

    public function mount(?Listing $listing = null): void
    {
        if ($listing !== null && $listing->exists) {
            abort_unless(auth()->user()?->can('update', $listing) ?? false, 403);
            $this->editing = true;
            $this->listing = $listing;
            $this->title = (string) $listing->title;
            $this->category_id = $listing->category_id;
            $this->condition = (string) $listing->condition;
            $this->price_cents = (int) $listing->price_cents;
            $this->is_trade_allowed = (bool) $listing->is_trade_allowed;
            $this->description = (string) ($listing->description ?? '');
            $this->region_postcode = $listing->region_postcode;
            /** @var array<string, bool> $opts */
            $opts = (array) $listing->shipping_options;
            $this->shipping_pickup = (bool) ($opts['pickup'] ?? true);
            $this->shipping_post = (bool) ($opts['post'] ?? false);
        }
    }

    public function next(): void
    {
        if ($this->step === 1) {
            $this->validate([
                'title' => ['required', 'string', 'min:5', 'max:120'],
                'category_id' => ['required', 'integer', 'exists:categories,id'],
                'condition' => ['required', 'in:new,used,defective,for_parts'],
                'price_cents' => ['required', 'integer', 'min:0'],
                'is_trade_allowed' => ['boolean'],
            ]);
            $this->saveDraft(step1: true);
            $this->step = 2;

            return;
        }

        if ($this->step === 2) {
            $this->validate([
                'description' => ['required', 'string', 'min:20', 'max:8000'],
                'region_postcode' => ['nullable', 'string', 'size:4'],
                'shipping_pickup' => ['boolean'],
                'shipping_post' => ['boolean'],
            ]);
            $this->saveDraft(step1: false);
            $this->step = 3;
        }
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function submit(): void
    {
        // When editing an existing listing that already has photos, keeping
        // the current photos is enough — new uploads are optional. A brand
        // new listing still requires at least one photo.
        $alreadyHasPhotos = $this->listing !== null && $this->listing->photos()->exists();

        $this->validate([
            'photos' => [$alreadyHasPhotos ? 'nullable' : 'required', 'array', 'max:10'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        if ($this->listing === null) {
            $this->saveDraft(step1: true);
        }

        $listing = $this->listing;
        abort_if($listing === null, 500);

        $position = (int) $listing->photos()->max('position');
        foreach ($this->photos as $upload) {
            $position++;
            // We invoke ::handle() directly rather than dispatchSync().
            // dispatchSync still goes through the queue serializer, which
            // refuses raw binary payloads (JSON requires UTF-8). The job
            // is idempotent so running it inline gives identical semantics.
            (new StoreListingPhotoJob(
                $listing->id,
                (string) file_get_contents($upload->getRealPath()),
                (string) $upload->getMimeType(),
                $position,
            ))->handle();
        }

        try {
            $stateService = app(ListingStateService::class);
            $stateService->transition($listing, 'pending_review');

            // Trusted veterans skip the moderation queue (flag-gated,
            // sales-gated — see TrustLevelService). A listing with a
            // rejection history (moderation_notes set) is NEVER auto-
            // published: the moderator's rejection stays binding.
            $user = auth()->user();
            if ($user !== null
                && $listing->moderation_notes === null
                && app(TrustLevelService::class)->canSkipModeration($user)) {
                $stateService->transition($listing, 'published');
                AdminActionLogger::log('listing.autopublish', 'listing', $listing->id);
            }
        } catch (InvalidStateTransition $e) {
            $this->addError('state', $e->getMessage());

            return;
        }

        $this->redirect("/listings/{$listing->ulid}-{$listing->slug}");
    }

    private function saveDraft(bool $step1): void
    {
        $payload = $step1
            ? [
                'user_id' => auth()->id(),
                'category_id' => $this->category_id,
                'title' => $this->title,
                'condition' => $this->condition,
                'price_cents' => $this->price_cents,
                'is_trade_allowed' => $this->is_trade_allowed,
                // Description is nullable for drafts (see migration
                // `add_nullable_description_to_listings`); the step-2
                // validation enforces a real value before submit.
                'description' => $this->description !== '' ? $this->description : null,
                'state' => 'draft',
            ]
            : [
                'description' => $this->description,
                'region_postcode' => $this->region_postcode,
                'shipping_options' => [
                    'pickup' => $this->shipping_pickup,
                    'post' => $this->shipping_post,
                ],
            ];

        if ($this->listing === null) {
            $this->listing = Listing::query()->create($payload);

            return;
        }

        $this->listing->fill($payload)->save();
    }

    public function render(): View
    {
        // Group active categories under their top-level parent (first ltree
        // segment) so the picker reads as a hierarchy instead of one flat
        // alphabetical mix. Each group: top name => [id => label], with the
        // top itself offered as a "— algemeen" fallback above its children.
        $all = Category::query()->where('is_active', true)->orderBy('name')->get();
        $byTop = $all->groupBy(fn (Category $c): string => explode('.', (string) $c->path)[0]);

        $categoryGroups = [];
        foreach ($all->filter(fn (Category $c): bool => ! str_contains((string) $c->path, '.'))->sortBy('name') as $top) {
            $options = [$top->id => $top->name.' — algemeen'];
            foreach ($byTop->get((string) $top->path, collect())->filter(fn (Category $c): bool => str_contains((string) $c->path, '.'))->sortBy('name') as $child) {
                $options[$child->id] = $child->name;
            }
            $categoryGroups[$top->name] = $options;
        }

        return view('livewire.listings.wizard', ['categoryGroups' => $categoryGroups]);
    }
}
