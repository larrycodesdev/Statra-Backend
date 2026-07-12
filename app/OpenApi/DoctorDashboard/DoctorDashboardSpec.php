<?php

namespace App\OpenApi\DoctorDashboard;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'STATRA — Doctor Dashboard API',
    version: '1.0.0',
    description: <<<'MD'
REST API for the STATRA Doctor Dashboard web app.

---

## How to use this docs

1. Click **Authorize** (top right) and paste your Bearer token.
2. All endpoints auto-include the token after that.
3. To get a token: expand **Auth → POST /auth/login**, click **Try it out**, fill the body, hit **Execute**.

---

## Standard response envelope

Every response wraps data in this shape:

```json
{ "success": true, "message": "...", "data": { ... } }
```

Paginated lists add a `meta` key:
```json
{ "success": true, "data": [...], "meta": { "current_page": 1, "per_page": 20, "total": 84, "last_page": 5 } }
```

Errors:
```json
{ "success": false, "message": "Human-readable message." }
```

Validation errors (422):
```json
{ "success": false, "message": "Validation failed.", "errors": { "field": ["reason"] } }
```

---

## Role system

All data queries are scoped **automatically** by the backend based on the token's role. You don't pass a role param — the backend reads it from the token.

| Role | What they can query |
|---|---|
| `superadmin` | All patients across all hospitals |
| `admin` | All patients + staff within their hospital |
| `doctor` | Only patients where they are the assigned doctor |
| `staff` | Same patient scope as admin but cannot create/edit |

### Write restrictions (backend returns 403 for these)

| Action | doctor | admin | staff | superadmin |
|---|---|---|---|---|
| POST /patients/{id}/notes | ✅ | ✅ | ❌ | ✅ |
| POST/DELETE /appointments | ✅ | ✅ | ❌ | ✅ |
| PUT /alerts/{id}/assign | ✅ | ✅ | ❌ | ✅ |
| PUT /alerts/{id}/resolve | ✅ | ✅ | ✅ | ✅ |
| PATCH /staff/{id}/approve | ❌ | ✅ | ❌ | ✅ |
| POST /patients | ❌ | ✅ | ❌ | ✅ |

---

## Approval flow

Doctors and staff must be approved before they can use the API:

1. `POST /auth/register` with `role: "doctor"` or `role: "staff"`
2. Response has `approval_status: "pending"` — the token is issued but all protected routes return **403 Account pending approval**
3. An admin calls `PATCH /staff/{id}/approve` with `action: "approve"`
4. The doctor/staff can now access all protected routes

---

## Key field reference

### healthScore
Derived from each patient's personal 28-day vital baseline. Never population-compared.

| Value | Status | Colour |
|---|---|---|
| `85` | Stable | Green |
| `65` | Watch | Yellow |
| `45` | Elevated | Orange |
| `20` | Urgent | Red |
| `null` | Calibrating — less than 28 days of data | Grey |

### alertLevel
Highest-priority **pending** alert on the patient. Resolved alerts do not affect this.

| Value | Meaning | Colour |
|---|---|---|
| `"L1"` | Critical alert pending | Red |
| `"L2"` | Warning alert pending | Amber |
| `"L3"` | No pending alerts | Green |

### displayId
Human-readable ID shown in the UI: `SCW-001`, `SCW-042`, etc. Always display this instead of the numeric `id`. Use numeric `id` only in URL path params.
MD,
    contact: new OA\Contact(email: 'hello@statra.health')
)]
#[OA\Server(url: 'https://statrawebapp-f7gaa7bghfczhmf7.centralus-01.azurewebsites.net', description: 'Production')]
#[OA\Server(url: 'http://localhost:8000', description: 'Local dev')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', scheme: 'bearer', bearerFormat: 'Sanctum')]

#[OA\Tag(name: 'Auth',         description: '**Start here.** Use POST /auth/login to get a token, then click Authorize at the top of the page.')]
#[OA\Tag(name: 'Dashboard',    description: 'Summary stats for the dashboard home page. All counts are scoped by your role automatically.')]
#[OA\Tag(name: 'Patients',     description: 'Patient list, detail, vitals snapshot, medications, and medical notes. Vitals return a typed object — not a flat array.')]
#[OA\Tag(name: 'Alerts',       description: 'Alert feed and management. Levels are returned as L1/L2/L3 strings, not integers.')]
#[OA\Tag(name: 'Appointments', description: 'Appointment scheduling. Includes a `type` free-text field. Staff role cannot create or delete.')]
#[OA\Tag(name: 'Staff',        description: '**Admin and superadmin only.** Lists doctors/staff and handles the approve/reject flow for pending accounts.')]
#[OA\Tag(name: 'Reports',      description: 'Aggregated data for reports page. Last endpoint exports a CSV file.')]

