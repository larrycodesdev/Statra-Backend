<?php

use App\Http\Controllers\Doctor\AlertController;
use App\Http\Controllers\Doctor\AppointmentController;
use App\Http\Controllers\Doctor\AuthController;
use App\Http\Controllers\Doctor\DashboardController;
use App\Http\Controllers\Doctor\HospitalController;
use App\Http\Controllers\Doctor\PatientController;
use App\Http\Controllers\Doctor\ReportController;
use App\Http\Controllers\Doctor\StaffController;
use Illuminate\Support\Facades\Route;

// ── Public: Auth ──────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('register',        [AuthController::class, 'register']);
    Route::post('login',           [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password',  [AuthController::class, 'resetPassword']);
    Route::post('accept-invite',   [AuthController::class, 'acceptInvite']);
});

// ── Protected: requires valid Sanctum token + staff/doctor/admin/superadmin ───
Route::middleware(['auth:sanctum', 'staff'])->group(function () {

    // Auth
    Route::get('auth/me',     [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // Staff / approval management (admin + superadmin only — enforced in controller)
    Route::get('staff',              [StaffController::class, 'index']);
    Route::patch('staff/{id}/approve', [StaffController::class, 'approve']);

    // Dashboard
    Route::get('dashboard/summary',             [DashboardController::class, 'summary']);
    Route::get('dashboard/alert-feed',          [DashboardController::class, 'alertFeed']);
    Route::get('dashboard/weekly-alert-volume', [DashboardController::class, 'weeklyAlertVolume']);

    // Patient management
    Route::get('patients',                     [PatientController::class, 'index']);
    Route::post('patients',                    [PatientController::class, 'store']);
    Route::get('patients/{id}',                [PatientController::class, 'show']);
    Route::patch('patients/{id}',              [PatientController::class, 'update']);
    Route::get('patients/{id}/vitals',         [PatientController::class, 'vitals']);
    Route::get('patients/{id}/alerts',         [PatientController::class, 'alerts']);
    Route::get('patients/{id}/medications',    [PatientController::class, 'medications']);
    Route::post('patients/{id}/notes',         [PatientController::class, 'addNote']);

    // Alerts
    Route::get('alerts',               [AlertController::class, 'index']);
    Route::get('alerts/{id}',          [AlertController::class, 'show']);
    Route::put('alerts/{id}/resolve',  [AlertController::class, 'resolve']);
    Route::put('alerts/{id}/assign',   [AlertController::class, 'assign']);

    // Appointments
    Route::get('appointments',         [AppointmentController::class, 'index']);
    Route::post('appointments',        [AppointmentController::class, 'store']);
    Route::put('appointments/{id}',    [AppointmentController::class, 'update']);
    Route::delete('appointments/{id}', [AppointmentController::class, 'destroy']);

    // Reports
    Route::get('reports/summary',              [ReportController::class, 'summary']);
    Route::get('reports/weekly-alert-trend',   [ReportController::class, 'weeklyAlertTrend']);
    Route::get('reports/alert-type-breakdown', [ReportController::class, 'alertTypeBreakdown']);
    Route::get('reports/health-trends',        [ReportController::class, 'healthTrends']);
    Route::get('reports/alerts-analytics',     [ReportController::class, 'alertsAnalytics']);
    Route::get('reports/export',               [ReportController::class, 'export']);
});

// ── Superadmin only ───────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'superadmin'])->prefix('superadmin')->group(function () {
    Route::get('hospitals',                    [HospitalController::class, 'index']);
    Route::post('hospitals',                   [HospitalController::class, 'store']);
    Route::get('hospitals/{id}',               [HospitalController::class, 'show']);
    Route::put('hospitals/{id}',               [HospitalController::class, 'update']);
    Route::patch('hospitals/{id}/toggle',      [HospitalController::class, 'toggleActive']);
    Route::post('hospitals/{id}/admin',        [HospitalController::class, 'createAdmin']);
});
