<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        return Department::orderBy('order_no')->get();
    }

    public function store(Request $request)
    {
        $maxOrder = Department::max('order_no') ?? 0;

        $dept = Department::create([
            'name_th' => $request->name_th,
            'order_no' => $maxOrder + 1, // 🔥 auto ต่อท้าย
        ]);

        return response()->json($dept);
    }

    public function update(Request $request, $id)
    {
        $dept = Department::findOrFail($id);

        $dept->update([
            'name_th' => $request->name_th,
        ]);

        return response()->json($dept);
    }

    public function destroy($id)
    {
        $exists = \DB::table('executives')->where('department_id', $id)->exists();

        if ($exists) {
            return response()->json([
                'message' => 'มีข้อมูลใช้งานอยู่'
            ], 400);
        }

        Department::destroy($id);
        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        foreach ($request->departments as $dept) {
            Department::where('id', $dept['id'])
                ->update(['order_no' => $dept['order_no']]);
        }

        return response()->json(['success' => true]);
    }
}
