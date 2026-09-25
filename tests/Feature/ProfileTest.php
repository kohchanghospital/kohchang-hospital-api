<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_is_self_only_and_requires_verified_two_factor(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->getJson('/api/profile')->assertUnauthorized();
        $this->actingAsAdmin($user);
        $this->getJson('/api/profile')->assertOk()->assertJsonPath('id', $user->id);
        $this->putJson('/api/profile', [
            'user_id' => $other->id, 'username' => 'my_new_name', 'name' => 'Changed',
            'email' => $user->email, 'current_password' => 'password',
        ])->assertOk()->assertJsonPath('id', $user->id);
        $this->assertSame('my_new_name', $user->fresh()->username);
        $this->assertNotSame('Changed', $other->fresh()->name);
    }

    public function test_username_is_unique_and_password_is_required_for_change(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['username' => 'taken']);
        $this->actingAsAdmin($user);
        $this->putJson('/api/profile', ['username' => 'TAKEN', 'name' => $user->name, 'email' => $user->email,
            'current_password' => 'password'])->assertUnprocessable();
        $this->putJson('/api/profile', ['username' => 'another', 'name' => $user->name, 'email' => $user->email,
            'current_password' => 'wrong'])->assertUnprocessable();
        $this->assertSame($user->username, $user->fresh()->username);
    }

    public function test_changed_username_replaces_old_login_identifier(): void
    {
        $user = User::factory()->create(['username' => 'old_name']);
        $this->actingAsAdmin($user);
        $this->putJson('/api/profile', ['username' => 'New_Name', 'name' => $user->name,
            'email' => $user->email, 'current_password' => 'password'])->assertOk()
            ->assertJsonPath('username', 'new_name');
        $this->postJson('/logout')->assertNoContent();
        $this->postJson('/login', ['username' => 'old_name', 'password' => 'password'])
            ->assertUnprocessable()->assertJsonPath('message', 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
        $this->postJson('/login', ['username' => ' NEW_NAME ', 'password' => 'password'])
            ->assertOk()->assertJsonPath('next', '/2fa/verify');
    }

    public function test_password_change_requires_current_password_and_ends_session(): void
    {
        $user = User::factory()->create();
        $this->actingAsAdmin($user);
        $this->putJson('/api/profile/password', ['current_password' => 'wrong',
            'password' => 'NewSecurePassword12!', 'password_confirmation' => 'NewSecurePassword12!'])->assertUnprocessable();
        $this->putJson('/api/profile/password', ['current_password' => 'password',
            'password' => 'NewSecurePassword12!', 'password_confirmation' => 'NewSecurePassword12!'])->assertOk();
        $this->assertTrue(Hash::check('NewSecurePassword12!', $user->fresh()->password));
        $this->getJson('/api/profile')->assertUnauthorized();
    }
}
