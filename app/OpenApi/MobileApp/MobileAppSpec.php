<?php

namespace App\OpenApi\MobileApp;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'SCD Wellness — Mobile App API',
    version: '1.0.0',
    description: 'REST API for the SCD Wellness Flutter app. Two roles: **patient** (mobile) and **doctor** (web dashboard). Protected routes require `Authorization: Bearer {token}`.',
    contact: new OA\Contact(email: 'api@scdwellness.app')
)]
#[OA\Server(url: 'http://localhost:8000', description: 'Local dev')]
#[OA\Server(url: 'https://api.scdwellness.app', description: 'Production')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', scheme: 'bearer', bearerFormat: 'Sanctum')]
/**
 * @OA\Tag(name="Patient Auth",        description="Register, login (email or username), OTP password reset")
 * @OA\Tag(name="Patient Profile",     description="Personal info, medical info, emergency contacts, FCM token")
 * @OA\Tag(name="Patient Device",      description="Wearable device registration")
 * @OA\Tag(name="Patient Vitals",      description="Vitals sync from JCVital band and history")
 * @OA\Tag(name="Patient Alerts",      description="Alerts triggered by the alert engine")
 * @OA\Tag(name="Patient Logs",        description="Pain logs, medication logs, appointments")
 * @OA\Tag(name="Patient Settings",    description="Privacy and notification toggles")
 * @OA\Tag(name="Doctor Auth",         description="Doctor register and login")
 * @OA\Tag(name="Doctor Patients",     description="Patient management and dashboard")
 * @OA\Tag(name="Doctor Alerts",       description="Alert management — resolve, assign")
 * @OA\Tag(name="Doctor Appointments", description="Appointment scheduling")
 * @OA\Tag(name="Doctor Reports",      description="Health trends, analytics, CSV export")
 *
 * @OA\Schema(schema="SuccessResponse", type="object",
 *   @OA\Property(property="success", type="boolean", example=true),
 *   @OA\Property(property="message", type="string",  example="Operation successful."),
 *   @OA\Property(property="data",    nullable=true)
 * )
 * @OA\Schema(schema="AuthResponse", type="object",
 *   @OA\Property(property="success", type="boolean", example=true),
 *   @OA\Property(property="message", type="string",  example="Login successful."),
 *   @OA\Property(property="data", type="object",
 *     @OA\Property(property="token", type="string", example="1|abc123"),
 *     @OA\Property(property="user",  type="object",
 *       @OA\Property(property="id",         type="integer", example=1),
 *       @OA\Property(property="first_name", type="string",  example="Jane"),
 *       @OA\Property(property="last_name",  type="string",  example="Johnson"),
 *       @OA\Property(property="username",   type="string",  example="janej"),
 *       @OA\Property(property="email",      type="string",  example="jane@example.com"),
 *       @OA\Property(property="role",       type="string",  example="patient")
 *     )
 *   )
 * )
 * @OA\Schema(schema="PaginatedResponse", type="object",
 *   @OA\Property(property="success", type="boolean", example=true),
 *   @OA\Property(property="data",    type="array", @OA\Items(type="object")),
 *   @OA\Property(property="meta", type="object",
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="per_page",     type="integer", example=20),
 *     @OA\Property(property="total",        type="integer", example=84),
 *     @OA\Property(property="last_page",    type="integer", example=5)
 *   )
 * )
 * @OA\Schema(schema="ErrorResponse", type="object",
 *   @OA\Property(property="success", type="boolean", example=false),
 *   @OA\Property(property="message", type="string",  example="Invalid credentials.")
 * )
 * @OA\Schema(schema="ValidationError", type="object",
 *   @OA\Property(property="success", type="boolean", example=false),
 *   @OA\Property(property="message", type="string",  example="Validation failed."),
 *   @OA\Property(property="errors",  type="object")
 * )
 */
class MobileAppSpec
{
    // ── PATIENT AUTH ──────────────────────────────────────────────────────────

