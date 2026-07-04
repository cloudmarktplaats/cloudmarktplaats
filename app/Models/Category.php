<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The `path` column is a raw Postgres ltree (added via DB::statement in the
 * migration), so Larastan can't infer it from the schema — declare it here.
 *
 * @property string $path
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'is_active',
        'path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Builder<static>
     */
    public static function descendantsOf(string $path): Builder
    {
        return static::query()->whereRaw('path <@ ?::ltree', [$path]);
    }

    /**
     * @return Builder<static>
     */
    public static function ancestorsOf(string $path): Builder
    {
        return static::query()->whereRaw('path @> ?::ltree', [$path]);
    }
}
