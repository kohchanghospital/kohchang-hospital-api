<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\TwoFactorController;

Route::post('/login', [TwoFactorController::class, 'login']);
Route::get('/two-factor/challenge', [TwoFactorController::class, 'challengeStatus']);
Route::post('/two-factor/challenge', [TwoFactorController::class, 'challenge']);
Route::post('/two-factor/setup', [TwoFactorController::class, 'pendingSetup']);
Route::post('/two-factor/confirm', [TwoFactorController::class, 'pendingConfirm']);
Route::get('/two-factor/session', fn () => response()->noContent())
    ->middleware(['auth:web', '2fa']);

Route::post('/logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return response()->noContent();
});
