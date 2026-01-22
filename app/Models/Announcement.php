<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'type_id',
        'announce_date',
        'pdf_name',
        'file_path',
        'created_by',
    ];

    protected $casts = [
        'announce_date' => 'date',
    ];

    public function type()
    {
        return $this->belongsTo(AnnouncementType::class, 'type_id');
    }
}
