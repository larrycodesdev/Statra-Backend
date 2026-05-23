# SCD Wellness App — Backend README
> Hand this file to Claude Code in VS Code to begin building the Laravel backend.

---

## Project overview

A Laravel REST API backend for the **SCD Wellness App** — a health monitoring system for sickle cell disease patients. The backend serves two clients:

- **Patient mobile app** (iOS + Android) — connects to a JCVital 2208A wearable band via Bluetooth and syncs vitals data to this API
- **Doctor web app** — hospital dashboard for monitoring patients, managing alerts, viewing trends and reports

---

## Tech stack

| Layer | Choice | Reason |
|---|---|---|
| Framework | Laravel 11 | Specified by backend dev |
| Auth | Laravel Sanctum | Token-based for mobile, session for web |
| Database | MySQL 8 | Relational, time-series vitals stored with indexes |
| Queue | Laravel Queue + Redis | Background jobs for alert processing |
| Push notifications | Firebase FCM (Android) + APNs (iOS) | Patient alerts |
| Real-time (doctor dashboard) | Polling every 2 mins OR Laravel Reverb (WebSocket) | Start with polling for MVP |
| Storage | Local / S3 | Medical records, exports |

---

## Project folder structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Patient/
│   │   │   ├── AuthController.php
│   │   │   ├── ProfileController.php
│   │   │   ├── VitalsController.php
│   │   │   ├── AlertController.php
│   │   │   ├── DeviceController.php
│   │   │   ├── PainLogController.php
│   │   │   ├── MedicationController.php
│   │   │   └── AppointmentController.php
│   │   └── Doctor/
│   │       ├── AuthController.php
│   │       ├── PatientController.php
│   │       ├── AlertController.php
│   │       ├── AppointmentController.php
│   │       └── ReportController.php
│   └── Middleware/
│       ├── EnsureIsPatient.php
│       └── EnsureIsDoctor.php
├── Models/
│   ├── User.php           (base — patients and doctors both extend this)
│   ├── Patient.php
│   ├── Doctor.php
│   ├── Device.php
│   ├── VitalReading.php
│   ├── Alert.php
│   ├── PainLog.php
│   ├── MedicationLog.php
│   ├── Appointment.php
│   └── MedicalRecord.php
├── Services/
│   ├── AlertEngine.php        ← core: evaluates readings, triggers alerts
│   ├── VitalProcessor.php     ← parses + validates incoming sync batches
│   └── NotificationService.php
└── Jobs/
    ├── ProcessVitalBatch.php
    └── SendAlertNotification.php
routes/
├── api_patient.php    (prefix: /api/v1/patient)
└── api_doctor.php     (prefix: /api/v1/doctor)
```

---

## Authentication architecture

Two user roles sharing one `users` table, separated by a `role` column (`patient` | `doctor`).

**Use Laravel Sanctum** with ability-based tokens:

```php
// Patient login — returns token with 'patient' ability
$token = $user->createToken('mobile-app', ['patient'])->plainTextToken;

// Doctor login — returns token with 'doctor' ability
$token = $user->createToken('web-dashboard', ['doctor'])->plainTextToken;
```

**Middleware guards:**

```php
// In routes/api_patient.php
Route::middleware(['auth:sanctum', 'ability:patient'])->group(function () { ... });

