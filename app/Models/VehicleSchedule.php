<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleSchedule extends Model
{
    protected $fillable = ['schedule_date', 'start_time', 'end_time', 'driver_id', 'vehicle_id', 'title', 'note'];
    protected function casts(): array { return ['schedule_date' => 'date:Y-m-d']; }
    public function driver(): BelongsTo { return $this->belongsTo(Driver::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function details(): HasMany { return $this->hasMany(VehicleScheduleDetail::class)->orderBy('sort_order'); }
}
