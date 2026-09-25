<?php

namespace Tests;

use App\Models\User;
use App\Models\UserTwoFactor;
use App\Services\Totp;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsAdmin(User $user): static
    {
        UserTwoFactor::firstOrCreate(['user_id' => $user->id], [
            'two_factor_enabled' => true,
            'two_factor_secret' => app(Totp::class)->secret(),
            'two_factor_confirmed_at' => now(),
        ]);
        $this->withSession(['2fa_verified' => true, '2fa_verified_user_id' => $user->id]);
        $this->withHeader('Origin', 'http://localhost:5173');
        return $this->actingAs($user, 'web');
    }
}
