<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleScheduleDetail extends Model
{
    protected $fillable = ['detail_text', 'sort_order'];
    public function schedule(): BelongsTo { return $this->belongsTo(VehicleSchedule::class, 'vehicle_schedule_id'); }
}
