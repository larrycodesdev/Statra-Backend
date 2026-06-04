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
#[OA\Tag(name: 'Patient Auth',        description: 'Register, login (email or username), OTP password reset')]
#[OA\Tag(name: 'Patient Profile',     description: 'Personal info, medical info, emergency contacts, FCM token')]
#[OA\Tag(name: 'Patient Device',      description: 'Wearable device registration')]
#[OA\Tag(name: 'Patient Vitals',      description: 'Vitals sync from JCVital band and history')]
#[OA\Tag(name: 'Patient Alerts',      description: 'Alerts triggered by the alert engine')]
#[OA\Tag(name: 'Patient Logs',        description: 'Pain logs, medication logs, appointments')]
#[OA\Tag(name: 'Patient Settings',    description: 'Privacy and notification toggles')]
#[OA\Tag(name: 'Doctor Auth',         description: 'Doctor register and login')]
#[OA\Tag(name: 'Doctor Patients',     description: 'Patient management and dashboard')]
#[OA\Tag(name: 'Doctor Alerts',       description: 'Alert management — resolve, assign')]
#[OA\Tag(name: 'Doctor Appointments', description: 'Appointment scheduling')]
#[OA\Tag(name: 'Doctor Reports',      description: 'Health trends, analytics, CSV export')]
class MobileAppSpec
{
    // ── SCHEMAS ───────────────────────────────────────────────────────────────

