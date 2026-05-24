<?php

use App\Http\Controllers\Doctor\AlertController;
use App\Http\Controllers\Doctor\AppointmentController;
use App\Http\Controllers\Doctor\AuthController;
use App\Http\Controllers\Doctor\PatientController;
use App\Http\Controllers\Doctor\ReportController;
use Illuminate\Support\Facades\Route;

// ── Public: Auth ──────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('register',        [AuthController::class, 'register']);
    Route::post('login',           [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password',  [AuthController::class, 'resetPassword']);
});

// ── Protected: requires valid Sanctum token with 'doctor' ability ─────────────
Route::middleware(['auth:sanctum', 'ability:doctor', 'doctor'])->group(function () {

    // Auth
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // Patient management
    Route::get('patients',                        [PatientController::class, 'index']);
    Route::get('patients/{id}',                   [PatientController::class, 'show']);
    Route::get('patients/{id}/vitals',            [PatientController::class, 'vitals']);
    Route::get('patients/{id}/alerts',            [PatientController::class, 'alerts']);
    Route::post('patients/{id}/notes',            [PatientController::class, 'addNote']);
    Route::get('dashboard',                       [PatientController::class, 'dashboard']);

    // Alerts
    Route::get('alerts',                    [AlertController::class, 'index']);
    Route::put('alerts/{id}/resolve',       [AlertController::class, 'resolve']);
    Route::put('alerts/{id}/assign',        [AlertController::class, 'assign']);

    // Appointments
    Route::get('appointments',              [AppointmentController::class, 'index']);
    Route::post('appointments',             [AppointmentController::class, 'store']);
    Route::put('appointments/{id}',         [AppointmentController::class, 'update']);

    // Reports
    Route::get('reports/health-trends',     [ReportController::class, 'healthTrends']);
    Route::get('reports/alerts-analytics',  [ReportController::class, 'alertsAnalytics']);
    Route::get('reports/export',            [ReportController::class, 'export']);
});
