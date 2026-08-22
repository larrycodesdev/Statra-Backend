<?php

use App\Http\Controllers\Website\ContactController;
use App\Http\Controllers\Website\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::post('contact', [ContactController::class, 'store']);

// Subscription init — called by the website after receiving token from app
Route::post('subscription/init', [SubscriptionController::class, 'init']);