// In routes/api_doctor.php
Route::middleware(['auth:sanctum', 'ability:doctor'])->group(function () { ... });
```

---

## Database schema (build these migrations in order)

### 1. users
```
id, uuid, name, email, password, role (enum: patient|doctor),
phone, avatar, email_verified_at, fcm_token (push notification device token),
remember_token, timestamps, soft_deletes
```

### 2. patients (extends users)
```
id, user_id (FK), genotype (enum: SS|SC|SB|other),
blood_type, date_of_birth, gender, emergency_contact_name,
emergency_contact_phone, assigned_doctor_id (FK → users),
timestamps
```

### 3. doctors
```
id, user_id (FK), hospital_name, department,
specialisation, license_number, timestamps
```

### 4. devices
```
id, patient_id (FK), device_id (unique — the BLE hardware ID),
device_model, firmware_version, platform (android|ios),
last_synced_at, is_active, timestamps
```

### 5. vital_readings (high write volume — index heavily)
```
id, patient_id (FK), device_id (FK), type (enum — see allowed types below),
value (json — handles simple numerics AND objects like BP {systolic, diastolic}),
unit, recorded_at (indexed), created_at
```
Index: `(patient_id, type, recorded_at)` — used for all chart queries.

**Allowed type enum values:**
`heart_rate | spo2 | temperature | blood_pressure | steps | sleep_state | hrv`

### 6. alerts
```
id, patient_id (FK), vital_reading_id (FK), type, level (1=critical|2=warning),
message, status (enum: pending|acknowledged|resolved),
assigned_to (FK → users, doctor),
resolved_at, timestamps
```

### 7. pain_logs
```
id, patient_id (FK), pain_level (1-10), location (json — body map areas),
notes, logged_at, timestamps
```

### 8. medication_logs
```
id, patient_id (FK), medication_name, dosage, scheduled_at,
taken_at (null = missed), status (enum: taken|missed|pending), timestamps
```

### 9. appointments
```
id, patient_id (FK), doctor_id (FK), scheduled_at,
status (enum: upcoming|completed|cancelled), notes, timestamps
```

### 10. medical_records
```
id, patient_id (FK), title, content (text), recorded_by (FK → users),
timestamps
```

---

## Core modules to build (in recommended order)

### Module 1: Auth (start here)
- `POST /api/v1/patient/auth/register`
- `POST /api/v1/patient/auth/login`
- `POST /api/v1/patient/auth/social` (Google, Apple, Facebook token verification)
- `POST /api/v1/patient/auth/forgot-password`
- `POST /api/v1/patient/auth/reset-password`
- `POST /api/v1/patient/auth/logout`
- Mirror all above for `/api/v1/doctor/auth/...`

### Module 2: Patient profile
- `GET  /api/v1/patient/profile`
- `PUT  /api/v1/patient/profile`
- `PUT  /api/v1/patient/profile/emergency-contacts`
- `PUT  /api/v1/patient/profile/medical-info`
- `PUT  /api/v1/patient/profile/fcm-token` ← called by app on every login to keep push token fresh

### Module 3: Device + wearable sync
- `POST /api/v1/patient/device/register`
- `GET  /api/v1/patient/device/status`
- `POST /api/v1/patient/vitals/sync` ← the most important endpoint
- `GET  /api/v1/patient/vitals?type=&range=`

### Module 4: Alert engine
- Fires automatically inside `ProcessVitalBatch` job after every sync
- No separate HTTP endpoint for triggering — it's internal
- `GET  /api/v1/patient/alerts` (patient sees their own)
- `GET  /api/v1/doctor/alerts` (doctor sees all patients, filterable)
- `PUT  /api/v1/doctor/alerts/{id}/resolve`
- `PUT  /api/v1/doctor/alerts/{id}/assign`

### Module 5: Pain log + medication log
- `POST /api/v1/patient/pain-log`
- `GET  /api/v1/patient/pain-log`
- `POST /api/v1/patient/medication-log`
- `GET  /api/v1/patient/medication-log`

### Module 6: Doctor patient management
- `GET  /api/v1/doctor/patients`
- `GET  /api/v1/doctor/patients/{id}`
- `GET  /api/v1/doctor/patients/{id}/vitals?type=&range=`
- `GET  /api/v1/doctor/patients/{id}/alerts`
- `POST /api/v1/doctor/patients/{id}/notes`
- `GET  /api/v1/doctor/dashboard` ← summary metrics card

### Module 7: Appointments
- `GET  /api/v1/patient/appointments`
- `POST /api/v1/doctor/appointments`
- `PUT  /api/v1/doctor/appointments/{id}`
- `GET  /api/v1/doctor/appointments`

### Module 8: Reports
- `GET  /api/v1/doctor/reports/health-trends`
- `GET  /api/v1/doctor/reports/alerts-analytics`
- `GET  /api/v1/doctor/reports/export?format=csv`

---

## Alert engine logic (AlertEngine.php)

```php
// Thresholds — make these configurable in DB or .env later
const THRESHOLDS = [
    'spo2'         => ['critical' => 90, 'warning' => 93],      // below = alert
    'heart_rate'   => ['critical_low' => 40, 'warning_low' => 50,
                       'warning_high' => 120, 'critical_high' => 150],
    'temperature'  => ['warning' => 37.8, 'critical' => 39.0],  // above = alert
];

// Logic per reading:
// 1. Evaluate against thresholds
// 2. If threshold breached: create Alert record
// 3. Dispatch SendAlertNotification job
// 4. Return triggered alerts in sync response
```

---

## Vitals sync endpoint logic

```
POST /api/v1/patient/vitals/sync

1. Validate request (device_id must belong to authenticated patient)
2. Dispatch ProcessVitalBatch::dispatch($readings, $patient) to queue
3. Return immediate 202 Accepted response

Inside ProcessVitalBatch job:
  foreach reading:
    - Validate type is in allowed enum list
    - Store in vital_readings table
    - Pass to AlertEngine::evaluate($reading, $patient)
  
AlertEngine::evaluate():
    - Check value against thresholds
    - If breached: create Alert, dispatch SendAlertNotification
    - Return list of triggered alerts
```

---

## Environment variables needed

```env
APP_NAME=SCDWellnessApp
APP_ENV=local
APP_KEY=
APP_URL=

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=scd_wellness
DB_USERNAME=
DB_PASSWORD=

QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

FCM_SERVER_KEY=         # Firebase Cloud Messaging key
APNS_KEY_ID=            # Apple Push key
APNS_TEAM_ID=

GOOGLE_CLIENT_ID=       # Social auth
APPLE_CLIENT_ID=
FACEBOOK_APP_ID=
FACEBOOK_APP_SECRET=

SANCTUM_STATEFUL_DOMAINS=localhost
```

---

## API response format — use consistently across all endpoints

```json
// Success
{
  "success": true,
  "data": { ... },
  "message": "Operation successful"
}

// Error
{
  "success": false,
  "message": "Validation failed",
  "errors": { "field": ["error message"] }
}

// Paginated list
{
  "success": true,
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 84
  }
}
```

---

## Build order for Claude Code

1. `php artisan make:migration` — all tables above in order
2. Auth module (patient + doctor) — test with Postman before moving on
3. Patient profile endpoints
4. Device registration endpoint
5. Vitals sync endpoint + ProcessVitalBatch job
6. AlertEngine service
7. Remaining modules

---

> **Note for Claude Code:** Do not start on any module until the migration for that module's table is confirmed working. Build and test one module at a time.
