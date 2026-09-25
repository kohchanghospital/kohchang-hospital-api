<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTwoFactor extends Model
{
    protected $table = 'user_two_factor';

    protected $guarded = [];

    protected $hidden = ['two_factor_secret', 'pending_secret', 'last_used_counter'];

    protected function casts(): array
    {
        return [
            'two_factor_enabled' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'pending_secret' => 'encrypted',
            'pending_created_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
