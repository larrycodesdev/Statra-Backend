<?php

use App\Http\Controllers\Store\AdminOrderController;
use App\Http\Controllers\Store\OrderController;
use App\Http\Controllers\Store\PaymentController;
use App\Http\Controllers\Store\ProductController;
use Illuminate\Support\Facades\Route;

// ── Public ────────────────────────────────────────────────────────────────────
Route::get('product',                    [ProductController::class, 'show']);
Route::post('orders',                    [OrderController::class,   'store']);
Route::get('orders/{orderNumber}',       [OrderController::class,   'track']);
Route::post('payment/webhook',           [PaymentController::class, 'webhook']);

// ── Admin — Bearer token required (STORE_ADMIN_TOKEN) ─────────────────────────
Route::middleware('store.admin')->prefix('admin')->group(function () {
    Route::get('orders',                          [AdminOrderController::class, 'index']);
    Route::get('orders/{orderNumber}',            [AdminOrderController::class, 'show']);
    Route::patch('orders/{orderNumber}/status',   [AdminOrderController::class, 'updateStatus']);
});
