<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
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

        $query = Activity::with('details')
            ->orderBy('activity_date')
            ->orderByRaw('start_time IS NULL')
            ->orderBy('start_time');

        if (!empty($validated['start'])) {
            $query->whereDate('activity_date', '>=', $validated['start']);
        }
        if (!empty($validated['end'])) {
            // FullCalendar supplies an exclusive range end.
            $query->whereDate('activity_date', '<', $validated['end']);
        }
        if (!empty($validated['q'])) {
            $query->where('title', 'like', '%' . $validated['q'] . '%');
        }

        if (isset($validated['start']) || isset($validated['end'])) {
            return response()->json(['status' => true, 'data' => $query->get()]);
        }

        $activities = $query->paginate($validated['per_page'] ?? 10);

        return response()->json([
            'status' => true,
            'data' => $activities->items(),
            'current_page' => $activities->currentPage(),
            'last_page' => $activities->lastPage(),
            'total' => $activities->total(),
        ]);
    }

    public function show(Activity $activity): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $activity->load('details')]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateActivity($request);
        $activity = DB::transaction(fn () => $this->persist(new Activity(), $validated));

        return response()->json([
            'status' => true,
            'message' => 'เพิ่มกิจกรรมเรียบร้อย',
            'data' => $activity,
        ], 201);
    }

    public function update(Request $request, Activity $activity): JsonResponse
    {
        $validated = $this->validateActivity($request);
        $activity = DB::transaction(function () use ($activity, $validated) {
            $activity->details()->delete();
            return $this->persist($activity, $validated);
        });

        return response()->json([
            'status' => true,
            'message' => 'แก้ไขกิจกรรมเรียบร้อย',
            'data' => $activity,
        ]);
    }

    public function destroy(Activity $activity): JsonResponse
    {
        $activity->delete();
        return response()->json(['status' => true, 'message' => 'ลบกิจกรรมเรียบร้อย']);
    }

    private function validateActivity(Request $request): array
    {
        return $request->validate([
            'activity_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => [
                'nullable',
                'date_format:H:i',
                Rule::when($request->filled('start_time'), ['after_or_equal:start_time']),
            ],
            'title' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'array'],
            'details.*' => ['nullable', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:10000'],
        ], [
            'activity_date.required' => 'กรุณาเลือกวันที่',
            'activity_date.date_format' => 'รูปแบบวันที่ไม่ถูกต้อง',
            'title.required' => 'กรุณากรอกหัวข้อกิจกรรม',
            'end_time.after_or_equal' => 'เวลาสิ้นสุดต้องไม่น้อยกว่าเวลาเริ่มต้น',
            'start_time.date_format' => 'รูปแบบเวลาเริ่มต้นไม่ถูกต้อง',
            'end_time.date_format' => 'รูปแบบเวลาสิ้นสุดไม่ถูกต้อง',
        ]);
    }

    private function persist(Activity $activity, array $validated): Activity
    {
        $activity->fill([
            'activity_date' => $validated['activity_date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'title' => trim($validated['title']),
            'note' => isset($validated['note']) && trim($validated['note']) !== '' ? trim($validated['note']) : null,
        ])->save();

        collect($validated['details'] ?? [])
            ->map(fn ($detail) => trim((string) $detail))
            ->filter()
            ->values()
            ->each(fn ($detail, $index) => $activity->details()->create([
                'detail_text' => $detail,
                'sort_order' => $index + 1,
            ]));

        return $activity->load('details');
    }
}
