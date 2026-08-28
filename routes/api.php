<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Api\AnnouncementTypeController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\KnowledgeController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\ExecutiveController;
use App\Http\Controllers\Api\DonationSettingController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\VehicleMasterController;
use App\Http\Controllers\Api\VehicleScheduleController;
use App\Http\Controllers\Api\OrganDonationController;
use App\Http\Controllers\Api\WebsitePolicyController;
use Mews\Purifier\Facades\Purifier;

Route::post('/login', function (Request $request) {

    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    if (!Auth::attempt($credentials)) {
        return response()->json(['message' => 'Invalid credentials'], 422);
    }

    return response()->json([
        'message' => 'Login success',
        'user' => $request->user(),
    ]);
});

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});

Route::get('/activities', [ActivityController::class, 'index']);
Route::get('/activities/{activity}', [ActivityController::class, 'show']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/activities', [ActivityController::class, 'store']);
    Route::put('/activities/{activity}', [ActivityController::class, 'update']);
    Route::delete('/activities/{activity}', [ActivityController::class, 'destroy']);
});

Route::get('/vehicle-schedules', [VehicleScheduleController::class, 'index']);
Route::get('/vehicle-schedules/{vehicleSchedule}', [VehicleScheduleController::class, 'show']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/drivers', [VehicleMasterController::class, 'drivers']);
    Route::post('/drivers', [VehicleMasterController::class, 'storeDriver']);
    Route::put('/drivers/{driver}', [VehicleMasterController::class, 'updateDriver']);
    Route::delete('/drivers/{driver}', [VehicleMasterController::class, 'destroyDriver']);
    Route::post('/drivers/reorder', [VehicleMasterController::class, 'reorderDrivers']);
    Route::get('/vehicles', [VehicleMasterController::class, 'vehicles']);
    Route::post('/vehicles', [VehicleMasterController::class, 'storeVehicle']);
    Route::put('/vehicles/{vehicle}', [VehicleMasterController::class, 'updateVehicle']);
    Route::delete('/vehicles/{vehicle}', [VehicleMasterController::class, 'destroyVehicle']);
    Route::post('/vehicles/reorder', [VehicleMasterController::class, 'reorderVehicles']);
    Route::post('/vehicle-schedules', [VehicleScheduleController::class, 'store']);
    Route::put('/vehicle-schedules/{vehicleSchedule}', [VehicleScheduleController::class, 'update']);
    Route::delete('/vehicle-schedules/{vehicleSchedule}', [VehicleScheduleController::class, 'destroy']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/announcement-types', [AnnouncementTypeController::class, 'index']);
Route::get('/announcements', [AnnouncementController::class, 'index']);
Route::get('/announcements/latest', [AnnouncementController::class, 'getLatestAnnouncement']);
Route::post('/announcements', [AnnouncementController::class, 'store']);
Route::get('/announcements/file/{id}', [AnnouncementController::class, 'download']);
Route::put('/announcements/{id}', [AnnouncementController::class, 'update']);

Route::get('/knowledges', [KnowledgeController::class, 'index']);
Route::get('/knowledges/latest', [KnowledgeController::class, 'getLatestKnowledge']);
Route::post('/knowledges', [KnowledgeController::class, 'store']);
Route::get('/knowledges/file/{id}', [KnowledgeController::class, 'download']);
Route::put('/knowledges/{id}', [KnowledgeController::class, 'update']);

Route::get('/contents/type/{type}', [ContentController::class, 'getByType']);
Route::get('/contents/{slug}', [ContentController::class, 'show']);
Route::put('/contents/type/{type}', [ContentController::class, 'updateByType']);

Route::get('/donation/settings', [DonationSettingController::class, 'show']);
Route::put('/donation/settings', [DonationSettingController::class, 'update']);

Route::get('/organ-donation', [OrganDonationController::class, 'showPublic']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/admin/organ-donation', [OrganDonationController::class, 'showAdmin']);
    Route::put('/admin/organ-donation', [OrganDonationController::class, 'update']);
});

Route::get('/policies', [WebsitePolicyController::class, 'publicIndex']);
Route::get('/policies/{policyType}', [WebsitePolicyController::class, 'publicShow']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/admin/policies', [WebsitePolicyController::class, 'adminIndex']);
    Route::put('/admin/policies/{policyType}', [WebsitePolicyController::class, 'update']);
});

Route::get('/departments', [DepartmentController::class, 'index']);
Route::post('/departments', [DepartmentController::class, 'store']);
Route::post('/departments/reorder', [DepartmentController::class, 'reorder']);
Route::put('/departments/{id}', [DepartmentController::class, 'update']);
Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);

Route::get('/executives', [ExecutiveController::class, 'index']);
Route::post('/executives', [ExecutiveController::class, 'store']);
Route::post('/executives/reorder', [ExecutiveController::class, 'reorder']);
Route::get('/executives/reindex', [ExecutiveController::class, 'reindex']);
Route::put('/executives/{id}', [ExecutiveController::class, 'update']);
Route::delete('/executives/{id}', [ExecutiveController::class, 'destroy']);



Route::get('/test-purifier', function () {
    $html = '<script>alert(1)</script><p>โรงพยาบาลเกาะช้าง</p>';
    return Purifier::clean($html, 'ckeditor');
});

Route::get('/test', function () {
    return response()->json([
        'message' => 'Laravel API ทำงานแล้ว'
    ]);
});