    /** @OA\Post(path="/api/v1/patient/auth/register", tags={"Patient Auth"}, summary="Register a new patient",
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"first_name","last_name","email","password","password_confirmation"},
     *     @OA\Property(property="first_name", type="string", example="Jane"),
     *     @OA\Property(property="last_name",  type="string", example="Johnson"),
     *     @OA\Property(property="username",   type="string", example="janej", description="Auto-generated if omitted"),
     *     @OA\Property(property="email",      type="string", format="email"),
     *     @OA\Property(property="password",   type="string", format="password", example="Password1"),
     *     @OA\Property(property="password_confirmation", type="string", example="Password1"),
     *     @OA\Property(property="phone", type="string", example="+2348012345678"))),
     *   @OA\Response(response=201, description="Registered", @OA\JsonContent(ref="#/components/schemas/AuthResponse")),
     *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))) */
    public function patientRegister() {}

    /** @OA\Post(path="/api/v1/patient/auth/login", tags={"Patient Auth"}, summary="Login with email OR username",
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"identifier","password"},
     *     @OA\Property(property="identifier", type="string", example="jane@example.com", description="Email or username"),
     *     @OA\Property(property="password",   type="string", format="password"),
     *     @OA\Property(property="fcm_token",  type="string", description="Refreshes push token on login"))),
     *   @OA\Response(response=200, description="Login successful", @OA\JsonContent(ref="#/components/schemas/AuthResponse")),
     *   @OA\Response(response=401, description="Invalid credentials", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))) */
    public function patientLogin() {}

    /** @OA\Post(path="/api/v1/patient/auth/social", tags={"Patient Auth"}, summary="Sign in with Google / Apple / Facebook",
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"provider","token"},
     *     @OA\Property(property="provider", type="string", enum={"google","apple","facebook"}),
     *     @OA\Property(property="token", type="string", description="OAuth access token from provider SDK"))),
     *   @OA\Response(response=200, description="Social login successful", @OA\JsonContent(ref="#/components/schemas/AuthResponse")),
     *   @OA\Response(response=401, description="Invalid social token", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))) */
    public function patientSocial() {}

    /** @OA\Post(path="/api/v1/patient/auth/forgot-password", tags={"Patient Auth"}, summary="Step 1 — Request 6-digit OTP via email",
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"email"}, @OA\Property(property="email", type="string", format="email"))),
     *   @OA\Response(response=200, description="OTP sent (always returns success to prevent email enumeration")) */
    public function patientForgotPassword() {}

    /** @OA\Post(path="/api/v1/patient/auth/verify-otp", tags={"Patient Auth"}, summary="Step 2 — Verify OTP, receive reset_token",
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"email","otp"},
     *     @OA\Property(property="email", type="string", format="email"),
     *     @OA\Property(property="otp",   type="string", example="482916", description="6-digit code from email"))),
     *   @OA\Response(response=200, description="OTP verified",
     *     @OA\JsonContent(@OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="data", type="object", @OA\Property(property="reset_token", type="string", example="AbCdEf...")))),
     *   @OA\Response(response=400, description="Invalid or expired OTP", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))) */
    public function patientVerifyOtp() {}

    /** @OA\Post(path="/api/v1/patient/auth/reset-password", tags={"Patient Auth"}, summary="Step 3 — Set new password using reset_token",
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"reset_token","password","password_confirmation"},
     *     @OA\Property(property="reset_token", type="string"),
     *     @OA\Property(property="password", type="string", format="password"),
     *     @OA\Property(property="password_confirmation", type="string"))),
     *   @OA\Response(response=200, description="Password reset", @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
     *   @OA\Response(response=400, description="Invalid token", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))) */
    public function patientResetPassword() {}

    /** @OA\Post(path="/api/v1/patient/auth/logout", tags={"Patient Auth"}, summary="Revoke current device token", security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Logged out", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function patientLogout() {}

    /** @OA\Put(path="/api/v1/patient/auth/change-password", tags={"Patient Auth"}, summary="Change password while logged in", security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"current_password","password","password_confirmation"},
     *     @OA\Property(property="current_password", type="string", format="password"),
     *     @OA\Property(property="password", type="string", format="password"),
     *     @OA\Property(property="password_confirmation", type="string"))),
     *   @OA\Response(response=200, description="Password updated", @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
     *   @OA\Response(response=400, description="Current password incorrect", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))) */
    public function patientChangePassword() {}

