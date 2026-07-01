<?php

use App\Http\Controllers\Store\AdminAuthController;
use App\Http\Controllers\Store\AdminOrderController;
use App\Http\Controllers\Store\AffiliateController;
use App\Http\Controllers\Store\OrderController;
use App\Http\Controllers\Store\PaymentController;
use App\Http\Controllers\Store\ProductController;
use Illuminate\Support\Facades\Route;

// ── Public ────────────────────────────────────────────────────────────────────
Route::get('product',                          [ProductController::class,  'show']);
Route::post('orders',                          [OrderController::class,    'store']);
Route::get('orders/{orderNumber}',             [OrderController::class,    'track']);
Route::post('orders/{orderNumber}/review',     [OrderController::class,    'review']);
Route::post('payment/webhook',                 [PaymentController::class,  'webhook']);
Route::post('affiliates/join',                 [AffiliateController::class, 'join']);

// ── Admin auth (no token required) ───────────────────────────────────────────
Route::prefix('admin')->group(function () {
    Route::post('auth/login', [AdminAuthController::class, 'login']);
});

// ── Admin — Bearer token required ────────────────────────────────────────────
Route::middleware('store.admin')->prefix('admin')->group(function () {
    Route::post('auth/logout',          [AdminAuthController::class,  'logout']);
    Route::post('auth/change-password', [AdminAuthController::class,  'changePassword']);
    Route::get('auth/me',               [AdminAuthController::class,  'me']);

    Route::get('stats',                          [AdminOrderController::class, 'stats']);
    Route::get('activity',                       [AdminOrderController::class, 'activity']);
    Route::get('orders',                         [AdminOrderController::class, 'index']);
    Route::get('orders/{orderNumber}',           [AdminOrderController::class, 'show']);
    Route::patch('orders/{orderNumber}/status',  [AdminOrderController::class, 'updateStatus']);
    Route::patch('orders/{orderNumber}/issue',   [AdminOrderController::class, 'updateIssue']);
});
