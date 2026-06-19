<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DonationSetting extends Model
{
    protected $fillable = [
        'bank_name',
        'account_name',
        'account_number',
        'qr_code_image',
        'email',
        'phone',
        'fax',
        'facebook',
        'organization_name',
        'description',
        'address',
        'google_map_embed_url',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];
}
