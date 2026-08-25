<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;

class VehicleMasterController extends Controller
{
    public function drivers(): JsonResponse
    {
        return response()->json(['status' => true, 'data' => Driver::where('is_active', true)->orderBy('name')->get()]);
    }

    public function vehicles(): JsonResponse
    {
        return response()->json(['status' => true, 'data' => Vehicle::where('is_active', true)->orderBy('registration_number')->get()]);
    }
}
