<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Department;

class Executive extends Model
{
    protected $fillable = [
        'name_th',
        'name_en',
        'position_th',
        'position_en',
        'department_id',
        'image_path',
        'order_no',
        'is_active'
    ];

    public function executives()
    {
        return $this->hasMany(Executive::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
