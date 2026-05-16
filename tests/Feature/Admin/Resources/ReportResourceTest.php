<?php

declare(strict_types=1);

use App\Filament\Resources\ReportResource\Pages\ListReports;
use App\Models\AdminAction;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('renders the reports list for admins', function () {
    Report::factory()->count(2)->open()->create();

    Livewire::test(ListReports::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Report::all());
});

it('resolves an open report and records the resolver + audit log', function () {
    $report = Report::factory()->open()->create();

    Livewire::test(ListReports::class)
        ->callTableAction('resolve', $report, data: [
            'note' => 'Reviewed, listing complies with policy.',
            'archive_listing' => false,
        ])
        ->assertHasNoTableActionErrors();

    $report->refresh();
    expect($report->status)->toBe('resolved')
        ->and($report->resolved_by_user_id)->toBe($this->admin->id)
        ->and($report->resolution_note)->toBe('Reviewed, listing complies with policy.');

    expect(AdminAction::query()
        ->where('action', 'report.resolve')
        ->where('target_id', $report->id)
        ->exists()
    )->toBeTrue();
});

it('cascades archive when resolve_with_archive is checked', function () {
    $listing = Listing::factory()->published()->create();
    $report = Report::factory()->open()->create([
        'reportable_type' => 'listing',
        'reportable_id' => $listing->id,
    ]);

    Livewire::test(ListReports::class)
        ->callTableAction('resolve', $report, data: [
            'note' => 'Confirmed stolen.',
            'archive_listing' => true,
        ])
        ->assertHasNoTableActionErrors();

    expect($listing->fresh()->state)->toBe('archived');
});
