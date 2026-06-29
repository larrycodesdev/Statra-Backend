<?php

use App\Http\Controllers\Patient\AlertController;
use App\Http\Controllers\Patient\HealthTrackerController;
use App\Http\Controllers\Patient\AppointmentController;
use App\Http\Controllers\Patient\AuthController;
use App\Http\Controllers\Patient\DeviceController;
use App\Http\Controllers\Patient\MedicationController;
use App\Http\Controllers\Patient\NotificationController;
use App\Http\Controllers\Patient\PainLogController;
use App\Http\Controllers\Patient\ProfileController;
use App\Http\Controllers\Patient\SettingsController;
use App\Http\Controllers\Patient\SymptomController;
use App\Http\Controllers\Patient\TriggerController;
use App\Http\Controllers\Patient\TrendsController;
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
    Route::get('profile',                          [ProfileController::class, 'show']);
    Route::put('profile',                          [ProfileController::class, 'update']);
    Route::post('profile/emergency-contacts',          [ProfileController::class, 'addEmergencyContact']);
    Route::delete('profile/emergency-contacts/{id}',   [ProfileController::class, 'removeEmergencyContact']);
    Route::put('profile/medical-info',             [ProfileController::class, 'updateMedicalInfo']);
    Route::put('profile/fcm-token',                [ProfileController::class, 'updateFcmToken']);
    Route::post('profile/avatar',                  [ProfileController::class, 'uploadAvatar']);

    // Settings
    Route::get('settings', [SettingsController::class, 'show']);
    Route::put('settings', [SettingsController::class, 'update']);

    // Device
    Route::post('device/register', [DeviceController::class, 'register']);
    Route::get('device/status',    [DeviceController::class, 'status']);

    // Vitals
    Route::post('vitals/sync', [VitalsController::class, 'sync']);
    Route::get('vitals',       [VitalsController::class, 'index']);

    // Alerts & Notifications
    Route::get('alerts',                          [AlertController::class, 'index']);
    Route::get('notifications',                   [NotificationController::class, 'index']);
    Route::patch('notifications/read-all',        [NotificationController::class, 'markAllRead']);
    Route::patch('notifications/{id}/read',       [NotificationController::class, 'markRead']);

    // Symptoms
    Route::get('symptoms/stats',      [SymptomController::class, 'stats']);
    Route::delete('symptoms/clear',   [SymptomController::class, 'clearAll']);
    Route::get('symptoms',            [SymptomController::class, 'index']);
    Route::post('symptoms',           [SymptomController::class, 'store']);
    Route::get('symptoms/{id}',       [SymptomController::class, 'show']);
    Route::put('symptoms/{id}',       [SymptomController::class, 'update']);
    Route::delete('symptoms/{id}',    [SymptomController::class, 'destroy']);

    // Medications
    Route::delete('medications/clear',         [MedicationController::class, 'clearAll']);
    Route::get('medications/log',              [MedicationController::class, 'log']);
    Route::get('medications',                  [MedicationController::class, 'index']);
    Route::post('medications',                 [MedicationController::class, 'store']);
    Route::get('medications/{id}',             [MedicationController::class, 'show']);
    Route::put('medications/{id}',             [MedicationController::class, 'update']);
    Route::delete('medications/{id}',          [MedicationController::class, 'destroy']);
    Route::post('medications/{id}/taken',      [MedicationController::class, 'markTaken']);
    Route::post('medications/{id}/missed',     [MedicationController::class, 'markMissed']);

    // Triggers
    Route::get('triggers', [TriggerController::class, 'index']);

    // Health Tracker (aggregated dashboard endpoint)
    Route::get('health-tracker', [HealthTrackerController::class, 'index']);

    // Trends & Insights
    Route::get('trends', [TrendsController::class, 'index']);

    // Pain logs (legacy — kept for backwards compat)
    Route::post('pain-log', [PainLogController::class, 'store']);
    Route::get('pain-log',  [PainLogController::class, 'index']);

    // Appointments
    Route::get('appointments', [AppointmentController::class, 'index']);
});
