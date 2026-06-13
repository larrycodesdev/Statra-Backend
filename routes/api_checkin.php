<?php

use App\Http\Controllers\CheckIn\AuthController;
use App\Http\Controllers\CheckIn\CheckInController;
use Illuminate\Support\Facades\Route;

// ── Public: Auth ──────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('register',        [AuthController::class, 'register']);
    Route::post('login',           [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password',  [AuthController::class, 'resetPassword']);
});

// ── Protected: requires Sanctum token with 'checkin_user' ability ─────────────
Route::middleware(['auth:sanctum', 'ability:checkin_user'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('history',           [CheckInController::class, 'index']);
    Route::post('check-in',         [CheckInController::class, 'store']);
    Route::get('check-in/latest',   [CheckInController::class, 'latest']);
});
