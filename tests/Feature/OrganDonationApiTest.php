<?php

namespace Tests\Feature;

use App\Models\OrganDonationPage;
use App\Models\User;
use Database\Seeders\OrganDonationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganDonationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrganDonationSeeder::class);
    }

    public function test_public_response_contains_only_active_items_in_order(): void
    {
        $page = OrganDonationPage::query()->firstOrFail();
        $page->organs()->create(['title' => 'ไม่แสดง', 'sort_order' => 0, 'is_active' => false]);
        $page->organs()->create(['title' => 'รายการแรก', 'sort_order' => 0, 'is_active' => true]);
        $page->qualifications()->create(['content' => 'ไม่แสดง', 'sort_order' => 0, 'is_active' => false]);

        $this->getJson('/api/organ-donation')
            ->assertOk()
            ->assertJsonPath('data.page_title', 'บริจาคอวัยวะ')
            ->assertJsonPath('data.organs.0.title', 'รายการแรก')
            ->assertJsonMissing(['title' => 'ไม่แสดง'])
            ->assertJsonMissing(['content' => 'ไม่แสดง']);
    }

    public function test_admin_endpoints_require_authentication(): void
    {
        $this->getJson('/api/admin/organ-donation')->assertUnauthorized();
        $this->putJson('/api/admin/organ-donation', [])->assertUnauthorized();
    }

    public function test_admin_can_update_page_and_synchronize_repeatable_items(): void
    {
        $this->actingAs(User::factory()->create());
        $page = OrganDonationPage::query()->with(['organs', 'qualifications'])->firstOrFail();

        $payload = [
            'eyebrow_text' => 'GIVE LIFE',
            'page_title' => 'บริจาคอวัยวะ',
            'headline' => 'หัวข้อใหม่',
            'subheadline' => 'ข้อความรอง',
            'importance_title' => 'ความสำคัญ',
            'importance_content' => 'รายละเอียดความสำคัญ',
            'qualification_title' => 'คุณสมบัติ',
            'contact_title' => 'ติดต่อ',
            'contact_description' => 'รายละเอียด',
            'phone' => '1666',
            'external_url' => 'https://example.com/register',
            'external_url_label' => 'ลงทะเบียน',
            'organs' => [
                ['id' => $page->organs[1]->id, 'title' => 'ไตแก้ไข', 'sort_order' => 1, 'is_active' => true],
                ['title' => 'กระจกตา', 'sort_order' => 2, 'is_active' => false],
            ],
            'qualifications' => [
                ['id' => $page->qualifications[0]->id, 'content' => 'คุณสมบัติใหม่', 'sort_order' => 1, 'is_active' => true],
            ],
        ];

        $this->putJson('/api/admin/organ-donation', $payload)
            ->assertOk()
            ->assertJsonPath('data.headline', 'หัวข้อใหม่')
            ->assertJsonCount(2, 'data.organs')
            ->assertJsonCount(1, 'data.qualifications')
            ->assertJsonPath('data.organs.0.title', 'ไตแก้ไข')
            ->assertJsonPath('data.organs.1.is_active', false);

        $this->assertDatabaseCount('organ_donation_organs', 2);
        $this->assertDatabaseCount('organ_donation_qualifications', 1);
    }

    public function test_admin_update_validates_nested_content(): void
    {
        $this->actingAs(User::factory()->create());

        $this->putJson('/api/admin/organ-donation', [
            'eyebrow_text' => '',
            'organs' => [['title' => '', 'sort_order' => 0, 'is_active' => true]],
            'qualifications' => [['content' => '', 'sort_order' => 0, 'is_active' => true]],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'eyebrow_text', 'page_title', 'headline', 'importance_title', 'importance_content',
            'qualification_title', 'organs.0.title', 'organs.0.sort_order',
            'qualifications.0.content', 'qualifications.0.sort_order',
        ]);
    }
}