class DoctorDashboardSpec
{
    // ═══════════════════════════════════════════════════════════════════════════
    // SCHEMAS
    // ═══════════════════════════════════════════════════════════════════════════

    #[OA\Schema(schema: 'SuccessResponse', type: 'object', properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string',  example: 'Operation successful.'),
        new OA\Property(property: 'data',    nullable: true),
    ])]
    public function schemaSuccess() {}

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

    #[OA\Schema(schema: 'PaginatedMeta', type: 'object', properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'per_page',     type: 'integer', example: 20),
        new OA\Property(property: 'total',        type: 'integer', example: 84),
        new OA\Property(property: 'last_page',    type: 'integer', example: 5),
    ])]
    public function schemaMeta() {}

    #[OA\Schema(schema: 'AuthResponse', type: 'object', properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string',  example: 'Login successful.'),
        new OA\Property(property: 'data', type: 'object', properties: [
            new OA\Property(property: 'token',          type: 'string',  example: '1|abc123'),
            new OA\Property(property: 'approval_status', type: 'string', enum: ['pending', 'approved'], example: 'approved'),
            new OA\Property(property: 'user', type: 'object', properties: [
                new OA\Property(property: 'id',       type: 'integer', example: 7),
                new OA\Property(property: 'uuid',     type: 'string',  example: 'a1b2c3d4-...'),
                new OA\Property(property: 'fullName', type: 'string',  example: 'Dr. Amara Osei'),
                new OA\Property(property: 'initials', type: 'string',  example: 'AO'),
                new OA\Property(property: 'email',    type: 'string',  example: 'amara@hospital.org'),
                new OA\Property(property: 'role',     type: 'string',  enum: ['doctor', 'admin', 'staff', 'superadmin'], example: 'doctor'),
            ]),
        ]),
    ])]
    public function schemaAuth() {}

    #[OA\Schema(schema: 'PatientCard', type: 'object', description: 'Compact patient row for list views', properties: [
        new OA\Property(property: 'id',          type: 'integer', example: 12),
        new OA\Property(property: 'displayId',   type: 'string',  example: 'SCW-012', description: 'Human-readable ID shown in the UI'),
        new OA\Property(property: 'name',        type: 'string',  example: 'James Kwame'),
        new OA\Property(property: 'avatar',      type: 'string',  nullable: true, example: 'https://...'),
        new OA\Property(property: 'age',         type: 'integer', nullable: true, example: 24),
        new OA\Property(property: 'gender',      type: 'string',  nullable: true, example: 'male'),
        new OA\Property(property: 'genotype',    type: 'string',  nullable: true, example: 'SS'),
        new OA\Property(property: 'ward',        type: 'string',  nullable: true, example: 'Haematology Ward A'),
        new OA\Property(property: 'admittedAt',  type: 'string',  nullable: true, example: '2026-06-01'),
        new OA\Property(property: 'healthScore', type: 'integer', nullable: true, example: 65,
            description: 'Composite score: urgent=20, elevated=45, watch=65, stable=85. null during 28-day calibration.'),
        new OA\Property(property: 'alertLevel',  type: 'string',  enum: ['L1', 'L2', 'L3'], example: 'L2',
            description: 'L1=critical pending alert, L2=warning pending alert, L3=no active alert'),
        new OA\Property(property: 'calibrationStatus', type: 'string', enum: ['calibrating', 'active'], nullable: true, example: 'active'),
        new OA\Property(property: 'assignedDoctor', nullable: true, type: 'object', properties: [
            new OA\Property(property: 'id',   type: 'integer', example: 3),
            new OA\Property(property: 'name', type: 'string',  example: 'Dr. Kofi Mensah'),
        ]),
        new OA\Property(property: 'assignedNurse', nullable: true, type: 'object', properties: [
            new OA\Property(property: 'id',   type: 'integer', example: 5),
            new OA\Property(property: 'name', type: 'string',  example: 'Nurse Abena Boateng'),
        ]),
    ])]
    public function schemaPatientCard() {}

    #[OA\Schema(schema: 'AlertItem', type: 'object', properties: [
        new OA\Property(property: 'id',        type: 'integer', example: 44),
        new OA\Property(property: 'level',     type: 'string',  enum: ['L1', 'L2', 'L3'], example: 'L1'),
        new OA\Property(property: 'rawLevel',  type: 'integer', enum: [1, 2], example: 1, description: 'Backend level: 1=critical, 2=warning'),
        new OA\Property(property: 'type',      type: 'string',  example: 'fever'),
        new OA\Property(property: 'message',   type: 'string',  example: 'Temperature 38.7°C exceeds threshold'),
        new OA\Property(property: 'status',    type: 'string',  enum: ['pending', 'acknowledged', 'resolved'], example: 'pending'),
        new OA\Property(property: 'resolvedAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'createdAt', type: 'string',  format: 'date-time', example: '2026-07-12T08:31:00Z'),
        new OA\Property(property: 'patient',   nullable: true, type: 'object', properties: [
            new OA\Property(property: 'id',        type: 'integer', example: 12),
            new OA\Property(property: 'displayId', type: 'string',  example: 'SCW-012'),
            new OA\Property(property: 'name',      type: 'string',  example: 'James Kwame'),
            new OA\Property(property: 'avatar',    type: 'string',  nullable: true),
        ]),
        new OA\Property(property: 'vital', nullable: true, type: 'object', properties: [
            new OA\Property(property: 'type',       type: 'string', example: 'temperature'),
            new OA\Property(property: 'value',      example: 38.7),
            new OA\Property(property: 'unit',       type: 'string', example: '°C'),
            new OA\Property(property: 'recordedAt', type: 'string', format: 'date-time'),
        ]),
    ])]
    public function schemaAlertItem() {}

    #[OA\Schema(schema: 'AppointmentItem', type: 'object', properties: [
        new OA\Property(property: 'id',          type: 'integer', example: 9),
        new OA\Property(property: 'scheduledAt', type: 'string',  format: 'date-time', example: '2026-07-20T10:00:00Z'),
        new OA\Property(property: 'type',        type: 'string',  nullable: true, example: 'Follow-up'),
        new OA\Property(property: 'status',      type: 'string',  enum: ['upcoming', 'completed', 'cancelled'], example: 'upcoming'),
        new OA\Property(property: 'notes',       type: 'string',  nullable: true),
        new OA\Property(property: 'patient',     nullable: true, type: 'object', properties: [
            new OA\Property(property: 'id',        type: 'integer', example: 12),
            new OA\Property(property: 'displayId', type: 'string',  example: 'SCW-012'),
            new OA\Property(property: 'name',      type: 'string',  example: 'James Kwame'),
            new OA\Property(property: 'avatar',    type: 'string',  nullable: true),
        ]),
    ])]
    public function schemaAppointment() {}

    // ═══════════════════════════════════════════════════════════════════════════
    // AUTH
    // ═══════════════════════════════════════════════════════════════════════════

    #[OA\Post(
        path: '/api/v1/doctor/auth/register',
        summary: 'Register as doctor or staff',
        description: 'Creates the account with `approval_status: pending`. The returned token cannot access protected routes until an admin approves the account.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['role', 'first_name', 'last_name', 'email', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'role',           type: 'string', enum: ['doctor', 'staff'], example: 'doctor'),
                new OA\Property(property: 'first_name',     type: 'string', example: 'Amara'),
                new OA\Property(property: 'last_name',      type: 'string', example: 'Osei'),
                new OA\Property(property: 'email',          type: 'string', format: 'email', example: 'amara@hospital.org'),
                new OA\Property(property: 'password',       type: 'string', format: 'password', example: 'Secret1234'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'Secret1234'),
                new OA\Property(property: 'phone',          type: 'string', nullable: true, example: '+233241234567'),
                new OA\Property(property: 'hospital_id',   type: 'integer', nullable: true, example: 1),
                new OA\Property(property: 'department',    type: 'string',  nullable: true, example: 'Haematology'),
                new OA\Property(property: 'specialisation', type: 'string', nullable: true, example: 'Sickle Cell Disease'),
                new OA\Property(property: 'license_number', type: 'string', nullable: true, example: 'GH-MED-00123'),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Registered (pending approval)', content: new OA\JsonContent(ref: '#/components/schemas/AuthResponse')),
            new OA\Response(response: 422, description: 'Validation error',              content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function register() {}

    #[OA\Post(
        path: '/api/v1/doctor/auth/login',
        summary: 'Login',
        description: 'Returns a Sanctum token. Check `approval_status` — if `pending`, the token cannot access protected routes.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email',    type: 'string', format: 'email', example: 'amara@hospital.org'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Secret1234'),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Login successful', content: new OA\JsonContent(ref: '#/components/schemas/AuthResponse')),
            new OA\Response(response: 401, description: 'Invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function login() {}

    #[OA\Get(
        path: '/api/v1/doctor/auth/me',
        summary: 'Get current user profile',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Current user', content: new OA\JsonContent(
                type: 'object', properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', type: 'object', properties: [
                        new OA\Property(property: 'id',       type: 'integer', example: 7),
                        new OA\Property(property: 'uuid',     type: 'string',  example: 'a1b2c3...'),
                        new OA\Property(property: 'fullName', type: 'string',  example: 'Dr. Amara Osei'),
                        new OA\Property(property: 'initials', type: 'string',  example: 'AO'),
                        new OA\Property(property: 'email',    type: 'string',  example: 'amara@hospital.org'),
                        new OA\Property(property: 'phone',    type: 'string',  nullable: true),
                        new OA\Property(property: 'avatar',   type: 'string',  nullable: true),
                        new OA\Property(property: 'role',     type: 'string',  enum: ['doctor', 'admin', 'staff', 'superadmin']),
                        new OA\Property(property: 'hospital', nullable: true, type: 'object', properties: [
                            new OA\Property(property: 'id',   type: 'integer', example: 1),
                            new OA\Property(property: 'name', type: 'string',  example: 'Korle Bu Teaching Hospital'),
                        ]),
                    ]),
                ]
            )),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Account pending approval', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function me() {}

    #[OA\Post(
        path: '/api/v1/doctor/auth/logout',
        summary: 'Logout (revoke token)',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logged out', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function logout() {}

    #[OA\Post(
        path: '/api/v1/doctor/auth/forgot-password',
        summary: 'Send password reset link',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email'],
            properties: [new OA\Property(property: 'email', type: 'string', format: 'email')]
        )),
        responses: [new OA\Response(response: 200, description: 'Reset link sent (if email exists)', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function forgotPassword() {}

    #[OA\Post(
        path: '/api/v1/doctor/auth/reset-password',
        summary: 'Reset password with token',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['token', 'email', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'token',                 type: 'string'),
                new OA\Property(property: 'email',                 type: 'string', format: 'email'),
                new OA\Property(property: 'password',              type: 'string', format: 'password'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Password reset',        content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: 'Invalid/expired token', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function resetPassword() {}

    // ═══════════════════════════════════════════════════════════════════════════
    // DASHBOARD
    // ═══════════════════════════════════════════════════════════════════════════

    #[OA\Get(
        path: '/api/v1/doctor/dashboard/summary',
        summary: 'Dashboard summary stats',
        description: 'All counts are scoped by role: doctor→assigned patients, admin→hospital, superadmin→all.',
        tags: ['Dashboard'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Summary', content: new OA\JsonContent(
                type: 'object', properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', type: 'object', properties: [
                        new OA\Property(property: 'totalPatients', type: 'integer', example: 48),
                        new OA\Property(property: 'alertLevelCounts', type: 'object', properties: [
                            new OA\Property(property: 'L1', type: 'integer', example: 2, description: 'Critical pending alerts'),
                            new OA\Property(property: 'L2', type: 'integer', example: 5, description: 'Warning pending alerts'),
                            new OA\Property(property: 'L3', type: 'integer', example: 41, description: 'Patients with no active alert'),
                        ]),
                        new OA\Property(property: 'pendingAlerts', type: 'integer', example: 7),
                        new OA\Property(property: 'resolvedToday', type: 'integer', example: 3),
                        new OA\Property(property: 'readmissionRate', nullable: true, description: 'Placeholder — requires discharge tracking'),
                        new OA\Property(property: 'lastUpdatedAt', type: 'string', format: 'date-time'),
                    ]),
                ]
            )),
        ]
    )]
    public function dashboardSummary() {}

    #[OA\Get(
        path: '/api/v1/doctor/dashboard/alert-feed',
        summary: 'Paginated alert feed',
        tags: ['Dashboard'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'acknowledged', 'resolved'])),
            new OA\Parameter(name: 'level',  in: 'query', required: false, schema: new OA\Schema(type: 'integer', enum: [1, 2])),
            new OA\Parameter(name: 'page',   in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Alert feed', content: new OA\JsonContent(
                type: 'object', properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data',    type: 'array',   items: new OA\Items(ref: '#/components/schemas/AlertItem')),
                    new OA\Property(property: 'meta',    ref: '#/components/schemas/PaginatedMeta'),
                ]
            )),
        ]
    )]
    public function dashboardAlertFeed() {}

    #[OA\Get(
        path: '/api/v1/doctor/dashboard/weekly-alert-volume',
        summary: '7-day alert volume trend',
        tags: ['Dashboard'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Weekly volume', content: new OA\JsonContent(
                type: 'object', properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', type: 'object', properties: [
                        new OA\Property(property: 'volume', type: 'array', items: new OA\Items(
                            type: 'object', properties: [
                                new OA\Property(property: 'date',  type: 'string', example: '2026-07-06'),
                                new OA\Property(property: 'label', type: 'string', example: 'Sun'),
                                new OA\Property(property: 'count', type: 'integer', example: 4),
                            ]
                        )),
                    ]),
                ]
            )),
        ]
    )]
    public function dashboardWeeklyVolume() {}

    // ═══════════════════════════════════════════════════════════════════════════
    // PATIENTS
    // ═══════════════════════════════════════════════════════════════════════════

    #[OA\Get(
        path: '/api/v1/doctor/patients',
        summary: 'List patients (role-scoped)',
        description: 'Returns paginated patient cards. The backend scopes results by your role automatically — you do not pass a role param. Each card includes `healthScore` (number or null), `alertLevel` (L1/L2/L3 string), and `displayId` (SCW-XXX). Show `displayId` in the UI, use numeric `id` in API calls.',
        tags: ['Patients'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Patient list', content: new OA\JsonContent(
                type: 'object', properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data',    type: 'array',   items: new OA\Items(ref: '#/components/schemas/PatientCard')),
                    new OA\Property(property: 'meta',    ref: '#/components/schemas/PaginatedMeta'),
                ]
            )),
        ]
    )]
    public function patientIndex() {}

    #[OA\Post(
        path: '/api/v1/doctor/patients',
        summary: 'Create patient (admin/superadmin only)',
        tags: ['Patients'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'email', 'password'],
            properties: [
                new OA\Property(property: 'name',                type: 'string',  example: 'James Kwame'),
                new OA\Property(property: 'email',               type: 'string',  format: 'email', example: 'james@example.com'),
                new OA\Property(property: 'password',            type: 'string',  format: 'password', example: 'Welcome123'),
                new OA\Property(property: 'phone',               type: 'string',  nullable: true),
                new OA\Property(property: 'genotype',            type: 'string',  nullable: true, example: 'SS'),
                new OA\Property(property: 'blood_type',          type: 'string',  nullable: true, example: 'O+'),
                new OA\Property(property: 'date_of_birth',       type: 'string',  format: 'date', nullable: true),
                new OA\Property(property: 'gender',              type: 'string',  enum: ['male', 'female', 'other'], nullable: true),
                new OA\Property(property: 'hospital_id',         type: 'integer', nullable: true),
                new OA\Property(property: 'assigned_doctor_id',  type: 'integer', nullable: true),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Patient created', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 403, description: 'Access denied',   content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function patientStore() {}

    #[OA\Get(
        path: '/api/v1/doctor/patients/{id}',
        summary: 'Get patient detail',
        tags: ['Patients'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 12))],
        responses: [
            new OA\Response(response: 200, description: 'Patient detail', content: new OA\JsonContent(
                type: 'object', properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', type: 'object', properties: [
                        new OA\Property(property: 'id',             type: 'integer', example: 12),
                        new OA\Property(property: 'displayId',      type: 'string',  example: 'SCW-012'),
                        new OA\Property(property: 'healthScore',    type: 'integer', nullable: true, example: 65),
                        new OA\Property(property: 'alertLevel',     type: 'string',  enum: ['L1', 'L2', 'L3'], example: 'L2'),
                        new OA\Property(property: 'calibrationStatus', type: 'string', enum: ['calibrating', 'active'], nullable: true),
                        new OA\Property(property: 'name',           type: 'string',  example: 'James Kwame'),
                        new OA\Property(property: 'email',          type: 'string',  example: 'james@example.com'),
                        new OA\Property(property: 'phone',          type: 'string',  nullable: true),
                        new OA\Property(property: 'avatar',         type: 'string',  nullable: true),
                        new OA\Property(property: 'dateOfBirth',    type: 'string',  format: 'date', nullable: true),
                        new OA\Property(property: 'age',            type: 'integer', nullable: true, example: 24),
                        new OA\Property(property: 'gender',         type: 'string',  nullable: true, example: 'male'),
                        new OA\Property(property: 'sickleCellType', type: 'string',  nullable: true, example: 'SS'),
                        new OA\Property(property: 'genotype',       type: 'string',  nullable: true, example: 'SS'),
                        new OA\Property(property: 'bloodType',      type: 'string',  nullable: true, example: 'O+'),
                        new OA\Property(property: 'condition',      nullable: true),
                        new OA\Property(property: 'ward',           type: 'string',  nullable: true, example: 'Haematology Ward A'),
                        new OA\Property(property: 'admittedAt',     type: 'string',  format: 'date', nullable: true),
                        new OA\Property(property: 'nextAppointmentAt', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'assignedDoctor', nullable: true, type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'name', type: 'string'),
                        ]),
                        new OA\Property(property: 'assignedNurse', nullable: true, type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'name', type: 'string'),
                        ]),
                        new OA\Property(property: 'devices',          type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'emergencyContacts', type: 'array', items: new OA\Items(type: 'object')),
                    ]),
                ]
            )),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function patientShow() {}

    #[OA\Get(
        path: '/api/v1/doctor/patients/{id}/vitals',
        summary: 'Patient vitals snapshot',
        description: <<<'MD'
