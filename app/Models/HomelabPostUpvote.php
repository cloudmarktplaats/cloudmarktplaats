<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HomelabPostUpvoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomelabPostUpvote extends Model
{
    /** @use HasFactory<HomelabPostUpvoteFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['user_id', 'homelab_post_id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<HomelabPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(HomelabPost::class, 'homelab_post_id');
    }
}
