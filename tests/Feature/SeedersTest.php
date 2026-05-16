<?php

use App\Models\Category;
use App\Models\LegalDocument;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\LegalDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds top-level categories from spec', function () {
    $this->seed(CategorySeeder::class);

    expect(Category::whereRaw("path = 'servers'")->exists())->toBeTrue();
    expect(Category::whereRaw("path = 'networking'")->exists())->toBeTrue();
    expect(Category::count())->toBeGreaterThanOrEqual(12);
});

it('seeds legal documents in nl + en', function () {
    $this->seed(LegalDocumentSeeder::class);

    expect(LegalDocument::current('tos', 'nl'))->not->toBeNull();
    expect(LegalDocument::current('privacy', 'nl'))->not->toBeNull();
});

it('creates demo admin and user', function () {
    $this->seed(DemoUserSeeder::class);

    expect(User::where('email', 'admin@example.local')->first()?->role)->toBe('admin');
    expect(User::where('email', 'user@example.local')->first()?->role)->toBe('user');
});
