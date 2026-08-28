<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsitePolicy;
use Database\Seeders\WebsitePolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsitePolicyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WebsitePolicySeeder::class);
    }

    public function test_seeder_creates_each_policy_once_without_overwriting_content(): void
    {
        $policy = WebsitePolicy::query()->where('policy_type', 'privacy_policy')->firstOrFail();
        $policy->update(['content_th' => '<p>เนื้อหาจริง</p>']);
        $this->seed(WebsitePolicySeeder::class);
        $this->assertDatabaseCount('website_policies', 3);
        $this->assertSame('<p>เนื้อหาจริง</p>', $policy->fresh()->content_th);
    }

    public function test_public_can_only_read_active_policies(): void
    {
        WebsitePolicy::query()->where('policy_type', 'cookie_policy')->update(['is_active' => false]);
        $this->getJson('/api/policies')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/policies/privacy_policy')->assertOk()
            ->assertJsonPath('data.policy_type', 'privacy_policy')
            ->assertJsonMissingPath('data.updated_by');
        $this->getJson('/api/policies/cookie_policy')->assertNotFound();
    }

    public function test_admin_endpoints_require_authentication(): void
    {
        $this->getJson('/api/admin/policies')->assertUnauthorized();
        $this->putJson('/api/admin/policies/privacy_policy', [])->assertUnauthorized();
    }

    public function test_admin_update_sanitizes_html_and_updates_existing_record(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->putJson('/api/admin/policies/privacy_policy', [
            'title_th' => 'นโยบายฉบับปรับปรุง', 'title_en' => 'Updated Privacy Policy',
            'content_th' => '<script>alert(1)</script><h2>หัวข้อ</h2><p><strong>ข้อความ</strong></p>',
            'content_en' => '<p>English content</p>', 'is_active' => true,
        ])->assertOk()->assertJsonPath('data.updated_by', $user->id);

        $this->assertDatabaseCount('website_policies', 3);
        $policy = WebsitePolicy::query()->where('policy_type', 'privacy_policy')->firstOrFail();
        $this->assertStringNotContainsString('<script', $policy->content_th);
        $this->assertStringContainsString('<h2>หัวข้อ</h2>', $policy->content_th);
    }
}
