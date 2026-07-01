<?php

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

// ── Admin — Bearer token required (STORE_ADMIN_TOKEN) ────────────────────────
Route::middleware('store.admin')->prefix('admin')->group(function () {
    Route::get('stats',                                [AdminOrderController::class, 'stats']);
    Route::get('activity',                             [AdminOrderController::class, 'activity']);
    Route::get('orders',                               [AdminOrderController::class, 'index']);
    Route::get('orders/{orderNumber}',                 [AdminOrderController::class, 'show']);
    Route::patch('orders/{orderNumber}/status',        [AdminOrderController::class, 'updateStatus']);
    Route::patch('orders/{orderNumber}/issue',         [AdminOrderController::class, 'updateIssue']);
});
