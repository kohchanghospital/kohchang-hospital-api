<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityDetail extends Model
{
    protected $fillable = ['detail_text', 'sort_order'];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
