# STATRA Health — Backend API

Laravel 13 REST API powering the STATRA Health platform — a clinical monitoring system for Sickle Cell Disease (SCD) patients. The backend serves five distinct clients across separate API surfaces, each with its own authentication scope.

---

## Platform overview

| Client | Prefix | Description |
|---|---|---|
| Patient mobile app | `api/v1/patient/` | iOS/Android app — vitals sync, medications, symptoms, check-ins |
| Doctor dashboard | `api/v1/doctor/` | Web app — patient monitoring, alerts, appointments, reports |
| Check-in app | `api/v1/checkin/` | Tablet kiosk — patients log daily symptoms at the clinic |
| Store | `api/v1/store/` | JCVital band e-commerce + affiliate management |
| Website | `api/v1/website/` | Public website contact and content endpoints |

---

## Tech stack

| | |
|---|---|
| Framework | Laravel 13.x / PHP 8.3 |
| Database | Azure SQL Server (sqlsrv driver) |
| Auth | Laravel Sanctum — token abilities per client |
| Email | Resend (via Laravel Mail) |
| Storage | AWS S3 via Cloudflare |
| Cache / Queue | Redis (Predis) |
| Social login | Laravel Socialite (Google) |
| API Docs | L5-Swagger (OpenAPI 3) |
| Deployment | Azure App Service |
| Baseline engine | Python EWMA script (`scripts/compute_baselines.py`) |

---

## Role system

All protected routes are scoped automatically by the token's role. No role param is ever needed in requests.

| Role | Scope |
|---|---|
| `patient` | Own data only — mobile app |
| `doctor` | Patients assigned to them |
| `admin` | All patients + staff within their hospital |
| `superadmin` | All data across all hospitals — product owner dashboard |
| `staff` | Same patient scope as admin, read-heavy |
| `checkin_user` | Check-in kiosk only |

Doctors and staff require admin approval (`approval_status = approved`) before accessing protected routes. Superadmin and admin accounts are created via the onboarding flow — not self-registration.

---

## Local setup

**Requirements:** PHP 8.3, Composer, SQL Server (or Azure SQL), Redis

```bash
git clone <repo>
cd statra-backend

composer install

cp .env.example .env
php artisan key:generate
```

**Configure `.env`:**

```env
DB_CONNECTION=sqlsrv
DB_HOST=your-azure-sql-host
DB_PORT=1433
DB_DATABASE=statra
DB_USERNAME=your-username
DB_PASSWORD=your-password

MAIL_MAILER=resend
RESEND_KEY=re_xxxx
MAIL_FROM_ADDRESS=hello@statra.health
MAIL_FROM_NAME=Statra

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=...
AWS_BUCKET=...

DASHBOARD_URL=https://dashboard.statra.health
```

**Run migrations and start:**

```bash
php artisan migrate
php artisan serve

# In separate terminals:
php artisan queue:work
php artisan schedule:work
```

---

## API documentation

Interactive Swagger UI is available at:

```
/api/documentation
```

Each API surface has its own spec file in `app/OpenApi/`. To regenerate docs after editing spec annotations:

```bash
php artisan l5-swagger:generate
```

Authorize in Swagger UI by clicking **Authorize** and pasting a Bearer token. Get a token via the relevant `POST /auth/login` endpoint for your client.

---

## Project structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Patient/          # Mobile app — auth, profile, vitals, meds, symptoms, alerts
│   │   ├── Doctor/           # Dashboard — patients, alerts, reports, staff, hospitals
│   │   ├── CheckIn/          # Kiosk check-in flow
│   │   ├── Store/            # Band orders, payments, affiliates
│   │   └── Website/          # Public contact + content
│   ├── Middleware/
│   │   ├── EnsureIsPatient.php
│   │   ├── EnsureIsStaff.php       # Covers doctor / admin / staff / superadmin
│   │   └── EnsureIsSuperAdmin.php  # Superadmin-only routes
│   └── Responses/
│       └── ApiResponse.php         # Standard envelope: { success, message, data }
├── Models/
│   ├── User.php                    # Base — all roles share this table
│   ├── Patient.php                 # Extended patient profile
│   ├── Doctor.php                  # Doctor profile + specialisation
│   ├── Hospital.php                # Hospital entity
│   ├── VitalReading.php            # Raw wearable readings
│   ├── PatientBaseline.php         # EWMA baselines per signal per activity context
│   ├── CompositeDeviationScore.php # Blended health score per computation run
│   ├── PatientSettings.php         # Per-patient privacy + notification toggles
│   ├── Alert.php
│   ├── Medication.php / MedicationLog.php
│   ├── Symptom.php / PainLog.php
│   └── Appointment.php
├── Services/
│   ├── VitalProcessor.php          # Normalises raw JCVital 2208A band payload
│   ├── AlertEngine.php             # Ingestion-time alert rules (temperature, SpO2)
│   └── RiskCalculatorService.php   # Clinical risk score — mirrors mobile calcRisk.ts
└── OpenApi/
    ├── DoctorDashboard/DoctorDashboardSpec.php
    ├── MobileApp/MobileAppSpec.php
    └── CheckIn/CheckInSpec.php