    // ── PATIENT PROFILE ───────────────────────────────────────────────────────

    /** @OA\Get(path="/api/v1/patient/profile", tags={"Patient Profile"}, summary="Get full patient profile", security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Profile data",
     *     @OA\JsonContent(@OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="first_name", type="string", example="Jane"),
     *         @OA\Property(property="last_name",  type="string", example="Johnson"),
     *         @OA\Property(property="username",   type="string", example="jenny"),
     *         @OA\Property(property="email",      type="string"),
     *         @OA\Property(property="gender",     type="string", example="female"),
     *         @OA\Property(property="age",        type="integer", example=25),
     *         @OA\Property(property="genotype",   type="string", example="SS"),
     *         @OA\Property(property="blood_type", type="string", example="O+"),
     *         @OA\Property(property="condition",  type="array", @OA\Items(type="string", example="Leg Ulcer")),
     *         @OA\Property(property="emergency_contact", type="object",
     *           @OA\Property(property="name", type="string"), @OA\Property(property="phone", type="string"),
     *           @OA\Property(property="email", type="string"), @OA\Property(property="address", type="string"),
     *           @OA\Property(property="relationship", type="string")),
     *         @OA\Property(property="care_team", type="array", @OA\Items(type="object")))))) */
    public function patientGetProfile() {}

    /** @OA\Put(path="/api/v1/patient/profile", tags={"Patient Profile"}, summary="Update personal info", security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true, @OA\JsonContent(
     *     @OA\Property(property="first_name", type="string"), @OA\Property(property="last_name", type="string"),
     *     @OA\Property(property="username", type="string"), @OA\Property(property="phone", type="string"),
     *     @OA\Property(property="avatar", type="string"), @OA\Property(property="gender", type="string", enum={"male","female","other"}),
     *     @OA\Property(property="date_of_birth", type="string", format="date", example="2001-03-15"))),
     *   @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function patientUpdateProfile() {}

    /** @OA\Put(path="/api/v1/patient/profile/medical-info", tags={"Patient Profile"}, summary="Update genotype, blood type, conditions", security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true, @OA\JsonContent(
     *     @OA\Property(property="genotype",   type="string", enum={"SS","SC","SB","SD","SE","SO","other"}),
     *     @OA\Property(property="blood_type", type="string", example="O+"),
     *     @OA\Property(property="condition",  type="array",
     *       @OA\Items(type="string", enum={"Leg Ulcer","Acute chest syndrome","Stroke","Organ damage","Vision Problem","Priapism"})))),
     *   @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function patientUpdateMedical() {}

    /** @OA\Put(path="/api/v1/patient/profile/emergency-contacts", tags={"Patient Profile"}, summary="Update emergency contact", security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"name","phone"},
     *     @OA\Property(property="name", type="string", example="Joseph Johnson"),
     *     @OA\Property(property="phone", type="string", example="+234 80 333 57163"),
     *     @OA\Property(property="email", type="string"), @OA\Property(property="address", type="string"),
     *     @OA\Property(property="relationship", type="string", example="Brother"))),
     *   @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function patientUpdateEmergency() {}

    /** @OA\Put(path="/api/v1/patient/profile/fcm-token", tags={"Patient Profile"}, summary="Refresh FCM push token — call on every app launch", security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"fcm_token"}, @OA\Property(property="fcm_token", type="string", example="fGT7s_..."))),
     *   @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function patientUpdateFcm() {}

    // ── PATIENT SETTINGS ──────────────────────────────────────────────────────

    /** @OA\Get(path="/api/v1/patient/settings", tags={"Patient Settings"}, summary="Get privacy and notification settings", security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Settings",
     *     @OA\JsonContent(@OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="allow_doctor_view_records", type="boolean", example=true),
     *         @OA\Property(property="allow_doctor_view_data",    type="boolean", example=true),
     *         @OA\Property(property="share_symptom_pain_data",   type="boolean", example=true),
     *         @OA\Property(property="share_medication_records",  type="boolean", example=true),
     *         @OA\Property(property="reminder_enabled",          type="boolean", example=true),
     *         @OA\Property(property="smart_alert_enabled",       type="boolean", example=true))))) */
    public function patientGetSettings() {}

