<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Api\AnnouncementTypeController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\ContentController;
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

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/announcement-types', [AnnouncementTypeController::class, 'index']);
Route::get('/announcements', [AnnouncementController::class, 'index']);
Route::get('/announcements/latest', [AnnouncementController::class, 'getLatestAnnouncement']);
Route::post('/announcements', [AnnouncementController::class, 'store']);
Route::get('/announcements/file/{id}', [AnnouncementController::class, 'download']);
Route::put('/announcements/{id}', [AnnouncementController::class, 'update']);

Route::get('/contents/type/{type}', [ContentController::class, 'getByType']);
Route::get('/contents/{slug}', [ContentController::class, 'show']);
Route::put('/contents/type/{type}', [ContentController::class, 'updateByType']);

Route::get('/test-purifier', function () {
    $html = '<script>alert(1)</script><p>โรงพยาบาลเกาะช้าง</p>';
    return Purifier::clean($html, 'ckeditor');
});

Route::get('/test', function () {
    return response()->json([
        'message' => 'Laravel API ทำงานแล้ว'
    ]);
});