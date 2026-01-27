<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10); // ค่า default
        $typeId  = $request->input('type_id');
        $keyword = $request->input('q');

        $query = Announcement::with('type')
            ->orderBy('created_at', 'desc');

        // 🔥 filter ตาม type
        if ($typeId) {
            $query->where('type_id', $typeId);
        }

        if ($keyword) {
            $query->where('title', 'like', "%{$keyword}%");
        }

        $announcements = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $announcements->items(),
            'current_page' => $announcements->currentPage(),
            'last_page' => $announcements->lastPage(),
            'total' => $announcements->total(),
        ]);
    }

    public function getLatestAnnouncement(Request $request)
    {
        $num  = $request->input('num',3);
        $news = Announcement::with('type')
            ->where('type_id', 1)
            ->orderBy('created_at', 'desc')
            ->take($num)
            ->get();

        $procurement = Announcement::with('type')
            ->where('type_id', 2)
            ->orderBy('created_at', 'desc')
            ->take($num)
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'news' => $news,
                'procurement' => $procurement
            ],
        ]);
    }


    public function store(Request $request)
    {
        $validated = $request->validate(
            [
                'title'   => 'required|string|max:255',
                'type_id' => 'required|exists:announcement_types,id',
                'file'    => 'required|file|mimes:pdf|max:10240',
            ],
            [
                'title.required' => 'กรุณากรอกหัวข้อ',
                'type_id.required' => 'กรุณาเลือกประเภทประกาศ',
                'type_id.exists' => 'ประเภทไม่ถูกต้อง',
                'file.required' => 'กรุณาเลือกไฟล์ PDF',
                'file.mimes' => 'รองรับเฉพาะไฟล์ PDF เท่านั้น',
                'file.max' => 'ขนาดไฟล์ต้องไม่เกิน 10MB',
            ]
        );

        $file = $request->file('file');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('announcements', $filename, 'public');

        $announcement = Announcement::create([
            'title' => $validated['title'],
            'type_id' => $validated['type_id'],
            'announce_date' => now(),
            'pdf_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'อัปโหลดสำเร็จ',
            'data' => [
                ...$announcement->toArray(),
                'file_url' => Storage::disk('public')->url($path),
            ],
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type_id' => 'required|exists:announcement_types,id',
            'file' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        if ($request->hasFile('file')) {
            Storage::disk('public')->delete($announcement->file_path);
            $path = $request->file('file')->store('announcements', 'public');
            $announcement->file_path = $path;
        }

        $announcement->update([
            'title' => $validated['title'],
            'type_id' => $validated['type_id'],
        ]);

        return response()->json(['status' => true]);
    }

    public function download($id)
    {
        $announcement = Announcement::findOrFail($id);
        $path = $announcement->file_path;

        if (!Storage::disk('public')->exists($path)) {
            abort(404, 'ไม่พบไฟล์');
        }

        return Storage::disk('public')->response(
            $path,
            $announcement->title, // ชื่อไฟล์ตอนดาวน์โหลด
            [
                'Content-Type' => 'application/pdf',
            ]
        );
    }
}
