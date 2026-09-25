<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WebsiteAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-analytics-ingest-key-with-at-least-32-characters';
    private const VISITOR = '4a5b5791-52fa-4afb-aeb6-6b48a6491ae2';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('analytics.ingest_key', self::KEY);
        config()->set('analytics.timezone', 'Asia/Bangkok');
    }

    private function heartbeat(string $uuid = self::VISITOR)
    {
        return $this->withHeader('X-Analytics-Key', self::KEY)
            ->postJson('/api/analytics/heartbeat', ['visitor_uuid' => $uuid]);
    }

    public function test_heartbeat_requires_server_key_and_valid_anonymous_id(): void
    {
        $this->postJson('/api/analytics/heartbeat', ['visitor_uuid' => self::VISITOR])->assertForbidden();
        $this->withHeader('X-Analytics-Key', self::KEY)
            ->postJson('/api/analytics/heartbeat', ['visitor_uuid' => 'not-a-uuid'])->assertUnprocessable();
        $this->assertDatabaseCount('website_visitors', 0);
    }

    public function test_repeated_activity_and_multiple_visitors_count_correctly(): void
    {
        $this->travelTo(Carbon::parse('2026-09-25 03:00:00', 'UTC'));
        $this->heartbeat()->assertNoContent();
        $this->heartbeat()->assertNoContent();
        $this->heartbeat('741c46fb-f783-45de-a841-69bf50ad8c50')->assertNoContent();
        $this->assertDatabaseCount('website_visitors', 2);
        $this->assertDatabaseCount('website_visitor_days', 2);
        $this->actingAsAdmin(User::factory()->create());
        $this->getJson('/api/admin/analytics/summary')->assertOk()
            ->assertJsonPath('total_visitors', 2)
            ->assertJsonPath('online_visitors', 2)
            ->assertJsonPath('today_visitors', 2);
        $this->travel(6)->minutes();
        $this->getJson('/api/admin/analytics/summary')->assertJsonPath('online_visitors', 0)
            ->assertJsonPath('total_visitors', 2);
    }

    public function test_returning_visitor_counts_on_new_bangkok_day_without_new_total(): void
    {
        $this->travelTo(Carbon::parse('2026-09-24 16:59:00', 'UTC'));
        $this->heartbeat()->assertNoContent();
        $this->assertDatabaseHas('website_visitor_days', ['visit_date' => '2026-09-24']);
        $this->travelTo(Carbon::parse('2026-09-24 17:01:00', 'UTC'));
        $this->heartbeat()->assertNoContent();
        $this->heartbeat()->assertNoContent();
        $this->assertDatabaseCount('website_visitors', 1);
        $this->assertDatabaseCount('website_visitor_days', 2);
        $this->actingAsAdmin(User::factory()->create());
        $this->getJson('/api/admin/analytics/summary')->assertJsonPath('total_visitors', 1)
            ->assertJsonPath('today_visitors', 1);
    }

    public function test_summary_requires_admin_session_and_verified_two_factor(): void
    {
        $this->getJson('/api/admin/analytics/summary')->assertUnauthorized();
        $user = User::factory()->create();
        $this->actingAs($user, 'web')->getJson('/api/admin/analytics/summary')->assertUnauthorized();
        $this->actingAsAdmin($user)->getJson('/api/admin/analytics/summary')->assertOk();
    }
}
