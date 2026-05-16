<?php

declare(strict_types=1);

namespace App\Http\Controllers\Listings;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Public report endpoint for listings.
 *
 * The marketplace exposes a single, polymorphic `reports` table that
 * stores reports against any model (listing, user, forum post). For
 * Foundation we only wire the listing flow; future sub-projects can
 * add more `store…` handlers without changing the table schema.
 *
 * Rate-limiting: a single user can file at most 10 reports per hour
 * across all reportables. This stops a malicious actor from spamming
 * the admin queue.
 */
class ReportController extends Controller
{
    public function storeForListing(Request $request, Listing $listing): RedirectResponse
    {
        $userId = (int) $request->user()?->id;

        $key = "reports:user:{$userId}";
        if (RateLimiter::tooManyAttempts($key, maxAttempts: 10)) {
            return back()->withErrors(['reason' => 'Te veel rapportages, probeer het later opnieuw.']);
        }
        RateLimiter::hit($key, decaySeconds: 3600);

        $data = $request->validate([
            'reason' => ['required', 'in:illegal,stolen,spam,wrong_category,other'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        // Dedup: a user must not be able to file the same report twice
        // against the same listing while their first one is still open.
        // We re-allow once the moderation team has closed (resolved /
        // dismissed) the original — that gives reporters a path to flag a
        // re-listing of the same offending content. Implemented as a
        // controller check rather than a unique index because the dedup
        // semantics are scoped to "open" status, which a partial unique
        // index could express but at the cost of pulling Postgres-specific
        // syntax into the schema.
        $alreadyOpen = Report::query()
            ->where('reportable_type', $listing->getMorphClass())
            ->where('reportable_id', $listing->id)
            ->where('reporter_user_id', $userId)
            ->where('status', 'open')
            ->exists();
        if ($alreadyOpen) {
            return back()->with('status', 'Je hebt deze advertentie al gerapporteerd; onze moderators bekijken het.');
        }

        Report::query()->create([
            'reportable_type' => $listing->getMorphClass(),
            'reportable_id' => $listing->id,
            'reporter_user_id' => $userId,
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'status' => 'open',
        ]);

        return back()->with('status', 'Bedankt — onze moderators bekijken dit zo snel mogelijk.');
    }
}
