<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Knowledge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class KnowledgeController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10); // ค่า default
        $keyword = $request->input('q');

        $query = Knowledge::orderBy('created_at', 'desc');

        if ($keyword) {
            $query->where('title', 'like', "%{$keyword}%");
        }

        $knowledges = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $knowledges->items(),
            'current_page' => $knowledges->currentPage(),
            'last_page' => $knowledges->lastPage(),
            'total' => $knowledges->total(),
        ]);
    }

    public function getLatestKnowledge(Request $request)
    {
        $num  = $request->input('num',3);
        $knowledge = Knowledge::orderBy('created_at', 'desc')
            ->take($num)
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'knowledge' => $knowledge,
            ],
        ]);
    }


    public function store(Request $request)
    {
        $validated = $request->validate(
            [
                'title'   => 'required|string|max:255',
                'file'    => 'required|file|mimes:pdf|max:10240',
            ],
            [
                'title.required' => 'กรุณากรอกหัวข้อ',
                'file.required' => 'กรุณาเลือกไฟล์ PDF',
                'file.mimes' => 'รองรับเฉพาะไฟล์ PDF เท่านั้น',
                'file.max' => 'ขนาดไฟล์ต้องไม่เกิน 10MB',
            ]
        );

        $file = $request->file('file');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('knowledges', $filename, 'public');

        $knowledge = Knowledge::create([
            'title' => $validated['title'],
            'announce_date' => now(),
            'pdf_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'อัปโหลดสำเร็จ',
            'data' => [
                ...$knowledge->toArray(),
                'file_url' => Storage::disk('public')->url($path),
            ],
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $knowledge = Knowledge::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        if ($request->hasFile('file')) {
            Storage::disk('public')->delete($knowledge->file_path);
            $path = $request->file('file')->store('knowledges', 'public');
            $knowledge->file_path = $path;
        }

        $knowledge->update([
            'title' => $validated['title'],
        ]);

        return response()->json(['status' => true]);
    }

    public function download($id)
    {
        $knowledge = Knowledge::findOrFail($id);
        $path = $knowledge->file_path;

        if (!Storage::disk('public')->exists($path)) {
            abort(404, 'ไม่พบไฟล์');
        }

        return Storage::disk('public')->response(
            $path,
            $knowledge->title, // ชื่อไฟล์ตอนดาวน์โหลด
            [
                'Content-Type' => 'application/pdf',
            ]
        );
    }
}
