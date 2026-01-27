<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    protected $fillable = [
        'slug',
        'type'
    ];

    public function translations()
    {
        return $this->hasMany(ContentTranslation::class);
    }

    public function translation($lang = 'th')
    {
        return $this->hasOne(ContentTranslation::class)->where('lang', $lang);
    }
}
