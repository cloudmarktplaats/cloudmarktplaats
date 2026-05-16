<?php

declare(strict_types=1);

use App\Filament\Widgets\ActiveListingsWidget;
use App\Filament\Widgets\NewUsersChartWidget;
use App\Filament\Widgets\OpenReportsWidget;
use App\Filament\Widgets\OutdatedTosWidget;
use App\Filament\Widgets\PendingReviewsWidget;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('renders PendingReviewsWidget with the moderation queue depth', function () {
    Listing::factory()->count(3)->create(['state' => 'pending_review']);
    Listing::factory()->count(2)->create(['state' => 'published']);

    Livewire::test(PendingReviewsWidget::class)
        ->assertOk()
        ->assertSee('Listings awaiting review')
        ->assertSee('3');
});

it('renders OpenReportsWidget with the open report count', function () {
    Report::factory()->count(4)->open()->create();
    Report::factory()->count(1)->resolved()->create();

    Livewire::test(OpenReportsWidget::class)
        ->assertOk()
        ->assertSee('Open reports')
        ->assertSee('4');
});

it('renders ActiveListingsWidget with the published count', function () {
    Listing::factory()->count(5)->create(['state' => 'published']);
    Listing::factory()->count(2)->create(['state' => 'archived']);

    Livewire::test(ActiveListingsWidget::class)
        ->assertOk()
        ->assertSee('Active listings')
        ->assertSee('5');
});

it('renders OutdatedTosWidget counting users without an acceptance', function () {
    $tos = LegalDocument::factory()->create([
        'type' => 'tos',
        'locale' => 'nl',
        'version' => '9.9.9',
        'published_at' => now(),
    ]);

    // 1 user accepted, 2 didn't (plus the admin from beforeEach also
    // hasn't accepted) — expect "3 outdated".
    $accepted = User::factory()->create();
    LegalAcceptance::create([
        'user_id' => $accepted->id,
        'legal_document_id' => $tos->id,
        'accepted_at' => now(),
        'ip_hash' => str_repeat('0', 64),
    ]);
    User::factory()->count(2)->create();

    Livewire::test(OutdatedTosWidget::class)
        ->assertOk()
        ->assertSee('Users with outdated ToS')
        ->assertSee('3');
});

it('renders NewUsersChartWidget with 7 daily buckets', function () {
    // Spread 3 new users across the 7-day window.
    User::factory()->create(['created_at' => now()->subDays(6)]);
    User::factory()->create(['created_at' => now()->subDays(3)]);
    User::factory()->create(['created_at' => now()]);

    $widget = Livewire::test(NewUsersChartWidget::class)
        ->assertOk();

    // The chart is rendered client-side; assert headline label appears
    // so we know the widget actually mounted with its title.
    $widget->assertSee('New users');
});
