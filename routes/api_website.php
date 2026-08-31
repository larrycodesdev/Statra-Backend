<?php

use App\Http\Controllers\Website\AuthController;
use App\Http\Controllers\Website\ContactController;
use App\Http\Controllers\Website\SubscriptionController;
use Illuminate\Support\Facades\Route;

// ── Public ─────────────────────────────────────────────────────────────────────
Route::post('contact', [ContactController::class, 'store']);

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
    Route::post('social',   [AuthController::class, 'social']);
});

// Subscription init — public token-based (called from app → website flow)
Route::post('subscription/init', [SubscriptionController::class, 'init']);

// ── Protected ──────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'ability:patient'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
});
