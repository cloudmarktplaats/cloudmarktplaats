<?php

use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('finds the current published version per type+locale', function () {
    LegalDocument::factory()->tos()->create(['version' => '1.0.0', 'published_at' => now()->subDay()]);
    $latest = LegalDocument::factory()->tos()->create(['version' => '1.1.0', 'published_at' => now()]);

    $found = LegalDocument::current('tos', 'nl');

    expect($found?->id)->toBe($latest->id);
});

it('records an acceptance with hashed ip', function () {
    $user = User::factory()->create();
    $doc = LegalDocument::factory()->tos()->create(['published_at' => now()]);

    LegalAcceptance::create([
        'user_id' => $user->id,
        'legal_document_id' => $doc->id,
        'accepted_at' => now(),
        'ip_hash' => hash('sha256', '127.0.0.1'.config('app.key')),
    ]);

    expect($user->legalAcceptances()->count())->toBe(1);
});