    /** @OA\Put(path="/api/v1/patient/settings", tags={"Patient Settings"}, summary="Update privacy/notification toggles", security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true, @OA\JsonContent(
     *     @OA\Property(property="allow_doctor_view_records", type="boolean"),
     *     @OA\Property(property="allow_doctor_view_data",    type="boolean"),
     *     @OA\Property(property="share_symptom_pain_data",   type="boolean"),
     *     @OA\Property(property="share_medication_records",  type="boolean"),
     *     @OA\Property(property="reminder_enabled",          type="boolean"),
     *     @OA\Property(property="smart_alert_enabled",       type="boolean"))),
     *   @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function patientUpdateSettings() {}

    // ── DEVICE + VITALS ───────────────────────────────────────────────────────

    /** @OA\Post(path="/api/v1/patient/device/register", tags={"Patient Device"}, summary="Register or update the JCVital wearable", security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"device_id","platform"},
     *     @OA\Property(property="device_id", type="string", example="JCV-2208A-001"),
     *     @OA\Property(property="device_model", type="string", example="JCVital 2208A"),
     *     @OA\Property(property="firmware_version", type="string", example="2.1.4"),
     *     @OA\Property(property="platform", type="string", enum={"android","ios"}))),
     *   @OA\Response(response=201, description="Device registered", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function deviceRegister() {}

    /** @OA\Get(path="/api/v1/patient/device/status", tags={"Patient Device"}, summary="Get all registered devices", security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Device list", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function deviceStatus() {}

    /** @OA\Post(path="/api/v1/patient/vitals/sync", tags={"Patient Vitals"}, summary="Sync a batch of readings — 202 returned immediately, processed async", security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"device_id","readings"},
     *     @OA\Property(property="device_id", type="string", example="JCV-2208A-001"),
     *     @OA\Property(property="readings", type="array",
     *       @OA\Items(type="object",
     *         @OA\Property(property="type", type="string", enum={"heart_rate","spo2","temperature","blood_pressure","steps","sleep_state","hrv"}),
     *         @OA\Property(property="value", description="Number, or {systolic,diastolic} for blood_pressure"),
     *         @OA\Property(property="unit", type="string", example="bpm"),
     *         @OA\Property(property="recorded_at", type="string", format="date-time"))))),
     *   @OA\Response(response=202, description="Accepted — batch queued for alert processing")) */
    public function vitalsSync() {}

    /** @OA\Get(path="/api/v1/patient/vitals", tags={"Patient Vitals"}, summary="Get vitals history", security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="type",  in="query", @OA\Schema(type="string", enum={"heart_rate","spo2","temperature","blood_pressure","steps","sleep_state","hrv"})),
     *   @OA\Parameter(name="range", in="query", @OA\Schema(type="string", enum={"7d","30d","90d"})),
     *   @OA\Response(response=200, description="Vitals", @OA\JsonContent(ref="#/components/schemas/PaginatedResponse"))) */
    public function vitalsIndex() {}

    // ── PATIENT ALERTS + LOGS ─────────────────────────────────────────────────

    /** @OA\Get(path="/api/v1/patient/alerts", tags={"Patient Alerts"}, summary="Get alerts for this patient", security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"pending","acknowledged","resolved"})),
     *   @OA\Response(response=200, description="Alert list", @OA\JsonContent(ref="#/components/schemas/PaginatedResponse"))) */
    public function patientAlerts() {}

    /** @OA\Post(path="/api/v1/patient/pain-log", tags={"Patient Logs"}, summary="Log a pain episode", security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"pain_level","logged_at"},
     *     @OA\Property(property="pain_level", type="integer", minimum=1, maximum=10, example=7),
     *     @OA\Property(property="location", type="array", @OA\Items(type="string", example="chest")),
     *     @OA\Property(property="notes", type="string"), @OA\Property(property="logged_at", type="string", format="date-time"))),
     *   @OA\Response(response=201, description="Saved", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function painLogStore() {}

    /** @OA\Get(path="/api/v1/patient/pain-log", tags={"Patient Logs"}, summary="Get pain log history", security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Pain logs", @OA\JsonContent(ref="#/components/schemas/PaginatedResponse"))) */
    public function painLogIndex() {}

