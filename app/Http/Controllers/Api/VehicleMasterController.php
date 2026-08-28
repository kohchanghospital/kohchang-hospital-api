<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VehicleMasterController extends Controller
{
    public function drivers(): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->activeDrivers()->get()]);
    }

    public function vehicles(): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->activeVehicles()->get()]);
    }

    public function storeDriver(Request $request): JsonResponse
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $name = trim($validated['name']);
        abort_if($name === '', 422, 'กรุณากรอกชื่อพนักงานขับรถ');

        $driver = Driver::create([
            'name' => $name,
            'sort_order' => (int) Driver::max('sort_order') + 1,
            'is_active' => true,
        ]);

        return response()->json(['status' => true, 'message' => 'เพิ่มพนักงานขับรถเรียบร้อย', 'data' => $driver], 201);
    }

    public function updateDriver(Request $request, Driver $driver): JsonResponse
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $name = trim($validated['name']);
        abort_if($name === '', 422, 'กรุณากรอกชื่อพนักงานขับรถ');
        $driver->update(['name' => $name]);

        return response()->json(['status' => true, 'message' => 'แก้ไขพนักงานขับรถเรียบร้อย', 'data' => $driver->refresh()]);
    }

    public function destroyDriver(Driver $driver): JsonResponse
    {
        if ($driver->schedules()->exists()) {
            return response()->json(['message' => 'ไม่สามารถลบพนักงานขับรถที่มีแผนการใช้รถอยู่ได้'], 422);
        }
        $driver->delete();
        $this->normalizeOrder(Driver::query());

        return response()->json(['status' => true, 'message' => 'ลบพนักงานขับรถเรียบร้อย']);
    }

    public function reorderDrivers(Request $request): JsonResponse
    {
        $ids = $this->validateOrder($request, 'drivers');
        $this->persistOrder(Driver::query(), $ids);

        return response()->json(['status' => true, 'message' => 'จัดลำดับพนักงานขับรถเรียบร้อย', 'data' => $this->activeDrivers()->get()]);
    }

    public function storeVehicle(Request $request): JsonResponse
    {
        $request->merge(['registration_number' => trim((string) $request->input('registration_number'))]);
        $validated = $request->validate([
            'registration_number' => ['required', 'string', 'max:255', Rule::unique('vehicles', 'registration_number')],
        ]);
        $registration = trim($validated['registration_number']);
        abort_if($registration === '', 422, 'กรุณากรอกทะเบียนรถ');

        $vehicle = Vehicle::create([
            'registration_number' => $registration,
            'sort_order' => (int) Vehicle::max('sort_order') + 1,
            'is_active' => true,
        ]);

        return response()->json(['status' => true, 'message' => 'เพิ่มทะเบียนรถเรียบร้อย', 'data' => $vehicle], 201);
    }

    public function updateVehicle(Request $request, Vehicle $vehicle): JsonResponse
    {
        $request->merge(['registration_number' => trim((string) $request->input('registration_number'))]);
        $validated = $request->validate([
            'registration_number' => ['required', 'string', 'max:255', Rule::unique('vehicles', 'registration_number')->ignore($vehicle)],
        ]);
        $registration = trim($validated['registration_number']);
        abort_if($registration === '', 422, 'กรุณากรอกทะเบียนรถ');
        $vehicle->update(['registration_number' => $registration]);

        return response()->json(['status' => true, 'message' => 'แก้ไขทะเบียนรถเรียบร้อย', 'data' => $vehicle->refresh()]);
    }

    public function destroyVehicle(Vehicle $vehicle): JsonResponse
    {
        if ($vehicle->schedules()->exists()) {
            return response()->json(['message' => 'ไม่สามารถลบทะเบียนรถที่มีแผนการใช้รถอยู่ได้'], 422);
        }
        $vehicle->delete();
        $this->normalizeOrder(Vehicle::query());

        return response()->json(['status' => true, 'message' => 'ลบทะเบียนรถเรียบร้อย']);
    }

    public function reorderVehicles(Request $request): JsonResponse
    {
        $ids = $this->validateOrder($request, 'vehicles');
        $this->persistOrder(Vehicle::query(), $ids);

        return response()->json(['status' => true, 'message' => 'จัดลำดับทะเบียนรถเรียบร้อย', 'data' => $this->activeVehicles()->get()]);
    }

    private function activeDrivers(): Builder
    {
        return Driver::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    private function activeVehicles(): Builder
    {
        return Vehicle::query()->where('is_active', true)->orderBy('sort_order')->orderBy('registration_number');
    }

    private function validateOrder(Request $request, string $table): array
    {
        return $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'distinct', Rule::exists($table, 'id')],
        ])['ids'];
    }

    private function persistOrder(Builder $query, array $ids): void
    {
        DB::transaction(function () use ($query, $ids) {
            foreach ($ids as $index => $id) {
                (clone $query)->whereKey($id)->update(['sort_order' => $index + 1]);
            }
        });
    }

    private function normalizeOrder(Builder $query): void
    {
        $ids = (clone $query)->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
        $this->persistOrder($query, $ids);
    }
}
