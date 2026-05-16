<?php

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates nested categories under parent path', function () {
    Category::create(['name' => 'Servers', 'slug' => 'servers', 'path' => 'servers']);
    Category::create(['name' => 'Rack', 'slug' => 'rack', 'path' => 'servers.rack']);

    expect(Category::descendantsOf('servers')->pluck('slug')->all())->toContain('rack');
});
