<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Services\Search\PostgresSearchService;
use App\Services\Search\SearchInterface;

it('binds SearchInterface to PostgresSearchService', function () {
    expect(app(SearchInterface::class))->toBeInstanceOf(PostgresSearchService::class);
});

it('returns published listings matching the query, ranked by ts_rank', function () {
    Listing::factory()->published()->create([
        'title' => 'Sun SPARCstation 20 workstation',
        'description' => 'Vintage UNIX hardware in working state.',
    ]);
    Listing::factory()->published()->create([
        'title' => 'Cisco Catalyst 2960 switch',
        'description' => 'Network switch with 24 ports.',
    ]);
    // Draft shouldn't show even if it matches
    Listing::factory()->create([
        'state' => 'draft',
        'title' => 'Sun draft listing',
        'description' => 'Hidden.',
    ]);

    $svc = app(SearchInterface::class);
    $results = $svc->listings('sun')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toContain('Sun SPARCstation');
});

it('GET /search returns 200 with a results listing', function () {
    Listing::factory()->published()->create([
        'title' => 'Mac mini M1',
        'description' => 'Refurbished and ready to ship.',
    ]);

    $this->get('/search?q=mac')
        ->assertOk()
        ->assertSee('Mac mini M1');
});
