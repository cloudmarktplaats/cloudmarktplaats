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

        Report::query()->create([
            'reportable_type' => Listing::class,
            'reportable_id' => $listing->id,
            'reporter_user_id' => $userId,
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'status' => 'open',
        ]);

        return back()->with('status', 'Bedankt — onze moderators bekijken dit zo snel mogelijk.');
    }
}
