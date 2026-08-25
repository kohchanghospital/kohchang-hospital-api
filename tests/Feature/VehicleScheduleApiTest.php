<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    private Driver $driver;
    private Driver $otherDriver;
    private Vehicle $vehicle;
    private Vehicle $otherVehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = Driver::create(['name' => 'นายสมชาย ใจดี']);
        $this->otherDriver = Driver::create(['name' => 'นายวิชัย พร้อมเดินทาง']);
        $this->vehicle = Vehicle::create(['registration_number' => 'กข 1234 ตราด']);
        $this->otherVehicle = Vehicle::create(['registration_number' => 'นข 5678 ตราด']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'schedule_date' => '2026-08-24',
            'start_time' => '08:30',
            'end_time' => '16:30',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'title' => 'รับผู้ป่วยส่งต่อ',
            'details' => ['ออกจากโรงพยาบาลเกาะช้าง', '', 'รับผู้ป่วยที่ รพ.ตราด', 'เดินทางกลับโรงพยาบาลเกาะช้าง'],
            'note' => 'เตรียมอุปกรณ์ฉุกเฉิน',
        ], $overrides);
    }

    public function test_public_reads_schedules_but_mutations_and_masters_require_authentication(): void
    {
        $schedule = VehicleSchedule::create($this->payload(['details' => []]));
        $this->getJson('/api/vehicle-schedules')->assertOk();
        $this->getJson("/api/vehicle-schedules/{$schedule->id}")->assertOk();
        $this->getJson('/api/drivers')->assertUnauthorized();
        $this->getJson('/api/vehicles')->assertUnauthorized();
        $this->postJson('/api/vehicle-schedules', $this->payload())->assertUnauthorized();
    }

    public function test_admin_crud_uses_relations_ordered_details_and_cascade_delete(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/drivers')->assertOk()->assertJsonFragment(['name' => 'นายสมชาย ใจดี']);
        $this->getJson('/api/vehicles')->assertOk()->assertJsonFragment(['registration_number' => 'กข 1234 ตราด']);

        $created = $this->postJson('/api/vehicle-schedules', $this->payload())
            ->assertCreated()->assertJsonCount(3, 'data.details')
            ->assertJsonPath('data.details.0.sort_order', 1)
            ->assertJsonPath('data.driver.name', 'นายสมชาย ใจดี')
            ->assertJsonPath('data.vehicle.registration_number', 'กข 1234 ตราด')->json('data');

        $this->putJson("/api/vehicle-schedules/{$created['id']}", $this->payload([
            'title' => 'แก้ไขแล้ว', 'details' => ['รายการเดียว'],
        ]))->assertOk()->assertJsonCount(1, 'data.details')->assertJsonPath('data.title', 'แก้ไขแล้ว');

        $this->assertDatabaseCount('vehicle_schedule_details', 1);
        $this->deleteJson("/api/vehicle-schedules/{$created['id']}")->assertOk();
        $this->assertDatabaseCount('vehicle_schedules', 0);
        $this->assertDatabaseCount('vehicle_schedule_details', 0);
    }

    public function test_rejects_vehicle_and_driver_overlaps_but_allows_touching_boundaries(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/vehicle-schedules', $this->payload())->assertCreated();

        $this->postJson('/api/vehicle-schedules', $this->payload([
            'driver_id' => $this->otherDriver->id, 'start_time' => '10:00', 'end_time' => '12:00',
        ]))->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');

        $this->postJson('/api/vehicle-schedules', $this->payload([
            'vehicle_id' => $this->otherVehicle->id, 'start_time' => '10:00', 'end_time' => '12:00',
        ]))->assertUnprocessable()->assertJsonValidationErrors('driver_id');

        $this->postJson('/api/vehicle-schedules', $this->payload([
            'driver_id' => $this->otherDriver->id,
            'vehicle_id' => $this->otherVehicle->id,
            'start_time' => '16:30',
            'end_time' => '17:30',
        ]))->assertCreated();
    }

    public function test_validates_required_fields_time_order_and_visible_range(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/vehicle-schedules', $this->payload([
            'schedule_date' => '', 'driver_id' => null, 'vehicle_id' => null,
            'title' => '', 'start_time' => '16:30', 'end_time' => '08:30',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['schedule_date', 'driver_id', 'vehicle_id', 'title', 'end_time']);

        VehicleSchedule::create($this->payload(['details' => []]));
        VehicleSchedule::create($this->payload([
            'schedule_date' => '2026-09-06', 'driver_id' => $this->otherDriver->id,
            'vehicle_id' => $this->otherVehicle->id, 'details' => [],
        ]));
        $this->getJson('/api/vehicle-schedules?start=2026-07-26&end=2026-09-06')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.schedule_date', '2026-08-24');
    }
}