    #[OA\Schema(schema: 'SuccessResponse', type: 'object', properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string',  example: 'Operation successful.'),
        new OA\Property(property: 'data',    nullable: true),
    ])]
    public function schemaSuccess() {}

    #[OA\Schema(schema: 'AuthResponse', type: 'object', properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string',  example: 'Login successful.'),
        new OA\Property(property: 'data', type: 'object', properties: [
            new OA\Property(property: 'token', type: 'string', example: '1|abc123'),
            new OA\Property(property: 'user',  type: 'object', properties: [
                new OA\Property(property: 'id',         type: 'integer', example: 1),
                new OA\Property(property: 'first_name', type: 'string',  example: 'Jane'),
                new OA\Property(property: 'last_name',  type: 'string',  example: 'Johnson'),
                new OA\Property(property: 'username',   type: 'string',  example: 'janej'),
                new OA\Property(property: 'email',      type: 'string',  example: 'jane@example.com'),
                new OA\Property(property: 'role',       type: 'string',  example: 'patient'),
            ]),
        ]),
    ])]
    public function schemaAuth() {}

    #[OA\Schema(schema: 'PaginatedResponse', type: 'object', properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data',    type: 'array',   items: new OA\Items(type: 'object')),
        new OA\Property(property: 'meta',    type: 'object',  properties: [
            new OA\Property(property: 'current_page', type: 'integer', example: 1),
            new OA\Property(property: 'per_page',     type: 'integer', example: 20),
            new OA\Property(property: 'total',        type: 'integer', example: 84),
            new OA\Property(property: 'last_page',    type: 'integer', example: 5),
        ]),
    ])]
    public function schemaPaginated() {}

    #[OA\Schema(schema: 'ErrorResponse', type: 'object', properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string',  example: 'Invalid credentials.'),
    ])]
    public function schemaError() {}

    #[OA\Schema(schema: 'ValidationError', type: 'object', properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string',  example: 'Validation failed.'),
        new OA\Property(property: 'errors',  type: 'object'),
    ])]
    public function schemaValidation() {}

    // ── PATIENT AUTH ──────────────────────────────────────────────────────────

    #[OA\Post(path: '/api/v1/patient/auth/register', summary: 'Register a new patient', tags: ['Patient Auth'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['first_name', 'last_name', 'email', 'password', 'password_confirmation'],
        properties: [
            new OA\Property(property: 'first_name', type: 'string', example: 'Jane'),
            new OA\Property(property: 'last_name',  type: 'string', example: 'Johnson'),
            new OA\Property(property: 'username',   type: 'string', example: 'janej', description: 'Auto-generated if omitted'),
            new OA\Property(property: 'email',      type: 'string', format: 'email'),
            new OA\Property(property: 'password',   type: 'string', format: 'password', example: 'Password1'),
            new OA\Property(property: 'password_confirmation', type: 'string', example: 'Password1'),
            new OA\Property(property: 'phone',      type: 'string', example: '+2348012345678'),
        ]
    ))]
    #[OA\Response(response: 201, description: 'Registered',       content: new OA\JsonContent(ref: '#/components/schemas/AuthResponse'))]
    #[OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function patientRegister() {}

    #[OA\Post(path: '/api/v1/patient/auth/login', summary: 'Login with email OR username', tags: ['Patient Auth'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['identifier', 'password'],
        properties: [
            new OA\Property(property: 'identifier', type: 'string', example: 'jane@example.com', description: 'Email or username'),
            new OA\Property(property: 'password',   type: 'string', format: 'password'),
            new OA\Property(property: 'fcm_token',  type: 'string', description: 'Refreshes push token on login'),
        ]
    ))]
    #[OA\Response(response: 200, description: 'Login successful',  content: new OA\JsonContent(ref: '#/components/schemas/AuthResponse'))]
    #[OA\Response(response: 401, description: 'Invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))]
    public function patientLogin() {}

    #[OA\Post(path: '/api/v1/patient/auth/social', summary: 'Sign in with Google / Apple / Facebook', tags: ['Patient Auth'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['provider', 'token'],
        properties: [
            new OA\Property(property: 'provider', type: 'string', enum: ['google', 'apple', 'facebook']),
            new OA\Property(property: 'token',    type: 'string', description: 'OAuth access token from provider SDK'),
        ]
    ))]
    #[OA\Response(response: 200, description: 'Social login successful', content: new OA\JsonContent(ref: '#/components/schemas/AuthResponse'))]
    #[OA\Response(response: 401, description: 'Invalid social token',    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))]
    public function patientSocial() {}

    #[OA\Post(path: '/api/v1/patient/auth/forgot-password', summary: 'Step 1 — Request 6-digit OTP via email', tags: ['Patient Auth'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['email'],
        properties: [new OA\Property(property: 'email', type: 'string', format: 'email')]
    ))]
    #[OA\Response(response: 200, description: 'OTP sent (always returns success to prevent email enumeration)')]
    public function patientForgotPassword() {}

    #[OA\Post(path: '/api/v1/patient/auth/verify-otp', summary: 'Step 2 — Verify OTP, receive reset_token', tags: ['Patient Auth'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['email', 'otp'],
        properties: [
            new OA\Property(property: 'email', type: 'string', format: 'email'),
            new OA\Property(property: 'otp',   type: 'string', example: '482916', description: '6-digit code from email'),
        ]
    ))]
    #[OA\Response(response: 200, description: 'OTP verified', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
            new OA\Property(property: 'reset_token', type: 'string', example: 'AbCdEf...'),
        ]),
    ]))]
    #[OA\Response(response: 400, description: 'Invalid or expired OTP', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))]
    public function patientVerifyOtp() {}

    #[OA\Post(path: '/api/v1/patient/auth/reset-password', summary: 'Step 3 — Set new password using reset_token', tags: ['Patient Auth'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['reset_token', 'password', 'password_confirmation'],
        properties: [
            new OA\Property(property: 'reset_token',           type: 'string'),
            new OA\Property(property: 'password',              type: 'string', format: 'password'),
            new OA\Property(property: 'password_confirmation', type: 'string'),
        ]
    ))]
    #[OA\Response(response: 200, description: 'Password reset',  content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    #[OA\Response(response: 400, description: 'Invalid token',   content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))]
    public function patientResetPassword() {}

    #[OA\Post(path: '/api/v1/patient/auth/logout', summary: 'Revoke current device token', security: [['bearerAuth' => []]], tags: ['Patient Auth'])]
    #[OA\Response(response: 200, description: 'Logged out', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function patientLogout() {}

    #[OA\Put(path: '/api/v1/patient/auth/change-password', summary: 'Change password while logged in', security: [['bearerAuth' => []]], tags: ['Patient Auth'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['current_password', 'password', 'password_confirmation'],
        properties: [
            new OA\Property(property: 'current_password',      type: 'string', format: 'password'),
            new OA\Property(property: 'password',              type: 'string', format: 'password'),
            new OA\Property(property: 'password_confirmation', type: 'string'),
        ]
    ))]
    #[OA\Response(response: 200, description: 'Password updated',           content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    #[OA\Response(response: 400, description: 'Current password incorrect', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))]
    public function patientChangePassword() {}

    // ── PATIENT PROFILE ───────────────────────────────────────────────────────

    #[OA\Get(path: '/api/v1/patient/profile', summary: 'Get full patient profile', security: [['bearerAuth' => []]], tags: ['Patient Profile'])]
    #[OA\Response(response: 200, description: 'Profile data', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
            new OA\Property(property: 'first_name',  type: 'string',  example: 'Jane'),
            new OA\Property(property: 'last_name',   type: 'string',  example: 'Johnson'),
            new OA\Property(property: 'username',    type: 'string',  example: 'jenny'),
            new OA\Property(property: 'email',       type: 'string'),
            new OA\Property(property: 'gender',      type: 'string',  example: 'female'),
            new OA\Property(property: 'age',         type: 'integer', example: 25),
            new OA\Property(property: 'genotype',    type: 'string',  example: 'SS'),
            new OA\Property(property: 'blood_type',  type: 'string',  example: 'O+'),
            new OA\Property(property: 'condition',   type: 'array',   items: new OA\Items(type: 'string', example: 'Leg Ulcer')),
            new OA\Property(property: 'emergency_contact', type: 'object', properties: [
                new OA\Property(property: 'name',         type: 'string'),
                new OA\Property(property: 'phone',        type: 'string'),
                new OA\Property(property: 'email',        type: 'string'),
                new OA\Property(property: 'address',      type: 'string'),
                new OA\Property(property: 'relationship', type: 'string'),
            ]),
            new OA\Property(property: 'care_team', type: 'array', items: new OA\Items(type: 'object')),
        ]),
    ]))]
    public function patientGetProfile() {}

    #[OA\Put(path: '/api/v1/patient/profile', summary: 'Update personal info', security: [['bearerAuth' => []]], tags: ['Patient Profile'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'first_name',    type: 'string'),
        new OA\Property(property: 'last_name',     type: 'string'),
        new OA\Property(property: 'username',      type: 'string'),
        new OA\Property(property: 'phone',         type: 'string'),
        new OA\Property(property: 'avatar',        type: 'string'),
        new OA\Property(property: 'gender',        type: 'string', enum: ['male', 'female', 'other']),
        new OA\Property(property: 'date_of_birth', type: 'string', format: 'date', example: '2001-03-15'),
    ]))]
    #[OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function patientUpdateProfile() {}

    #[OA\Put(path: '/api/v1/patient/profile/medical-info', summary: 'Update genotype, blood type, conditions', security: [['bearerAuth' => []]], tags: ['Patient Profile'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'genotype',   type: 'string', enum: ['SS', 'SC', 'SB', 'SD', 'SE', 'SO', 'other']),
        new OA\Property(property: 'blood_type', type: 'string', example: 'O+'),
        new OA\Property(property: 'condition',  type: 'array',  items: new OA\Items(
            type: 'string',
            enum: ['Leg Ulcer', 'Acute chest syndrome', 'Stroke', 'Organ damage', 'Vision Problem', 'Priapism']
        )),
    ]))]
    #[OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function patientUpdateMedical() {}

    #[OA\Put(path: '/api/v1/patient/profile/emergency-contacts', summary: 'Update emergency contact', security: [['bearerAuth' => []]], tags: ['Patient Profile'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['name', 'phone'],
        properties: [
            new OA\Property(property: 'name',         type: 'string', example: 'Joseph Johnson'),
            new OA\Property(property: 'phone',        type: 'string', example: '+234 80 333 57163'),
            new OA\Property(property: 'email',        type: 'string'),
            new OA\Property(property: 'address',      type: 'string'),
            new OA\Property(property: 'relationship', type: 'string', example: 'Brother'),
        ]
    ))]
    #[OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function patientUpdateEmergency() {}

    #[OA\Put(path: '/api/v1/patient/profile/fcm-token', summary: 'Refresh FCM push token — call on every app launch', security: [['bearerAuth' => []]], tags: ['Patient Profile'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['fcm_token'],
        properties: [new OA\Property(property: 'fcm_token', type: 'string', example: 'fGT7s_...')]
    ))]
    #[OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function patientUpdateFcm() {}

    // ── PATIENT SETTINGS ──────────────────────────────────────────────────────

    #[OA\Get(path: '/api/v1/patient/settings', summary: 'Get privacy and notification settings', security: [['bearerAuth' => []]], tags: ['Patient Settings'])]
    #[OA\Response(response: 200, description: 'Settings', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
            new OA\Property(property: 'allow_doctor_view_records', type: 'boolean', example: true),
            new OA\Property(property: 'allow_doctor_view_data',    type: 'boolean', example: true),
            new OA\Property(property: 'share_symptom_pain_data',   type: 'boolean', example: true),
            new OA\Property(property: 'share_medication_records',  type: 'boolean', example: true),
            new OA\Property(property: 'reminder_enabled',          type: 'boolean', example: true),
            new OA\Property(property: 'smart_alert_enabled',       type: 'boolean', example: true),
        ]),
    ]))]
    public function patientGetSettings() {}

    #[OA\Put(path: '/api/v1/patient/settings', summary: 'Update privacy/notification toggles', security: [['bearerAuth' => []]], tags: ['Patient Settings'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'allow_doctor_view_records', type: 'boolean'),
        new OA\Property(property: 'allow_doctor_view_data',    type: 'boolean'),
        new OA\Property(property: 'share_symptom_pain_data',   type: 'boolean'),
        new OA\Property(property: 'share_medication_records',  type: 'boolean'),
        new OA\Property(property: 'reminder_enabled',          type: 'boolean'),
        new OA\Property(property: 'smart_alert_enabled',       type: 'boolean'),
    ]))]
    #[OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function patientUpdateSettings() {}

    // ── PATIENT DEVICE + VITALS ───────────────────────────────────────────────

    #[OA\Post(path: '/api/v1/patient/device/register', summary: 'Register or update the JCVital wearable', security: [['bearerAuth' => []]], tags: ['Patient Device'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['device_id', 'platform'],
        properties: [
            new OA\Property(property: 'device_id',        type: 'string', example: 'JCV-2208A-001'),
            new OA\Property(property: 'device_model',     type: 'string', example: 'JCVital 2208A'),
            new OA\Property(property: 'firmware_version', type: 'string', example: '2.1.4'),
            new OA\Property(property: 'platform',         type: 'string', enum: ['android', 'ios']),
        ]
    ))]
    #[OA\Response(response: 201, description: 'Device registered', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function deviceRegister() {}

    #[OA\Get(path: '/api/v1/patient/device/status', summary: 'Get all registered devices', security: [['bearerAuth' => []]], tags: ['Patient Device'])]
    #[OA\Response(response: 200, description: 'Device list', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function deviceStatus() {}

    #[OA\Post(path: '/api/v1/patient/vitals/sync', summary: 'Sync a batch of readings — 202 accepted, processed async', security: [['bearerAuth' => []]], tags: ['Patient Vitals'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['device_id', 'readings'],
        properties: [
            new OA\Property(property: 'device_id', type: 'string', example: 'JCV-2208A-001'),
            new OA\Property(property: 'readings',  type: 'array',  items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'type',        type: 'string', enum: ['heart_rate', 'spo2', 'temperature', 'blood_pressure', 'steps', 'sleep_state', 'hrv']),
                    new OA\Property(property: 'value',       description: 'Number, or {systolic,diastolic} for blood_pressure'),
                    new OA\Property(property: 'unit',        type: 'string', example: 'bpm'),
                    new OA\Property(property: 'recorded_at', type: 'string', format: 'date-time'),
                ]
            )),
        ]
    ))]
    #[OA\Response(response: 202, description: 'Accepted — batch queued for alert processing')]
    public function vitalsSync() {}

    #[OA\Get(path: '/api/v1/patient/vitals', summary: 'Get vitals history', security: [['bearerAuth' => []]], tags: ['Patient Vitals'])]
    #[OA\Parameter(name: 'type',  in: 'query', schema: new OA\Schema(type: 'string', enum: ['heart_rate', 'spo2', 'temperature', 'blood_pressure', 'steps', 'sleep_state', 'hrv']))]
    #[OA\Parameter(name: 'range', in: 'query', schema: new OA\Schema(type: 'string', enum: ['7d', '30d', '90d']))]
    #[OA\Response(response: 200, description: 'Vitals', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))]
    public function vitalsIndex() {}

    // ── PATIENT ALERTS + LOGS ─────────────────────────────────────────────────

    #[OA\Get(path: '/api/v1/patient/alerts', summary: 'Get alerts for this patient', security: [['bearerAuth' => []]], tags: ['Patient Alerts'])]
    #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'acknowledged', 'resolved']))]
    #[OA\Response(response: 200, description: 'Alert list', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))]
    public function patientAlerts() {}

    #[OA\Post(path: '/api/v1/patient/pain-log', summary: 'Log a pain episode', security: [['bearerAuth' => []]], tags: ['Patient Logs'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['pain_level', 'logged_at'],
        properties: [
            new OA\Property(property: 'pain_level', type: 'integer', minimum: 1, maximum: 10, example: 7),
            new OA\Property(property: 'location',   type: 'array',   items: new OA\Items(type: 'string', example: 'chest')),
            new OA\Property(property: 'notes',      type: 'string'),
            new OA\Property(property: 'logged_at',  type: 'string',  format: 'date-time'),
        ]
    ))]
    #[OA\Response(response: 201, description: 'Saved', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function painLogStore() {}

    #[OA\Get(path: '/api/v1/patient/pain-log', summary: 'Get pain log history', security: [['bearerAuth' => []]], tags: ['Patient Logs'])]
    #[OA\Response(response: 200, description: 'Pain logs', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))]
    public function painLogIndex() {}

    #[OA\Post(path: '/api/v1/patient/medication-log', summary: 'Log a medication entry', security: [['bearerAuth' => []]], tags: ['Patient Logs'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['medication_name', 'dosage', 'scheduled_at', 'status'],
        properties: [
            new OA\Property(property: 'medication_name', type: 'string', example: 'Hydroxyurea 500mg'),
            new OA\Property(property: 'dosage',          type: 'string', example: '1 tablet'),
            new OA\Property(property: 'scheduled_at',    type: 'string', format: 'date-time'),
            new OA\Property(property: 'taken_at',        type: 'string', format: 'date-time', nullable: true),
            new OA\Property(property: 'status',          type: 'string', enum: ['taken', 'missed', 'pending']),
        ]
    ))]
    #[OA\Response(response: 201, description: 'Saved', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function medicationLogStore() {}

    #[OA\Get(path: '/api/v1/patient/medication-log', summary: 'Get medication log history', security: [['bearerAuth' => []]], tags: ['Patient Logs'])]
    #[OA\Response(response: 200, description: 'Logs', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))]
    public function medicationLogIndex() {}

    #[OA\Get(path: '/api/v1/patient/appointments', summary: 'Get appointments', security: [['bearerAuth' => []]], tags: ['Patient Logs'])]
    #[OA\Response(response: 200, description: 'Appointments', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))]
    public function patientAppointments() {}

    // ── DOCTOR AUTH ───────────────────────────────────────────────────────────

    #[OA\Post(path: '/api/v1/doctor/auth/register', summary: 'Register a doctor account', tags: ['Doctor Auth'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['name', 'email', 'password', 'password_confirmation'],
        properties: [
            new OA\Property(property: 'name',                  type: 'string', example: 'Dr. Adewale Okafor'),
            new OA\Property(property: 'email',                 type: 'string', format: 'email'),
            new OA\Property(property: 'password',              type: 'string', format: 'password'),
            new OA\Property(property: 'password_confirmation', type: 'string'),
            new OA\Property(property: 'hospital_name',         type: 'string', example: 'Lagos General Hospital'),
            new OA\Property(property: 'department',            type: 'string', example: 'Haematology'),
            new OA\Property(property: 'specialisation',        type: 'string', example: 'Sickle Cell Disease'),
            new OA\Property(property: 'license_number',        type: 'string', example: 'MDCN-12345'),
        ]
    ))]
    #[OA\Response(response: 201, description: 'Registered', content: new OA\JsonContent(ref: '#/components/schemas/AuthResponse'))]
    public function doctorRegister() {}

    #[OA\Post(path: '/api/v1/doctor/auth/login', summary: 'Doctor login', tags: ['Doctor Auth'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['email', 'password'],
        properties: [
            new OA\Property(property: 'email',    type: 'string', format: 'email'),
            new OA\Property(property: 'password', type: 'string', format: 'password'),
        ]
    ))]
    #[OA\Response(response: 200, description: 'Login successful',   content: new OA\JsonContent(ref: '#/components/schemas/AuthResponse'))]
    #[OA\Response(response: 401, description: 'Invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))]
    public function doctorLogin() {}

    #[OA\Post(path: '/api/v1/doctor/auth/logout', summary: 'Revoke doctor token', security: [['bearerAuth' => []]], tags: ['Doctor Auth'])]
    #[OA\Response(response: 200, description: 'Logged out', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function doctorLogout() {}

    // ── DOCTOR PATIENTS ───────────────────────────────────────────────────────

    #[OA\Get(path: '/api/v1/doctor/patients', summary: 'List all assigned patients', security: [['bearerAuth' => []]], tags: ['Doctor Patients'])]
    #[OA\Response(response: 200, description: 'Patient list', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))]
    public function doctorPatients() {}

    #[OA\Get(path: '/api/v1/doctor/patients/{id}', summary: 'Get full patient detail', security: [['bearerAuth' => []]], tags: ['Doctor Patients'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Patient detail', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function doctorPatientShow() {}

    #[OA\Get(path: '/api/v1/doctor/patients/{id}/vitals', summary: "Get a patient's vitals", security: [['bearerAuth' => []]], tags: ['Doctor Patients'])]
    #[OA\Parameter(name: 'id',    in: 'path',  required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'type',  in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'range', in: 'query', schema: new OA\Schema(type: 'string', enum: ['7d', '30d', '90d']))]
    #[OA\Response(response: 200, description: 'Vitals', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function doctorPatientVitals() {}

    #[OA\Get(path: '/api/v1/doctor/patients/{id}/alerts', summary: 'Get alerts for a patient', security: [['bearerAuth' => []]], tags: ['Doctor Patients'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Alerts', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function doctorPatientAlerts() {}

    #[OA\Post(path: '/api/v1/doctor/patients/{id}/notes', summary: 'Add a clinical note', security: [['bearerAuth' => []]], tags: ['Doctor Patients'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['title', 'content'],
        properties: [
            new OA\Property(property: 'title',   type: 'string', example: 'Post-visit note'),
            new OA\Property(property: 'content', type: 'string'),
        ]
    ))]
    #[OA\Response(response: 201, description: 'Note saved', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function doctorAddNote() {}

    #[OA\Get(path: '/api/v1/doctor/dashboard', summary: 'Summary metrics card', security: [['bearerAuth' => []]], tags: ['Doctor Patients'])]
    #[OA\Response(response: 200, description: 'Dashboard metrics', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function doctorDashboard() {}

    // ── DOCTOR ALERTS ─────────────────────────────────────────────────────────

    #[OA\Get(path: '/api/v1/doctor/alerts', summary: 'Get all patient alerts', security: [['bearerAuth' => []]], tags: ['Doctor Alerts'])]
    #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string',  enum: ['pending', 'acknowledged', 'resolved']))]
    #[OA\Parameter(name: 'level',  in: 'query', schema: new OA\Schema(type: 'integer', enum: [1, 2]))]
    #[OA\Response(response: 200, description: 'Alerts', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))]
    public function doctorAlerts() {}

    #[OA\Put(path: '/api/v1/doctor/alerts/{id}/resolve', summary: 'Mark alert as resolved', security: [['bearerAuth' => []]], tags: ['Doctor Alerts'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Resolved', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function doctorResolveAlert() {}

    #[OA\Put(path: '/api/v1/doctor/alerts/{id}/assign', summary: 'Assign alert to a doctor', security: [['bearerAuth' => []]], tags: ['Doctor Alerts'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['doctor_id'],
        properties: [new OA\Property(property: 'doctor_id', type: 'integer', example: 2)]
    ))]
    #[OA\Response(response: 200, description: 'Assigned', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function doctorAssignAlert() {}

    // ── DOCTOR APPOINTMENTS ───────────────────────────────────────────────────

    #[OA\Get(path: '/api/v1/doctor/appointments', summary: "Get doctor's appointments", security: [['bearerAuth' => []]], tags: ['Doctor Appointments'])]
    #[OA\Response(response: 200, description: 'Appointments', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))]
    public function doctorAppointmentsIndex() {}

    #[OA\Post(path: '/api/v1/doctor/appointments', summary: 'Schedule a new appointment', security: [['bearerAuth' => []]], tags: ['Doctor Appointments'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['patient_id', 'scheduled_at'],
        properties: [
            new OA\Property(property: 'patient_id',   type: 'integer', example: 5),
            new OA\Property(property: 'scheduled_at', type: 'string',  format: 'date-time'),
            new OA\Property(property: 'notes',        type: 'string'),
        ]
    ))]
    #[OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function doctorAppointmentStore() {}

    #[OA\Put(path: '/api/v1/doctor/appointments/{id}', summary: 'Update appointment', security: [['bearerAuth' => []]], tags: ['Doctor Appointments'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'status',       type: 'string', enum: ['upcoming', 'completed', 'cancelled']),
        new OA\Property(property: 'scheduled_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'notes',        type: 'string'),
    ]))]
    #[OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function doctorAppointmentUpdate() {}

    // ── DOCTOR REPORTS ────────────────────────────────────────────────────────

    #[OA\Get(path: '/api/v1/doctor/reports/health-trends', summary: 'Vitals trends across assigned patients', security: [['bearerAuth' => []]], tags: ['Doctor Reports'])]
    #[OA\Parameter(name: 'patient_id', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'type',       in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'range',      in: 'query', schema: new OA\Schema(type: 'string', enum: ['7d', '30d', '90d']))]
    #[OA\Response(response: 200, description: 'Trend data', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function doctorHealthTrends() {}

    #[OA\Get(path: '/api/v1/doctor/reports/alerts-analytics', summary: 'Alert counts by type, level, status', security: [['bearerAuth' => []]], tags: ['Doctor Reports'])]
    #[OA\Response(response: 200, description: 'Analytics', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    public function doctorAlertsAnalytics() {}

    #[OA\Get(path: '/api/v1/doctor/reports/export', summary: 'Export vitals as CSV', security: [['bearerAuth' => []]], tags: ['Doctor Reports'])]
    #[OA\Parameter(name: 'format',     in: 'query', schema: new OA\Schema(type: 'string',  enum: ['csv']))]
    #[OA\Parameter(name: 'patient_id', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'range',      in: 'query', schema: new OA\Schema(type: 'string',  enum: ['7d', '30d', '90d']))]
    #[OA\Response(response: 200, description: 'CSV file', content: new OA\MediaType(mediaType: 'text/csv'))]
    public function doctorExport() {}
}
