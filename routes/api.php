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

Route::get('/knowledges', [KnowledgeController::class, 'index']);
Route::get('/knowledges/latest', [KnowledgeController::class, 'getLatestKnowledge']);
Route::post('/knowledges', [KnowledgeController::class, 'store']);
Route::get('/knowledges/file/{id}', [KnowledgeController::class, 'download']);
Route::put('/knowledges/{id}', [KnowledgeController::class, 'update']);

Route::get('/contents/type/{type}', [ContentController::class, 'getByType']);
Route::get('/contents/{slug}', [ContentController::class, 'show']);
Route::put('/contents/type/{type}', [ContentController::class, 'updateByType']);

Route::get('/departments', [DepartmentController::class, 'index']);
Route::post('/departments', [DepartmentController::class, 'store']);
Route::post('/departments/reorder', [DepartmentController::class, 'reorder']);
Route::put('/departments/{id}', [DepartmentController::class, 'update']);
Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);

Route::get('/executives', [ExecutiveController::class, 'index']);
Route::post('/executives', [ExecutiveController::class, 'store']);
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