<?php

use App\Http\Controllers\Website\ContactController;
use Illuminate\Support\Facades\Route;

Route::post('contact', [ContactController::class, 'store']);
