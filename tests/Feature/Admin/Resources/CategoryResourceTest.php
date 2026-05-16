<?php

declare(strict_types=1);

use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Models\Category;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('renders the categories list for admins', function () {
    Category::factory()->count(3)->create();

    Livewire::test(ListCategories::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Category::all());
});

it('creates a new category through the create form', function () {
    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => 'GPUs',
            'slug' => 'gpus',
            'path' => 'gpus',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Category::query()->where('slug', 'gpus')->exists())->toBeTrue();
});