    /** @OA\Post(path="/api/v1/patient/medication-log", tags={"Patient Logs"}, summary="Log a medication entry", security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"medication_name","dosage","scheduled_at","status"},
     *     @OA\Property(property="medication_name", type="string", example="Hydroxyurea 500mg"),
     *     @OA\Property(property="dosage", type="string", example="1 tablet"),
     *     @OA\Property(property="scheduled_at", type="string", format="date-time"),
     *     @OA\Property(property="taken_at", type="string", format="date-time", nullable=true),
     *     @OA\Property(property="status", type="string", enum={"taken","missed","pending"}))),
     *   @OA\Response(response=201, description="Saved", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function medicationLogStore() {}

    /** @OA\Get(path="/api/v1/patient/medication-log", tags={"Patient Logs"}, summary="Get medication log history", security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Logs", @OA\JsonContent(ref="#/components/schemas/PaginatedResponse"))) */
    public function medicationLogIndex() {}

    /** @OA\Get(path="/api/v1/patient/appointments", tags={"Patient Logs"}, summary="Get appointments", security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Appointments", @OA\JsonContent(ref="#/components/schemas/PaginatedResponse"))) */
    public function patientAppointments() {}

    // ── DOCTOR AUTH ───────────────────────────────────────────────────────────

    /** @OA\Post(path="/api/v1/doctor/auth/register", tags={"Doctor Auth"}, summary="Register a doctor account",
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"name","email","password","password_confirmation"},
     *     @OA\Property(property="name", type="string", example="Dr. Adewale Okafor"),
     *     @OA\Property(property="email", type="string", format="email"),
     *     @OA\Property(property="password", type="string", format="password"),
     *     @OA\Property(property="password_confirmation", type="string"),
     *     @OA\Property(property="hospital_name", type="string", example="Lagos General Hospital"),
     *     @OA\Property(property="department", type="string", example="Haematology"),
     *     @OA\Property(property="specialisation", type="string", example="Sickle Cell Disease"),
     *     @OA\Property(property="license_number", type="string", example="MDCN-12345"))),
     *   @OA\Response(response=201, description="Registered", @OA\JsonContent(ref="#/components/schemas/AuthResponse"))) */
    public function doctorRegister() {}

    /** @OA\Post(path="/api/v1/doctor/auth/login", tags={"Doctor Auth"}, summary="Doctor login",
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"email","password"},
     *     @OA\Property(property="email", type="string", format="email"),
     *     @OA\Property(property="password", type="string", format="password"))),
     *   @OA\Response(response=200, description="Login successful", @OA\JsonContent(ref="#/components/schemas/AuthResponse")),
     *   @OA\Response(response=401, description="Invalid credentials", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))) */
    public function doctorLogin() {}

    /** @OA\Post(path="/api/v1/doctor/auth/logout", tags={"Doctor Auth"}, summary="Revoke doctor token", security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Logged out", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function doctorLogout() {}

    // ── DOCTOR PATIENTS ───────────────────────────────────────────────────────

    /** @OA\Get(path="/api/v1/doctor/patients", tags={"Doctor Patients"}, summary="List all assigned patients", security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Patient list", @OA\JsonContent(ref="#/components/schemas/PaginatedResponse"))) */
    public function doctorPatients() {}