routes/
├── api_patient.php
├── api_doctor.php
├── api_checkin.php
├── api_store.php
└── api_website.php

scripts/
└── compute_baselines.py      # EWMA baseline computation (see below)
```

---

## Wearable data pipeline

Patients wear a **JCVital 2208A** band. The mobile app syncs band data to:

```
POST /api/v1/patient/vitals/sync
```

`VitalProcessor::normalizeFromBand()` maps the raw band payload to typed `vital_readings` rows. Five signals are tracked: `heart_rate`, `spo2`, `temperature`, `hrv`, `steps`. Each reading carries an `activity_context` (resting / light / active / sleep) and a `quality_flag` (good / low_confidence / motion_artifact).

**Ingestion-time checks (PHP):**
- Temperature ≥ 38.5°C → immediate `level=1` critical alert fires via `AlertEngine`
- SpO2 quality flag is stored per reading for downstream filtering

**Batch baseline computation (Python — run on cron every 5–15 min):**

`scripts/compute_baselines.py` computes per-patient EWMA baselines per signal per activity context and writes to:

| Table | Contents |
|---|---|
| `patient_baselines` | Rolling mean + stddev per signal |
| `deviation_scores` | Per-signal z-score per run |
| `composite_deviation_scores` | Blended health score, outreach trigger flags |

Calibration requires 28 days of data minimum. Fever short-circuits composite scoring and fires outreach directly. Four outreach triggers are evaluated per the clinical spec: fever, sustained SpO2 drop (30 min), composite ≥ 9 from ≥ 2 signals, and persistent elevation ≥ 4 hours.

---

## Hospital onboarding

New hospitals are onboarded by superadmin — hospitals do not self-register.

```
POST /api/v1/doctor/superadmin/hospitals             # Create hospital record
POST /api/v1/doctor/superadmin/hospitals/{id}/admin  # Create first admin + send invite email
```

The invited admin receives a 72-hour set-password link. They set their password via:

```
POST /api/v1/doctor/auth/accept-invite               # Public — validates token, returns Sanctum token
```

Full hospital CRUD is available under `/superadmin/hospitals/` — list, detail, update, activate/deactivate.

---

## Authentication flows

**Patient mobile (Google Sign-In via Firebase):**
1. Mobile app authenticates with Firebase → receives ID token
2. `POST /api/v1/patient/auth/social` `{ provider: "google", id_token: "..." }`
3. Backend verifies with Firebase Admin SDK → finds or creates patient → returns Sanctum token
4. App sends `Authorization: Bearer <token>` on all subsequent requests

**Doctor / admin / staff (email + password):**
```
POST /api/v1/doctor/auth/login              # Returns token + approval_status
POST /api/v1/doctor/auth/register           # Creates account as pending
PATCH /api/v1/doctor/staff/{id}/approve     # Admin approves: { action: "approve"|"reject" }
```

**Check-in kiosk:**
```
POST /api/v1/checkin/auth/login             # Returns checkin_user scoped token
```

---

## Response envelope

```json
{ "success": true, "message": "...", "data": { ... } }
```

Validation errors (422):
```json
{ "success": false, "message": "Validation failed.", "errors": { "field": ["reason"] } }
```

Paginated lists:
```json
{ "success": true, "data": [...], "meta": { "current_page": 1, "per_page": 20, "total": 84, "last_page": 5 } }
```

---

## Deployment (Azure App Service)

The app runs on Azure App Service. `startup.sh` runs on each deploy — it reloads nginx, sets storage permissions, clears and caches config, runs `migrate --force`, and starts the scheduler and queue worker as background processes.

CI/CD is handled via GitHub Actions (`.github/workflows/main_statrawebapp.yml`). **Do not edit this file.**

---

## Key environment variables

| Variable | Purpose |
|---|---|
| `DB_CONNECTION=sqlsrv` | Azure SQL Server driver |
| `RESEND_KEY` | Transactional email via Resend |
| `MAIL_FROM_ADDRESS` | Must be `hello@statra.health` (verified sending domain) |
| `DASHBOARD_URL` | Base URL for invite links sent to hospital admins |
| `FIREBASE_*` | Firebase Admin SDK — Google Sign-In token verification |
| `AWS_*` | S3 credentials for file storage |

---

## Clinical context

STATRA is purpose-built for Sickle Cell Disease. The risk scoring, alert thresholds, and baseline logic have been reviewed by a haematologist. Key clinical decisions embedded in the codebase:

- **Genotype multipliers** — SS and SB0 are highest severity (×1.2), SB+ (×1.1), all others (×1.0)
- **Red-flag symptom override** — triggers immediate Urgent status regardless of score; includes priapism and splenic sequestration per haematologist review
- **SpO2 caution** — wrist oximetry is inherently less accurate in SCD patients due to poor peripheral perfusion; quality flagging and confidence filtering are built in at every layer
- **Context-aware HR/HRV baselines** — resting, active, and sleep baselines are always computed separately; a heart rate of 110 bpm means different things in different contexts
- **Risk score parity** — `RiskCalculatorService.php` is a direct PHP port of the mobile app's `calcRisk.ts`; both sides must produce identical scores; backend always recalculates and never trusts client-submitted scores
