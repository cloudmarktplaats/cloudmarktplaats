<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('matches full-text search on title and description', function () {
    $user = User::factory()->create();
    $cat = Category::factory()->create(['path' => 'servers']);
    Listing::factory()->for($user)->for($cat)->create([
        'title' => 'Dell PowerEdge R720 server',
        'description' => 'Refurbished dual Xeon E5-2650, 64GB RAM',
        'state' => 'published',
    ]);

    $hits = DB::select(
        "SELECT id FROM listings WHERE search_vector @@ plainto_tsquery('dutch', ?)",
        ['poweredge']
    );

    expect($hits)->toHaveCount(1);
});