Returns a **typed snapshot object** — NOT a flat array.

Each key is a vital type. Access data like:
- Latest value: `heartRate.current`
- Unit: `heartRate.unit`
- For charts: `heartRate.series` (array, newest first, max 100 points)

If no reading exists in the range, `current` is null and `series` is empty.

Available keys: `heartRate`, `spo2`, `temperature`, `hrv`, `steps`, `sleepState`
MD,
        tags: ['Patients'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id',    in: 'path',  required: true,  schema: new OA\Schema(type: 'integer', example: 12)),
            new OA\Parameter(name: 'range', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['1h', '6h', '24h', '7d', '30d'], default: '24h')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Vitals snapshot', content: new OA\JsonContent(
                type: 'object', properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', type: 'object', properties: [
                        new OA\Property(property: 'heartRate', type: 'object', properties: [
                            new OA\Property(property: 'current',    nullable: true, example: 88),
                            new OA\Property(property: 'unit',       type: 'string', example: 'bpm'),
                            new OA\Property(property: 'recordedAt', type: 'string', format: 'date-time', nullable: true),
                            new OA\Property(property: 'series',     type: 'array', items: new OA\Items(type: 'object')),
                        ]),
                        new OA\Property(property: 'spo2',        type: 'object', description: 'SpO2 (%), same shape'),
                        new OA\Property(property: 'temperature', type: 'object', description: 'Temperature (°C), same shape'),
                        new OA\Property(property: 'hrv',         type: 'object', description: 'HRV (ms), same shape'),
                        new OA\Property(property: 'steps',       type: 'object', description: 'Steps, same shape'),
                        new OA\Property(property: 'sleepState',  type: 'object', description: 'Sleep state, same shape'),
                    ]),
                ]
            )),
        ]
    )]
    public function patientVitals() {}

    #[OA\Get(
        path: '/api/v1/doctor/patients/{id}/alerts',
        summary: 'Patient alert history',
        tags: ['Patients'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id',   in: 'path',  required: true,  schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Alert list', content: new OA\JsonContent(
                type: 'object', properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlertItem')),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginatedMeta'),
                ]
            )),
        ]
    )]
    public function patientAlerts() {}

    #[OA\Get(
        path: '/api/v1/doctor/patients/{id}/medications',
        summary: 'Patient medications',
        tags: ['Patients'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Medication list', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function patientMedications() {}

    #[OA\Post(
        path: '/api/v1/doctor/patients/{id}/notes',
        summary: 'Add medical note (not available to staff)',
        tags: ['Patients'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['title', 'content'],
            properties: [
                new OA\Property(property: 'title',   type: 'string', example: 'Pain Crisis Follow-up'),
                new OA\Property(property: 'content', type: 'string', example: 'Patient presented with VOC grade 7/10...'),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Note added', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 403, description: 'Staff role is read-only', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function patientAddNote() {}

    // ═══════════════════════════════════════════════════════════════════════════
    // ALERTS
    // ═══════════════════════════════════════════════════════════════════════════

    #[OA\Get(
        path: '/api/v1/doctor/alerts',
        summary: 'List alerts (role-scoped)',
        description: 'Alert `level` is returned as a string: `"L1"` (critical) or `"L2"` (warning). The raw integer (1 or 2) is in `rawLevel` if needed for query params. Filter by `?level=1` (integer) but read back `"L1"` (string).',
        tags: ['Alerts'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'acknowledged', 'resolved'])),
            new OA\Parameter(name: 'level',  in: 'query', required: false, schema: new OA\Schema(type: 'integer', enum: [1, 2])),
            new OA\Parameter(name: 'page',   in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Alert list', content: new OA\JsonContent(
                type: 'object', properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlertItem')),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginatedMeta'),
                ]
            )),
        ]
    )]
    public function alertIndex() {}

    #[OA\Put(
        path: '/api/v1/doctor/alerts/{id}/resolve',
        summary: 'Resolve an alert',
        tags: ['Alerts'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Alert resolved', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 404, description: 'Not found',      content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function alertResolve() {}

    #[OA\Put(
        path: '/api/v1/doctor/alerts/{id}/assign',
        summary: 'Assign alert to a doctor (doctor/admin/superadmin only)',
        tags: ['Alerts'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['doctor_id'],
            properties: [new OA\Property(property: 'doctor_id', type: 'integer', example: 3)]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Alert assigned', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 403, description: 'Staff cannot assign', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function alertAssign() {}

    // ═══════════════════════════════════════════════════════════════════════════
    // APPOINTMENTS
    // ═══════════════════════════════════════════════════════════════════════════

    #[OA\Get(
        path: '/api/v1/doctor/appointments',
        summary: 'List appointments (role-scoped)',
        tags: ['Appointments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status',     in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['upcoming', 'completed', 'cancelled'])),
            new OA\Parameter(name: 'patient_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page',       in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Appointment list', content: new OA\JsonContent(
                type: 'object', properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AppointmentItem')),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginatedMeta'),
                ]
            )),
        ]
    )]
    public function appointmentIndex() {}

    #[OA\Post(
        path: '/api/v1/doctor/appointments',
        summary: 'Create appointment (not available to staff)',
        tags: ['Appointments'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['patient_id', 'scheduled_at'],
            properties: [
                new OA\Property(property: 'patient_id',   type: 'integer',  example: 12),
                new OA\Property(property: 'scheduled_at', type: 'string',   format: 'date-time', example: '2026-07-20T10:00:00Z'),
                new OA\Property(property: 'type',         type: 'string',   nullable: true, example: 'Follow-up'),
                new OA\Property(property: 'notes',        type: 'string',   nullable: true),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Appointment created', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 403, description: 'Staff cannot create', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function appointmentStore() {}

    #[OA\Put(
        path: '/api/v1/doctor/appointments/{id}',
        summary: 'Update appointment (not available to staff)',
        tags: ['Appointments'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'scheduled_at', type: 'string',  format: 'date-time'),
            new OA\Property(property: 'type',         type: 'string',  nullable: true),
            new OA\Property(property: 'status',       type: 'string',  enum: ['upcoming', 'completed', 'cancelled']),
            new OA\Property(property: 'notes',        type: 'string',  nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function appointmentUpdate() {}

    #[OA\Delete(
        path: '/api/v1/doctor/appointments/{id}',
        summary: 'Cancel/delete appointment (not available to staff)',
        tags: ['Appointments'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Appointment cancelled', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function appointmentDestroy() {}

    // ═══════════════════════════════════════════════════════════════════════════
    // STAFF
    // ═══════════════════════════════════════════════════════════════════════════

    #[OA\Get(
        path: '/api/v1/doctor/staff',
        summary: 'List staff members (admin/superadmin only)',
        description: 'Admin sees only their hospital\'s staff. Superadmin sees all.',
        tags: ['Staff'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'role',            in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['doctor', 'staff', 'admin'])),
            new OA\Parameter(name: 'approval_status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'approved', 'rejected'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Staff list', content: new OA\JsonContent(
                type: 'object', properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                        type: 'object', properties: [
                            new OA\Property(property: 'id',             type: 'integer', example: 7),
                            new OA\Property(property: 'name',           type: 'string',  example: 'Dr. Amara Osei'),
                            new OA\Property(property: 'email',          type: 'string',  example: 'amara@hospital.org'),
                            new OA\Property(property: 'role',           type: 'string',  enum: ['doctor', 'staff', 'admin']),
                            new OA\Property(property: 'approvalStatus', type: 'string',  enum: ['pending', 'approved', 'rejected']),
                            new OA\Property(property: 'registeredAt',   type: 'string',  format: 'date-time'),
                        ]
                    )),
                ]
            )),
            new OA\Response(response: 403, description: 'Doctor/staff cannot access', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function staffIndex() {}

    #[OA\Patch(
        path: '/api/v1/doctor/staff/{id}/approve',
        summary: 'Approve or reject a staff/doctor account',
        tags: ['Staff'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['action'],
            properties: [new OA\Property(property: 'action', type: 'string', enum: ['approve', 'reject'], example: 'approve')]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Status updated', content: new OA\JsonContent(
                type: 'object', properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', type: 'object', properties: [
                        new OA\Property(property: 'id',             type: 'integer', example: 7),
                        new OA\Property(property: 'approvalStatus', type: 'string',  example: 'approved'),
                    ]),
                ]
            )),
            new OA\Response(response: 403, description: 'Access denied', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found',     content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function staffApprove() {}

    // ═══════════════════════════════════════════════════════════════════════════
    // REPORTS
    // ═══════════════════════════════════════════════════════════════════════════

    #[OA\Get(
        path: '/api/v1/doctor/reports/summary',
        summary: 'Aggregated report summary',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Summary', content: new OA\JsonContent(
                type: 'object', properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', type: 'object', properties: [
                        new OA\Property(property: 'totalPatients',   type: 'integer', example: 48),
                        new OA\Property(property: 'totalAlerts',     type: 'integer', example: 120),
                        new OA\Property(property: 'criticalAlerts',  type: 'integer', example: 12),
                        new OA\Property(property: 'warningAlerts',   type: 'integer', example: 38),
                        new OA\Property(property: 'resolvedAlerts',  type: 'integer', example: 95),
                        new OA\Property(property: 'last7DaysAlerts', type: 'integer', example: 18),
                    ]),
                ]
            )),
        ]
    )]
    public function reportSummary() {}

    #[OA\Get(
        path: '/api/v1/doctor/reports/weekly-alert-trend',
        summary: '7-day alert count trend',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Trend data', content: new OA\JsonContent(
                type: 'object', properties: [
                    new OA\Property(property: 'data', type: 'object', properties: [
                        new OA\Property(property: 'trend', type: 'array', items: new OA\Items(
                            type: 'object', properties: [
                                new OA\Property(property: 'date',  type: 'string', example: '2026-07-06'),
                                new OA\Property(property: 'label', type: 'string', example: 'Sun'),
                                new OA\Property(property: 'count', type: 'integer', example: 4),
                            ]
                        )),
                    ]),
                ]
            )),
        ]
    )]
    public function reportWeeklyTrend() {}

    #[OA\Get(
        path: '/api/v1/doctor/reports/alert-type-breakdown',
        summary: 'Alert count grouped by type',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Breakdown', content: new OA\JsonContent(
                type: 'object', properties: [
                    new OA\Property(property: 'data', type: 'object', properties: [
                        new OA\Property(property: 'breakdown', type: 'object',
                            example: ['fever' => 14, 'hypoxia' => 8, 'tachycardia' => 5]),
                    ]),
                ]
            )),
        ]
    )]
    public function reportTypeBreakdown() {}

    #[OA\Get(
        path: '/api/v1/doctor/reports/health-trends',
        summary: 'Raw vital readings for trend charts',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'patient_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type',       in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['heart_rate', 'spo2', 'temperature', 'hrv', 'steps', 'sleep_state'])),
            new OA\Parameter(name: 'range',      in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['7d', '30d', '90d'], default: '7d')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Readings array', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function reportHealthTrends() {}

    #[OA\Get(
        path: '/api/v1/doctor/reports/export',
        summary: 'Export vitals as CSV',
        tags: ['Reports'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'patient_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'range',      in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['7d', '30d', '90d'], default: '7d')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'CSV file download', content: new OA\MediaType(mediaType: 'text/csv')),
        ]
    )]
    public function reportExport() {}
}
