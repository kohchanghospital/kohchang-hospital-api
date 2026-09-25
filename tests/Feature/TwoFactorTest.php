<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTwoFactor;
use App\Services\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost:5173');
    }

    private function currentCode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($secret) as $letter) $bits .= str_pad(decbin(strpos($alphabet, $letter)), 5, '0', STR_PAD_LEFT);
        $key = '';
        foreach (str_split($bits, 8) as $octet) if (strlen($octet) === 8) $key .= chr(bindec($octet));
        $counter = intdiv(time(), 30);
        $hash = hash_hmac('sha1', pack('N2', 0, $counter), $key, true);
        $offset = ord($hash[19]) & 15;
        $number = ((ord($hash[$offset]) & 127) << 24) | (ord($hash[$offset+1]) << 16) | (ord($hash[$offset+2]) << 8) | ord($hash[$offset+3]);
        return str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function test_unconfigured_user_must_complete_setup_before_admin_access(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        $this->postJson('/login', ['username' => $user->username, 'password' => 'correct-password'])
            ->assertOk()->assertJsonPath('next', '/2fa/setup');
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/two-factor/session')->assertUnauthorized();
        $this->postJson('/api/departments', ['name' => 'Bypass'])->assertUnauthorized();
        $this->postJson('/two-factor/challenge', ['code' => '000000'])->assertStatus(409);

        $setup = $this->postJson('/two-factor/setup')->assertOk()->json();
        $this->assertStringStartsWith('otpauth://totp/', $setup['otpauth_uri']);
        $this->assertFalse(UserTwoFactor::first()->two_factor_enabled);
        $this->assertNotEquals($setup['secret'], DB::table('user_two_factor')->value('pending_secret'));
        $this->postJson('/two-factor/confirm', ['code' => '000000'])->assertUnprocessable();
        $result = $this->postJson('/two-factor/confirm', ['code' => $this->currentCode($setup['secret'])])->assertOk()->json();
        $this->assertCount(8, $result['recovery_codes']);
        $this->assertTrue(UserTwoFactor::first()->two_factor_enabled);
        $this->getJson('/api/me')->assertOk();
        $this->getJson('/two-factor/session')->assertNoContent();
    }

    public function test_configured_user_needs_totp_on_every_new_login(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        $secret = app(Totp::class)->secret();
        UserTwoFactor::create(['user_id' => $user->id, 'two_factor_enabled' => true,
            'two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()]);
        $this->postJson('/login', ['username' => $user->username, 'password' => 'correct-password'])->assertJsonPath('next', '/2fa/verify');
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/two-factor/session')->assertUnauthorized();
        $this->postJson('/two-factor/challenge', ['code' => '000000'])->assertUnprocessable();
        $this->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/two-factor/challenge', ['code' => $this->currentCode($secret)])->assertOk();
        $this->getJson('/api/me')->assertOk();
        $this->postJson('/logout')->assertNoContent();
        $this->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/login', ['username' => $user->username, 'password' => 'correct-password'])->assertJsonPath('next', '/2fa/verify');
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_recovery_code_is_single_use_and_legacy_password_session_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        UserTwoFactor::create(['user_id' => $user->id, 'two_factor_enabled' => true,
            'two_factor_secret' => app(Totp::class)->secret(), 'two_factor_confirmed_at' => now()]);
        $code = 'ABCDE-12345';
        DB::table('two_factor_recovery_codes')->insert(['user_id' => $user->id,
            'code_hash' => hash_hmac('sha256', str_replace('-', '', $code), config('app.key')),
            'created_at' => now(), 'updated_at' => now()]);
        $this->postJson('/login', ['username' => $user->username, 'password' => 'correct-password'])->assertJsonPath('next', '/2fa/verify');
        $this->postJson('/two-factor/challenge', ['code' => $code])->assertOk();
        $this->postJson('/logout')->assertNoContent();
        $this->postJson('/login', ['username' => $user->username, 'password' => 'correct-password'])->assertJsonPath('next', '/2fa/verify');
        $this->postJson('/two-factor/challenge', ['code' => $code])->assertUnprocessable();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_auth_guard_alone_cannot_bypass_two_factor_middleware(): void
    {
        $user = User::factory()->create();
        UserTwoFactor::create(['user_id' => $user->id, 'two_factor_enabled' => true,
            'two_factor_secret' => app(Totp::class)->secret(), 'two_factor_confirmed_at' => now()]);
        $this->actingAs($user, 'web');
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/two-factor/session')->assertUnauthorized();
        $this->postJson('/api/departments', ['name' => 'Bypass'])->assertUnauthorized();
    }

    public function test_username_login_is_case_insensitive_trimmed_and_rejects_old_username(): void
    {
        $user = User::factory()->create(['username' => 'new_admin', 'password' => 'correct-password']);
        $this->postJson('/login', ['username' => ' old_admin ', 'password' => 'correct-password'])
            ->assertUnprocessable()->assertJsonPath('message', 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
        $this->postJson('/login', ['username' => ' NEW_ADMIN ', 'password' => 'wrong-password'])
            ->assertUnprocessable()->assertJsonPath('message', 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
        $this->postJson('/login', ['username' => ' NEW_ADMIN ', 'password' => 'correct-password'])
            ->assertOk()->assertJsonPath('next', '/2fa/setup');
        $this->getJson('/api/profile')->assertUnauthorized();
    }
}
