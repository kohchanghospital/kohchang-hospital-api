<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    protected $fillable = ['name', 'sort_order', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function schedules(): HasMany { return $this->hasMany(VehicleSchedule::class); }
}