    /** @OA\Get(path="/api/v1/doctor/patients/{id}", tags={"Doctor Patients"}, summary="Get full patient detail", security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="Patient detail", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function doctorPatientShow() {}

    /** @OA\Get(path="/api/v1/doctor/patients/{id}/vitals", tags={"Doctor Patients"}, summary="Get a patient's vitals", security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Parameter(name="type", in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="range", in="query", @OA\Schema(type="string", enum={"7d","30d","90d"})),
     *   @OA\Response(response=200, description="Vitals", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function doctorPatientVitals() {}

    /** @OA\Get(path="/api/v1/doctor/patients/{id}/alerts", tags={"Doctor Patients"}, summary="Get alerts for a patient", security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="Alerts", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function doctorPatientAlerts() {}

    /** @OA\Post(path="/api/v1/doctor/patients/{id}/notes", tags={"Doctor Patients"}, summary="Add a clinical note", security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"title","content"},
     *     @OA\Property(property="title", type="string", example="Post-visit note"),
     *     @OA\Property(property="content", type="string"))),
     *   @OA\Response(response=201, description="Note saved", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function doctorAddNote() {}

    /** @OA\Get(path="/api/v1/doctor/dashboard", tags={"Doctor Patients"}, summary="Summary metrics card", security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Dashboard metrics", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function doctorDashboard() {}

    // ── DOCTOR ALERTS ─────────────────────────────────────────────────────────

    /** @OA\Get(path="/api/v1/doctor/alerts", tags={"Doctor Alerts"}, summary="Get all patient alerts", security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"pending","acknowledged","resolved"})),
     *   @OA\Parameter(name="level",  in="query", @OA\Schema(type="integer", enum={1,2})),
     *   @OA\Response(response=200, description="Alerts", @OA\JsonContent(ref="#/components/schemas/PaginatedResponse"))) */
    public function doctorAlerts() {}

    /** @OA\Put(path="/api/v1/doctor/alerts/{id}/resolve", tags={"Doctor Alerts"}, summary="Mark alert as resolved", security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="Resolved", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function doctorResolveAlert() {}

    /** @OA\Put(path="/api/v1/doctor/alerts/{id}/assign", tags={"Doctor Alerts"}, summary="Assign alert to a doctor", security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"doctor_id"}, @OA\Property(property="doctor_id", type="integer", example=2))),
     *   @OA\Response(response=200, description="Assigned", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function doctorAssignAlert() {}

    // ── DOCTOR APPOINTMENTS ───────────────────────────────────────────────────

    /** @OA\Get(path="/api/v1/doctor/appointments", tags={"Doctor Appointments"}, summary="Get doctor's appointments", security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Appointments", @OA\JsonContent(ref="#/components/schemas/PaginatedResponse"))) */
    public function doctorAppointmentsIndex() {}

    /** @OA\Post(path="/api/v1/doctor/appointments", tags={"Doctor Appointments"}, summary="Schedule a new appointment", security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"patient_id","scheduled_at"},
     *     @OA\Property(property="patient_id", type="integer", example=5),
     *     @OA\Property(property="scheduled_at", type="string", format="date-time"),
     *     @OA\Property(property="notes", type="string"))),
     *   @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function doctorAppointmentStore() {}

    /** @OA\Put(path="/api/v1/doctor/appointments/{id}", tags={"Doctor Appointments"}, summary="Update appointment", security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(required=true, @OA\JsonContent(
     *     @OA\Property(property="status", type="string", enum={"upcoming","completed","cancelled"}),
     *     @OA\Property(property="scheduled_at", type="string", format="date-time"),
     *     @OA\Property(property="notes", type="string"))),
     *   @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function doctorAppointmentUpdate() {}

    // ── DOCTOR REPORTS ────────────────────────────────────────────────────────

    /** @OA\Get(path="/api/v1/doctor/reports/health-trends", tags={"Doctor Reports"}, summary="Vitals trends across assigned patients", security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="patient_id", in="query", @OA\Schema(type="integer")),
     *   @OA\Parameter(name="type", in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="range", in="query", @OA\Schema(type="string", enum={"7d","30d","90d"})),
     *   @OA\Response(response=200, description="Trend data", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function doctorHealthTrends() {}

    /** @OA\Get(path="/api/v1/doctor/reports/alerts-analytics", tags={"Doctor Reports"}, summary="Alert counts by type, level, status", security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Analytics", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))) */
    public function doctorAlertsAnalytics() {}

    /** @OA\Get(path="/api/v1/doctor/reports/export", tags={"Doctor Reports"}, summary="Export vitals as CSV", security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="format", in="query", @OA\Schema(type="string", enum={"csv"})),
     *   @OA\Parameter(name="patient_id", in="query", @OA\Schema(type="integer")),
     *   @OA\Parameter(name="range", in="query", @OA\Schema(type="string", enum={"7d","30d","90d"})),
     *   @OA\Response(response=200, description="CSV file", @OA\MediaType(mediaType="text/csv"))) */
    public function doctorExport() {}
}
