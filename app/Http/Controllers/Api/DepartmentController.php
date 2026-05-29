<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Executive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        $dept = Department::findOrFail($id);

        DB::transaction(function () use ($dept) {
            $executives = Executive::where('department_id', $dept->id)->get();

            foreach ($executives as $executive) {
                if ($executive->image_path) {
                    Storage::disk('public')->delete($executive->image_path);
                }

                $executive->delete();
            }

            $dept->delete();
        });

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
