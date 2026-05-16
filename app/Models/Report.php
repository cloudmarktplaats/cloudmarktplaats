<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Report extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'reportable_type',
        'reportable_id',
        'reporter_user_id',
        'reason',
        'details',
        'status',
        'resolved_by_user_id',
        'resolution_note',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }
}
