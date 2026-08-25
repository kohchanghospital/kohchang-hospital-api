<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityApiTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'activity_date' => '2026-01-15',
            'start_time' => '08:30',
            'end_time' => '16:30',
            'title' => 'ตรวจเยี่ยมหน่วย',
            'details' => ['รายละเอียดแรก', '', 'รายละเอียดที่สอง'],
            'note' => 'ไม่มี',
        ], $overrides);
    }

    public function test_public_can_list_and_read_activities_but_cannot_mutate_them(): void
    {
        $activity = Activity::create($this->payload(['details' => []]));

        $this->getJson('/api/activities')->assertOk();
        $this->getJson("/api/activities/{$activity->id}")->assertOk();
        $this->postJson('/api/activities', $this->payload())->assertUnauthorized();
        $this->putJson("/api/activities/{$activity->id}", $this->payload())->assertUnauthorized();
        $this->deleteJson("/api/activities/{$activity->id}")->assertUnauthorized();
    }

    public function test_admin_crud_preserves_detail_order_and_cascades_delete(): void
    {
        $this->actingAs(User::factory()->create());

        $created = $this->postJson('/api/activities', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.activity_date', '2026-01-15')
            ->assertJsonCount(2, 'data.details')
            ->assertJsonPath('data.details.0.sort_order', 1)
            ->assertJsonPath('data.details.1.sort_order', 2)
            ->json('data');

        $this->assertDatabaseCount('activities', 1);
        $this->assertDatabaseCount('activity_details', 2);

        $this->putJson("/api/activities/{$created['id']}", $this->payload([
            'title' => 'แก้ไขแล้ว',
            'details' => ['รายการใหม่'],
        ]))->assertOk()->assertJsonPath('data.title', 'แก้ไขแล้ว')->assertJsonCount(1, 'data.details');

        $this->assertDatabaseCount('activity_details', 1);
        $this->deleteJson("/api/activities/{$created['id']}")->assertOk();
        $this->assertDatabaseCount('activities', 0);
        $this->assertDatabaseCount('activity_details', 0);
    }

    public function test_validates_required_fields_and_time_order(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/activities', $this->payload([
            'activity_date' => '',
            'title' => '',
            'start_time' => '16:30',
            'end_time' => '08:30',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['activity_date', 'title', 'end_time']);
    }

    public function test_visible_range_uses_an_exclusive_end_date(): void
    {
        Activity::create($this->payload(['activity_date' => '2026-01-15', 'details' => []]));
        Activity::create($this->payload(['activity_date' => '2026-02-01', 'details' => []]));

        $this->getJson('/api/activities?start=2026-01-01&end=2026-02-01')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.activity_date', '2026-01-15');
    }
}
