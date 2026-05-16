<?php

namespace App\Models;

use Database\Factories\LegalDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegalDocument extends Model
{
    /** @use HasFactory<LegalDocumentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'type',
        'version',
        'locale',
        'markdown_content',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public static function current(string $type, string $locale): ?self
    {
        return static::query()
            ->where('type', $type)
            ->where('locale', $locale)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->first();
    }
}
