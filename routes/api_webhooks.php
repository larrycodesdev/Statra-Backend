<?php

use App\Http\Controllers\Webhook\PaystackWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('paystack', [PaystackWebhookController::class, 'handle']);
