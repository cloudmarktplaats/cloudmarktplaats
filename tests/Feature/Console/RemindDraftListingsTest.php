<?php

declare(strict_types=1);

use App\Mail\DraftReminderMail;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

/** Een concept dat 48+ uur stilligt en nog nooit een herinnering kreeg. */
function staleDraft(User $user, int $daysOld = 5): Listing
{
    $listing = Listing::factory()->for($user)->create(['state' => 'draft']);
    // updated_at wordt door de factory op nu gezet; direct terugzetten.
    Listing::query()->whereKey($listing->id)->update(['updated_at' => now()->subDays($daysOld)]);

    return $listing->refresh();
}

it('mails one reminder per seller, not per draft', function () {
    $user = User::factory()->create();
    staleDraft($user);
    staleDraft($user);
    staleDraft($user);

    $this->artisan('listings:remind-drafts')->assertSuccessful();

    Mail::assertQueued(DraftReminderMail::class, 1);
    Mail::assertQueued(DraftReminderMail::class, fn (DraftReminderMail $m) => $m->listings->count() === 3);
});

it('never reminds the same draft twice', function () {
    $user = User::factory()->create();
    staleDraft($user);

    $this->artisan('listings:remind-drafts')->assertSuccessful();
    $this->artisan('listings:remind-drafts')->assertSuccessful();

    Mail::assertQueued(DraftReminderMail::class, 1);
    expect(Listing::query()->whereNull('draft_reminded_at')->where('state', 'draft')->count())->toBe(0);
});

it('leaves a draft alone until it has been quiet long enough', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->for($user)->create(['state' => 'draft']);
    Listing::query()->whereKey($listing->id)->update(['updated_at' => now()->subHours(2)]);

    $this->artisan('listings:remind-drafts')->assertSuccessful();

    Mail::assertNothingQueued();
});

it('only touches drafts — published listings are none of its business', function () {
    $user = User::factory()->create();
    $published = Listing::factory()->for($user)->published()->create();
    Listing::query()->whereKey($published->id)->update(['updated_at' => now()->subDays(9)]);

    $this->artisan('listings:remind-drafts')->assertSuccessful();

    Mail::assertNothingQueued();
});

it('skips banned sellers and excluded user ids', function () {
    $banned = User::factory()->create(['is_banned' => true]);
    staleDraft($banned);

    $excluded = User::factory()->create();
    staleDraft($excluded);

    $this->artisan('listings:remind-drafts', ['--exclude' => [(string) $excluded->id]])->assertSuccessful();

    Mail::assertNothingQueued();
});

// --dry-run bestaat omdat rommelconcepten ("test1", toetsenbordgeklets) niet
// betrouwbaar automatisch te herkennen zijn: daar hoort een mens naar te kijken
// vóór er iets de deur uitgaat. Dan mag hij ook niets markeren.
it('sends nothing and marks nothing on a dry run', function () {
    $user = User::factory()->create();
    staleDraft($user);

    $this->artisan('listings:remind-drafts', ['--dry-run' => true])->assertSuccessful();

    Mail::assertNothingQueued();
    expect(Listing::query()->whereNotNull('draft_reminded_at')->count())->toBe(0);
});
