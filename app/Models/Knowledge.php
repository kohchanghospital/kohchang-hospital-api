<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Knowledge extends Model
{
    protected $fillable = [
        'title',
        'announce_date',
        'pdf_name',
        'file_path',
        'created_by',
    ];

    protected $casts = [
        'announce_date' => 'date',
    ];
}
