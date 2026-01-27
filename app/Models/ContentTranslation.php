<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentTranslation extends Model
{
    protected $fillable = [
        'content_id',
        'lang',
        'title',
        'body',
    ];

    public function content()
    {
        return $this->belongsTo(Content::class);
    }
}