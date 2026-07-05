<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WaitlistEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A prospective member who arrived after the founding cohort filled up.
 * No account, no password — just an email to invite once a slot opens.
 */
class WaitlistEntry extends Model
{
    /** @use HasFactory<WaitlistEntryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['email', 'invited'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['invited' => 'boolean'];
    }
}
