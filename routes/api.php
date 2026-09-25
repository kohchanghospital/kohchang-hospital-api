<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Api\HeroSliderController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Api\WebsiteAnalyticsController;

Route::post('/analytics/heartbeat', [WebsiteAnalyticsController::class, 'heartbeat']);
Route::get('/admin/analytics/summary', [WebsiteAnalyticsController::class, 'summary'])
    ->middleware(['auth:sanctum', '2fa']);

Route::get('/hero-sliders', [HeroSliderController::class, 'index']);
Route::middleware(['auth:sanctum', '2fa'])->prefix('admin/hero-sliders')->group(function () {
    Route::get('/', [HeroSliderController::class, 'adminIndex']);
    Route::post('/', [HeroSliderController::class, 'store']);
    Route::post('/reorder', [HeroSliderController::class, 'reorder']);
    Route::put('/{heroSlider}', [HeroSliderController::class, 'update']);
    Route::delete('/{heroSlider}', [HeroSliderController::class, 'destroy']);
});

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
use App\Http\Controllers\Api\SiteSettingController;
use Mews\Purifier\Facades\Purifier;

Route::post('/login', [TwoFactorController::class, 'login']);

Route::middleware(['auth:sanctum', '2fa'])->prefix('profile')->group(function () {
    Route::get('/', [ProfileController::class, 'show']);
    Route::put('/', [ProfileController::class, 'update']);
    Route::put('/password', [ProfileController::class, 'changePassword']);
});

Route::middleware(['auth:sanctum', '2fa'])->prefix('security/two-factor')->group(function () {
    Route::get('/', [TwoFactorController::class, 'status']);
    Route::post('/setup', [TwoFactorController::class, 'setup']);
    Route::post('/confirm', [TwoFactorController::class, 'confirm']);
    Route::post('/disable', [TwoFactorController::class, 'disable']);
    Route::post('/recovery-codes', [TwoFactorController::class, 'regenerate']);
    Route::post('/reconfigure', [TwoFactorController::class, 'reconfigure']);
    Route::post('/reconfigure/confirm', [TwoFactorController::class, 'confirmReconfigure']);
});

Route::middleware(['auth:sanctum', '2fa'])->get('/me', function (Request $request) {
    return $request->user();
});

Route::get('/activities', [ActivityController::class, 'index']);
Route::get('/activities/{activity}', [ActivityController::class, 'show']);
Route::middleware(['auth:sanctum', '2fa'])->group(function () {
    Route::post('/activities', [ActivityController::class, 'store']);
    Route::put('/activities/{activity}', [ActivityController::class, 'update']);
    Route::delete('/activities/{activity}', [ActivityController::class, 'destroy']);
});

Route::get('/vehicle-schedules', [VehicleScheduleController::class, 'index']);
Route::get('/vehicle-schedules/{vehicleSchedule}', [VehicleScheduleController::class, 'show']);
Route::middleware(['auth:sanctum', '2fa'])->group(function () {
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
})->middleware(['auth:sanctum', '2fa']);

Route::get('/announcement-types', [AnnouncementTypeController::class, 'index']);
Route::get('/announcements', [AnnouncementController::class, 'index']);
Route::get('/announcements/latest', [AnnouncementController::class, 'getLatestAnnouncement']);
Route::post('/announcements', [AnnouncementController::class, 'store'])->middleware(['auth:sanctum', '2fa']);
Route::get('/announcements/file/{id}', [AnnouncementController::class, 'download']);
Route::put('/announcements/{id}', [AnnouncementController::class, 'update'])->middleware(['auth:sanctum', '2fa']);

Route::get('/knowledges', [KnowledgeController::class, 'index']);
Route::get('/knowledges/latest', [KnowledgeController::class, 'getLatestKnowledge']);
Route::post('/knowledges', [KnowledgeController::class, 'store'])->middleware(['auth:sanctum', '2fa']);
Route::get('/knowledges/file/{id}', [KnowledgeController::class, 'download']);
Route::put('/knowledges/{id}', [KnowledgeController::class, 'update'])->middleware(['auth:sanctum', '2fa']);

Route::get('/contents/type/{type}', [ContentController::class, 'getByType']);
Route::get('/contents/{slug}', [ContentController::class, 'show']);
Route::put('/contents/type/{type}', [ContentController::class, 'updateByType'])->middleware(['auth:sanctum', '2fa']);

Route::get('/donation/settings', [DonationSettingController::class, 'show']);
Route::put('/donation/settings', [DonationSettingController::class, 'update'])->middleware(['auth:sanctum', '2fa']);

Route::get('/organ-donation', [OrganDonationController::class, 'showPublic']);
Route::middleware(['auth:sanctum', '2fa'])->group(function () {
    Route::get('/admin/organ-donation', [OrganDonationController::class, 'showAdmin']);
    Route::put('/admin/organ-donation', [OrganDonationController::class, 'update']);
});

Route::get('/policies', [WebsitePolicyController::class, 'publicIndex']);
Route::get('/policies/{policyType}', [WebsitePolicyController::class, 'publicShow']);
Route::middleware(['auth:sanctum', '2fa'])->group(function () {
    Route::get('/admin/policies', [WebsitePolicyController::class, 'adminIndex']);
    Route::put('/admin/policies/{policyType}', [WebsitePolicyController::class, 'update']);
});

Route::get('/site-settings', [SiteSettingController::class, 'publicIndex']);
Route::middleware(['auth:sanctum', '2fa'])->group(function () {
    Route::get('/admin/site-settings', [SiteSettingController::class, 'adminIndex']);
    Route::put('/admin/site-settings', [SiteSettingController::class, 'update']);
});

Route::get('/departments', [DepartmentController::class, 'index']);
Route::post('/departments', [DepartmentController::class, 'store'])->middleware(['auth:sanctum', '2fa']);
Route::post('/departments/reorder', [DepartmentController::class, 'reorder'])->middleware(['auth:sanctum', '2fa']);
Route::put('/departments/{id}', [DepartmentController::class, 'update'])->middleware(['auth:sanctum', '2fa']);
Route::delete('/departments/{id}', [DepartmentController::class, 'destroy'])->middleware(['auth:sanctum', '2fa']);

Route::get('/executives', [ExecutiveController::class, 'index']);
Route::post('/executives', [ExecutiveController::class, 'store'])->middleware(['auth:sanctum', '2fa']);
Route::post('/executives/reorder', [ExecutiveController::class, 'reorder'])->middleware(['auth:sanctum', '2fa']);
Route::get('/executives/reindex', [ExecutiveController::class, 'reindex'])->middleware(['auth:sanctum', '2fa']);
Route::put('/executives/{id}', [ExecutiveController::class, 'update'])->middleware(['auth:sanctum', '2fa']);
Route::delete('/executives/{id}', [ExecutiveController::class, 'destroy'])->middleware(['auth:sanctum', '2fa']);



Route::get('/test-purifier', function () {
    $html = '<script>alert(1)</script><p>โรงพยาบาลเกาะช้าง</p>';
    return Purifier::clean($html, 'ckeditor');
});

Route::get('/test', function () {
    return response()->json([
        'message' => 'Laravel API ทำงานแล้ว'
    ]);
});
