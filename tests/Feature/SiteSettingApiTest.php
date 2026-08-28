<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_read_the_mourning_ribbon_setting(): void
    {
        $this->getJson('/api/site-settings')
            ->assertOk()
            ->assertJsonPath('data.show_mourning_ribbon', true);
    }

    public function test_missing_setting_preserves_the_existing_visible_behavior(): void
    {
        SiteSetting::query()->where('key', 'show_mourning_ribbon')->delete();

        $this->getJson('/api/site-settings')
            ->assertOk()
            ->assertJsonPath('data.show_mourning_ribbon', true);
    }

    public function test_admin_endpoints_require_authentication(): void
    {
        $this->getJson('/api/admin/site-settings')->assertUnauthorized();
        $this->putJson('/api/admin/site-settings', [
            'show_mourning_ribbon' => false,
        ])->assertUnauthorized();
    }

    public function test_admin_can_disable_and_enable_the_ribbon_persistently(): void
    {
        $this->actingAs(User::factory()->create());

        $this->putJson('/api/admin/site-settings', [
            'show_mourning_ribbon' => false,
        ])->assertOk()->assertJsonPath('data.show_mourning_ribbon', false);

        $this->assertDatabaseHas('site_settings', ['key' => 'show_mourning_ribbon']);
        $this->getJson('/api/site-settings')
            ->assertOk()
            ->assertJsonPath('data.show_mourning_ribbon', false);

        $this->putJson('/api/admin/site-settings', [
            'show_mourning_ribbon' => true,
        ])->assertOk()->assertJsonPath('data.show_mourning_ribbon', true);

        $this->getJson('/api/site-settings')
            ->assertOk()
            ->assertJsonPath('data.show_mourning_ribbon', true);
    }

    public function test_admin_update_requires_a_boolean_value(): void
    {
        $this->actingAs(User::factory()->create());

        $this->putJson('/api/admin/site-settings', [
            'show_mourning_ribbon' => 'sometimes',
        ])->assertUnprocessable()->assertJsonValidationErrors('show_mourning_ribbon');
    }
}
