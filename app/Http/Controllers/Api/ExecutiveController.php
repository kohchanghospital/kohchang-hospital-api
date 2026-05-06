<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Executive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExecutiveController extends Controller
{
    public function index()
    {
        return Executive::with('department:id,name_th')
            ->orderBy('department_id')   // 🔥 เรียงตามฝ่ายก่อน
            ->orderBy('order_no')        // 🔥 แล้วค่อยเรียงลำดับในฝ่าย
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->all();

        // 🔥 คำนวณ order ตาม department
        $maxOrder = Executive::where('department_id', $request->department_id)
            ->max('order_no') ?? 0;

        $data['order_no'] = $maxOrder + 1;

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('executives', 'public');
            $data['image_path'] = $path;
        }

        return Executive::create($data);
    }

    public function update(Request $request, $id)
    {
        $executive = Executive::findOrFail($id);

        $data = $request->all();

        // 🔥 ถ้ามีการเปลี่ยน department
        if ($request->department_id != $executive->department_id) {

            $maxOrder = Executive::where('department_id', $request->department_id)
                ->max('order_no') ?? 0;

            $data['order_no'] = $maxOrder + 1;
        }

        if ($request->hasFile('image')) {

            // 🔥 ลบรูปเก่า
            if ($executive->image_path) {
                Storage::disk('public')->delete($executive->image_path);
            }

            // 🔥 อัปโหลดใหม่
            $path = $request->file('image')->store('executives', 'public');
            $data['image_path'] = $path;
        }

        $executive->update($data);

        return response()->json($executive);
    }

    public function destroy($id)
    {
        $executive = Executive::findOrFail($id);

        // 🔥 ลบรูปใน storage ด้วย
        if ($executive->image_path) {
            Storage::disk('public')->delete($executive->image_path);
        }

        $executive->delete();

        return response()->json(['message' => 'deleted']);
    }

    public function reorder(Request $request)
    {
        foreach ($request->executives as $item) {
            Executive::where('id', $item['id'])
                ->where('department_id', $request->department_id) // 🔥 กันมั่ว
                ->update(['order_no' => $item['order_no']]);
        }

        return response()->json(['success' => true]);
    }

    public function reindex()
{
    $execs = Executive::orderBy('department_id')
        ->orderBy('order_no')
        ->get();

    $currentDept = null;
    $index = 1;

    foreach ($execs as $e) {
        if ($currentDept != $e->department_id) {
            $currentDept = $e->department_id;
            $index = 1;
        }

        $e->update(['order_no' => $index++]);
    }

    return response()->json(['success' => true]);
}
}
