<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Api\AnnouncementTypeController;
use App\Http\Controllers\Api\AnnouncementController;

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
Route::post('/announcements', [AnnouncementController::class, 'store']);
Route::get('/announcements/file/{id}', [AnnouncementController::class, 'download']);
Route::put('/announcements/{id}', [AnnouncementController::class, 'update']);

Route::get('/test', function () {
    return response()->json([
        'message' => 'Laravel API ทำงานแล้ว'
    ]);
});