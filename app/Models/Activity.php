<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    protected $fillable = [
        'activity_date',
        'start_time',
        'end_time',
        'title',
        'note',
    ];

    protected function casts(): array
    {
        return ['activity_date' => 'date:Y-m-d'];
    }

    public function details(): HasMany
    {
        return $this->hasMany(ActivityDetail::class)->orderBy('sort_order');
    }
}
