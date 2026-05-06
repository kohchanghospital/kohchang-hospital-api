<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'name_th',
        'name_en',
        'order_no',
        'is_active'
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
