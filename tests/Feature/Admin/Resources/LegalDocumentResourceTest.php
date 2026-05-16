<?php

declare(strict_types=1);

use App\Filament\Resources\LegalDocumentResource\Pages\ListLegalDocuments;
use App\Models\AdminAction;
use App\Models\LegalDocument;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('renders the legal documents list for admins', function () {
    LegalDocument::factory()->count(2)->create();

    Livewire::test(ListLegalDocuments::class)
        ->assertOk()
        ->assertCanSeeTableRecords(LegalDocument::all());
});

it('publishes a new version of an existing document', function () {
    $doc = LegalDocument::factory()->create([
        'type' => 'tos',
        'locale' => 'nl',
        'version' => '1.0.0',
        'published_at' => now()->subWeek(),
    ]);

    Livewire::test(ListLegalDocuments::class)
        ->callTableAction('publish_new_version', $doc, data: [
            'version' => '1.1.0',
            'markdown_content' => '# Updated ToS',
        ])
        ->assertHasNoTableActionErrors();

    expect(LegalDocument::query()
        ->where('type', 'tos')
        ->where('locale', 'nl')
        ->where('version', '1.1.0')
        ->whereNotNull('published_at')
        ->exists()
    )->toBeTrue();

    expect(AdminAction::query()
        ->where('action', 'legal.publish')
        ->exists()
    )->toBeTrue();
});
