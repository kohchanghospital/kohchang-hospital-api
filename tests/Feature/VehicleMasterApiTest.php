<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleMasterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_crud_requires_authentication(): void
    {
        $driver = Driver::create(['name' => 'คนขับ']);
        $vehicle = Vehicle::create(['registration_number' => 'กข 1234']);

        $this->postJson('/api/drivers', ['name' => 'ใหม่'])->assertUnauthorized();
        $this->putJson("/api/drivers/{$driver->id}", ['name' => 'แก้ไข'])->assertUnauthorized();
        $this->deleteJson("/api/drivers/{$driver->id}")->assertUnauthorized();
        $this->postJson('/api/vehicles', ['registration_number' => 'ขค 5678'])->assertUnauthorized();
        $this->deleteJson("/api/vehicles/{$vehicle->id}")->assertUnauthorized();
    }

    public function test_admin_can_create_edit_delete_and_reorder_drivers(): void
    {
        $this->actingAs(User::factory()->create());
        $first = $this->postJson('/api/drivers', ['name' => '  คนขับหนึ่ง  '])->assertCreated()->assertJsonPath('data.name', 'คนขับหนึ่ง')->json('data');
        $second = $this->postJson('/api/drivers', ['name' => 'คนขับสอง'])->assertCreated()->json('data');

        $this->putJson("/api/drivers/{$first['id']}", ['name' => '  คนขับแก้ไข  '])->assertOk()->assertJsonPath('data.name', 'คนขับแก้ไข');
        $this->postJson('/api/drivers/reorder', ['ids' => [$second['id'], $first['id']]])->assertOk()->assertJsonPath('data.0.id', $second['id']);
        $this->deleteJson("/api/drivers/{$first['id']}")->assertOk();
        $this->assertDatabaseMissing('drivers', ['id' => $first['id']]);
        $this->postJson('/api/drivers', ['name' => '   '])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_admin_can_create_edit_delete_and_reorder_vehicles(): void
    {
        $this->actingAs(User::factory()->create());
        $first = $this->postJson('/api/vehicles', ['registration_number' => '  กข 1234 ตราด  '])->assertCreated()->assertJsonPath('data.registration_number', 'กข 1234 ตราด')->json('data');
        $second = $this->postJson('/api/vehicles', ['registration_number' => 'นข 5678 ตราด'])->assertCreated()->json('data');

        $this->putJson("/api/vehicles/{$first['id']}", ['registration_number' => '  กข 9999 ตราด  '])->assertOk()->assertJsonPath('data.registration_number', 'กข 9999 ตราด');
        $this->postJson('/api/vehicles/reorder', ['ids' => [$second['id'], $first['id']]])->assertOk()->assertJsonPath('data.0.id', $second['id']);
        $this->deleteJson("/api/vehicles/{$first['id']}")->assertOk();
        $this->assertDatabaseMissing('vehicles', ['id' => $first['id']]);
        $this->postJson('/api/vehicles', ['registration_number' => '   '])->assertUnprocessable()->assertJsonValidationErrors('registration_number');
    }

    public function test_referenced_driver_and_vehicle_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $driver = Driver::create(['name' => 'คนขับ']);
        $vehicle = Vehicle::create(['registration_number' => 'กข 1234']);
        VehicleSchedule::create([
            'schedule_date' => '2026-08-25', 'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id,
            'title' => 'เดินทาง',
        ]);

        $this->deleteJson("/api/drivers/{$driver->id}")->assertUnprocessable();
        $this->deleteJson("/api/vehicles/{$vehicle->id}")->assertUnprocessable();
        $this->assertDatabaseHas('drivers', ['id' => $driver->id]);
        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id]);
    }
}
