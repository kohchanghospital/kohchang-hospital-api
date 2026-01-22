<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnnouncementType;

class AnnouncementTypeController extends Controller
{
    public function index()
    {
        $types = AnnouncementType::select('id', 'name')
            ->orderBy('id')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $types
        ]);
    }
}
