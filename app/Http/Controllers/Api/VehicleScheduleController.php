<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VehicleSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VehicleScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
            'q' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = VehicleSchedule::with(['driver', 'vehicle', 'details'])
            ->orderBy('schedule_date')
            ->orderByRaw('start_time IS NULL')
            ->orderBy('start_time');

        if (!empty($validated['start'])) $query->whereDate('schedule_date', '>=', $validated['start']);
        if (!empty($validated['end'])) $query->whereDate('schedule_date', '<', $validated['end']);
        if (!empty($validated['q'])) $query->where('title', 'like', '%' . $validated['q'] . '%');

        if (isset($validated['start']) || isset($validated['end'])) {
            return response()->json(['status' => true, 'data' => $query->get()]);
        }

        $schedules = $query->paginate($validated['per_page'] ?? 10);
        return response()->json([
            'status' => true,
            'data' => $schedules->items(),
            'current_page' => $schedules->currentPage(),
            'last_page' => $schedules->lastPage(),
            'total' => $schedules->total(),
        ]);
    }

    public function show(VehicleSchedule $vehicleSchedule): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $vehicleSchedule->load(['driver', 'vehicle', 'details'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateSchedule($request);
        $schedule = DB::transaction(function () use ($validated) {
            $this->ensureAvailable($validated);
            return $this->persist(new VehicleSchedule(), $validated);
        });

        return response()->json(['status' => true, 'message' => 'เพิ่มรายการใช้รถเรียบร้อย', 'data' => $schedule], 201);
    }

    public function update(Request $request, VehicleSchedule $vehicleSchedule): JsonResponse
    {
        $validated = $this->validateSchedule($request);
        $schedule = DB::transaction(function () use ($vehicleSchedule, $validated) {
            $this->ensureAvailable($validated, $vehicleSchedule->id);
            $vehicleSchedule->details()->delete();
            return $this->persist($vehicleSchedule, $validated);
        });

        return response()->json(['status' => true, 'message' => 'แก้ไขรายการใช้รถเรียบร้อย', 'data' => $schedule]);
    }

    public function destroy(VehicleSchedule $vehicleSchedule): JsonResponse
    {
        $vehicleSchedule->delete();
        return response()->json(['status' => true, 'message' => 'ลบรายการใช้รถเรียบร้อย']);
    }

    private function validateSchedule(Request $request): array
    {
        return $request->validate([
            'schedule_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', Rule::when($request->filled('start_time'), ['after_or_equal:start_time'])],
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'title' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'array'],
            'details.*' => ['nullable', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:10000'],
        ], [
            'schedule_date.required' => 'กรุณาเลือกวันที่',
            'schedule_date.date_format' => 'รูปแบบวันที่ไม่ถูกต้อง',
            'driver_id.required' => 'กรุณาเลือกพนักงานขับรถ',
            'driver_id.exists' => 'ไม่พบข้อมูลพนักงานขับรถ',
            'vehicle_id.required' => 'กรุณาเลือกทะเบียนรถ',
            'vehicle_id.exists' => 'ไม่พบข้อมูลทะเบียนรถ',
            'title.required' => 'กรุณากรอกหัวข้อ',
            'end_time.after_or_equal' => 'เวลาสิ้นสุดต้องไม่น้อยกว่าเวลาเริ่มต้น',
        ]);
    }

    private function ensureAvailable(array $data, ?int $ignoreId = null): void
    {
        $newStart = $data['start_time'] ?? '00:00:00';
        $newEnd = $data['end_time'] ?? '23:59:59';

        $candidates = VehicleSchedule::whereDate('schedule_date', $data['schedule_date'])
            ->where(function ($query) use ($data) {
                $query->where('vehicle_id', $data['vehicle_id'])->orWhere('driver_id', $data['driver_id']);
            })
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->lockForUpdate()
            ->get();

        foreach ($candidates as $existing) {
            $existingStart = $existing->start_time ?? '00:00:00';
            $existingEnd = $existing->end_time ?? '23:59:59';
            if ($newStart < $existingEnd && $newEnd > $existingStart) {
                if ((int) $existing->vehicle_id === (int) $data['vehicle_id']) {
                    throw ValidationException::withMessages(['vehicle_id' => 'รถคันนี้มีการใช้งานในช่วงเวลาดังกล่าวแล้ว']);
                }
                throw ValidationException::withMessages(['driver_id' => 'พนักงานขับรถคนนี้มีตารางงานในช่วงเวลาดังกล่าวแล้ว']);
            }
        }
    }

    private function persist(VehicleSchedule $schedule, array $data): VehicleSchedule
    {
        $schedule->fill([
            'schedule_date' => $data['schedule_date'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'driver_id' => $data['driver_id'],
            'vehicle_id' => $data['vehicle_id'],
            'title' => trim($data['title']),
            'note' => isset($data['note']) && trim($data['note']) !== '' ? trim($data['note']) : null,
        ])->save();

        collect($data['details'] ?? [])->map(fn ($detail) => trim((string) $detail))->filter()->values()
            ->each(fn ($detail, $index) => $schedule->details()->create(['detail_text' => $detail, 'sort_order' => $index + 1]));

        return $schedule->load(['driver', 'vehicle', 'details']);
    }
}
