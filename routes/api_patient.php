<?php

use App\Http\Controllers\Patient\AlertController;
use App\Http\Controllers\Patient\AppointmentController;
use App\Http\Controllers\Patient\AuthController;
use App\Http\Controllers\Patient\DeviceController;
use App\Http\Controllers\Patient\MedicationController;
use App\Http\Controllers\Patient\PainLogController;
use App\Http\Controllers\Patient\ProfileController;
use App\Http\Controllers\Patient\SettingsController;
use App\Http\Controllers\Patient\VitalsController;
use Illuminate\Support\Facades\Route;

// ── Public: Auth ──────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('register',        [AuthController::class, 'register']);
    Route::post('login',           [AuthController::class, 'login']);
    Route::post('social',          [AuthController::class, 'social']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('verify-otp',      [AuthController::class, 'verifyOtp']);
    Route::post('reset-password',  [AuthController::class, 'resetPassword']);
});

// ── Protected: requires valid Sanctum token with 'patient' ability ────────────
Route::middleware(['auth:sanctum', 'ability:patient', 'patient'])->group(function () {

    // Auth
    Route::post('auth/logout',           [AuthController::class, 'logout']);
    Route::put('auth/change-password',   [AuthController::class, 'changePassword']);

    // Profile
    Route::get('profile',                            [ProfileController::class, 'show']);
    Route::put('profile',                            [ProfileController::class, 'update']);
    Route::put('profile/emergency-contacts',         [ProfileController::class, 'updateEmergencyContacts']);
    Route::put('profile/medical-info',               [ProfileController::class, 'updateMedicalInfo']);
    Route::put('profile/fcm-token',                  [ProfileController::class, 'updateFcmToken']);

    // Settings (privacy toggles)
    Route::get('settings', [SettingsController::class, 'show']);
    Route::put('settings', [SettingsController::class, 'update']);

    // Device
    Route::post('device/register', [DeviceController::class, 'register']);
    Route::get('device/status',    [DeviceController::class, 'status']);

    // Vitals
    Route::post('vitals/sync', [VitalsController::class, 'sync']);
    Route::get('vitals',       [VitalsController::class, 'index']);

    // Alerts
    Route::get('alerts', [AlertController::class, 'index']);

    // Pain logs
    Route::post('pain-log', [PainLogController::class, 'store']);
    Route::get('pain-log',  [PainLogController::class, 'index']);

    // Medication logs
    Route::post('medication-log', [MedicationController::class, 'store']);
    Route::get('medication-log',  [MedicationController::class, 'index']);

    // Appointments
    Route::get('appointments', [AppointmentController::class, 'index']);
});
