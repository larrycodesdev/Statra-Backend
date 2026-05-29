# STATRA — Laravel Backend Implementation Guide

This document covers every file you need to add to an existing Laravel backend to support
the STATRA Sickle Cell Check-in app. Follow the steps in order.

---

## 1. Install Laravel Sanctum (token authentication)

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

Then open `bootstrap/app.php` (Laravel 11+) and add Sanctum to the API middleware:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(prepend: [
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    ]);
})
```

---

## 2. Migrations

### 2a. Add `username` column to the existing `users` table

Create file: `database/migrations/YYYY_MM_DD_add_username_to_users_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('id');
            $table->string('email')->nullable()->change(); // email not sent by frontend yet
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
```

---

### 2b. Create the `check_ins` table

Create file: `database/migrations/YYYY_MM_DD_create_check_ins_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Patient snapshot
            $table->string('pid');
            $table->string('name');
            $table->enum('genotype', ['SS', 'SC', 'SB+', 'SB0', 'Unknown'])->default('Unknown');
            $table->enum('meds', ['Yes', 'No', 'Missed']);

            // Wellbeing inputs
            $table->unsignedTinyInteger('pain'); // 0–10
            $table->enum('fatigue', ['Low', 'Medium', 'High']);
            $table->enum('sleep', ['Good', 'Okay', 'Poor']);
            $table->enum('hydration', ['Good', 'Okay', 'Low']);
            $table->string('condition'); // "No, I feel normal" | "Slightly different" | "Very different"

            // Symptoms & triggers (arrays stored as JSON)
            $table->json('symptoms')->nullable();  // general symptoms
            $table->json('flags')->nullable();     // red-flag symptoms
            $table->json('triggers')->nullable();  // triggers

            // Safety
            $table->string('safety')->default('None');
            $table->text('notes')->nullable();

            // Calculated risk result (computed server-side)
            $table->unsignedSmallInteger('total');
            $table->string('display_score');       // "OVERRIDE" or stringified number
            $table->string('status');              // Stable | Watch closely | Elevated | Urgent
            $table->boolean('red_flag')->default(false);
            $table->string('reason');
            $table->json('scores');                // {pain, fatigue, sleep, hydration, symptoms, triggers}
            $table->decimal('geno_mult', 3, 2)->default(1.00);

            $table->timestamp('checked_in_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
    }
};
```

Run migrations:

```bash
php artisan migrate
```

---

## 3. Models

### 3a. Update `app/Models/User.php`

Replace the entire file:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function checkIns()
    {
        return $this->hasMany(CheckIn::class);
    }
}
```

---

### 3b. Create `app/Models/CheckIn.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckIn extends Model
{
    protected $fillable = [
        'user_id', 'pid', 'name', 'genotype', 'meds',
        'pain', 'fatigue', 'sleep', 'hydration', 'condition',
        'symptoms', 'flags', 'triggers',
        'safety', 'notes',
        'total', 'display_score', 'status', 'red_flag',
        'reason', 'scores', 'geno_mult', 'checked_in_at',
    ];

    protected function casts(): array
    {
        return [
            'symptoms'      => 'array',
            'flags'         => 'array',
            'triggers'      => 'array',
            'scores'        => 'array',
            'red_flag'      => 'boolean',
            'pain'          => 'integer',
            'total'         => 'integer',
            'geno_mult'     => 'float',
            'checked_in_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

---

## 4. Services

### 4a. Create `app/Services/RiskCalculatorService.php`

This is a direct PHP port of the frontend's `calcRisk.ts` so both sides produce
identical scores. The backend recalculates — it never trusts scores sent from the client.

```php
<?php

namespace App\Services;

class RiskCalculatorService
{
    private const RED_FLAG_SYMPTOMS = [
        'Chest pain',
        'Shortness of breath',
        'Fever',
        'Confusion or weakness',
        'Severe chest pain',
        'Difficulty breathing',
    ];

    public function calculate(array $input): array
    {
        $flags    = $input['flags'] ?? [];
        $symptoms = $input['symptoms'] ?? [];
        $triggers = $input['triggers'] ?? [];
        $safety   = $input['safety'] ?? 'None';
        $genotype = $input['genotype'] ?? 'Unknown';

        // Immediate URGENT if any red-flag symptom is present
        $hasRedFlag = !empty(array_intersect($flags, self::RED_FLAG_SYMPTOMS))
            || in_array($safety, self::RED_FLAG_SYMPTOMS);

        if ($hasRedFlag) {
            return [
                'total'        => 999,
                'display_score' => 'OVERRIDE',
                'status'       => 'Urgent',
                'red_flag'     => true,
                'reason'       => 'Red-flag symptom reported — immediate medical attention required',
                'scores'       => [
                    'pain' => 0, 'fatigue' => 0, 'sleep' => 0,
                    'hydration' => 0, 'symptoms' => 0, 'triggers' => 0,
                ],
                'geno_mult' => 1.0,
            ];
        }

        // Component scoring
        $pain = (int) ($input['pain'] ?? 0);
        $painScore = match (true) {
            $pain === 0 => 0,
            $pain <= 3  => 1,
            $pain <= 6  => 3,
            $pain <= 8  => 5,
            default     => 6,
        };

        $fatScore = match ($input['fatigue'] ?? '') {
            'Low'    => 0,
            'Medium' => 1,
            default  => 2,
        };

        $sleepScore = match ($input['sleep'] ?? '') {
            'Good'  => 0,
            'Okay'  => 0.5,
            default => 1,
        };

        $hydScore = match ($input['hydration'] ?? '') {
            'Good'  => 0,
            'Okay'  => 1,
            default => 2,
        };

        $symScore  = 0;
        $condition = $input['condition'] ?? '';
        if (in_array('Joint pain', $symptoms)) $symScore += 2;
        if (in_array('Headache', $symptoms))   $symScore += 1;
        if (in_array('Dizziness', $symptoms))  $symScore += 2;
        if ($condition === 'Very different')     $symScore += 2;
        if ($condition === 'Slightly different') $symScore += 1;

        $trigScore = 0;
        if (in_array('Stress', $triggers))                $trigScore += 1;
        if (in_array('Physical exertion', $triggers))     $trigScore += 1;
        if (in_array('Cold weather exposure', $triggers)) $trigScore += 1;
        if (in_array('Dehydration', $triggers))           $trigScore += 2;
        if (in_array('Illness/infection', $triggers))     $trigScore += 3;
        if (in_array('Poor sleep', $triggers))            $trigScore += 1;
        if (in_array('Travel', $triggers))                $trigScore += 1;

        $genoMult = match (true) {
            in_array($genotype, ['SS', 'SB0']) => 1.2,
            $genotype === 'SB+'               => 1.1,
            default                           => 1.0,
        };

        $raw   = $painScore + $fatScore + $sleepScore + $hydScore + $symScore + $trigScore;
        $total = (int) round($raw * $genoMult);

        $status = match (true) {
            $total <= 3  => 'Stable',
            $total <= 7  => 'Watch closely',
            $total <= 11 => 'Elevated',
            default      => 'Urgent',
        };

        $reason = match (true) {
            $symScore >= max($painScore, $trigScore) && $symScore > 0
                => 'Multiple symptoms present beyond pain level',
            $trigScore >= max($painScore, $symScore) && $trigScore > 0
                => 'Trigger exposure increasing risk today',
            $painScore > 0
                => 'Pain level is the primary risk driver today',
            default
                => 'Combination of mild factors contributing to score',
        };

        if ($genoMult > 1.0) {
            $reason .= " · Genotype {$genotype} (×{$genoMult}) applied";
        }

        return [
            'total'        => $total,
            'display_score' => (string) $total,
            'status'       => $status,
            'red_flag'     => false,
            'reason'       => $reason,
            'geno_mult'    => $genoMult,
            'scores'       => [
                'pain'      => $painScore,
                'fatigue'   => $fatScore,
                'sleep'     => $sleepScore,
                'hydration' => $hydScore,
                'symptoms'  => $symScore,
                'triggers'  => $trigScore,
            ],
        ];
    }
}
```

Register it as a singleton in `app/Providers/AppServiceProvider.php`:

```php
use App\Services\RiskCalculatorService;

public function register(): void
{
    $this->app->singleton(RiskCalculatorService::class);
}
```

---

## 5. Form Requests (Validation)

### 5a. Create `app/Http/Requests/Auth/LoginRequest.php`

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }
}
```

---

### 5b. Create `app/Http/Requests/Auth/RegisterRequest.php`

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'name'     => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:6'],
            'email'    => ['nullable', 'email', 'unique:users,email'],
        ];
    }
}
```

---

### 5c. Create `app/Http/Requests/StoreCheckInRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCheckInRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'pid'        => ['required', 'string'],
            'name'       => ['required', 'string'],
            'genotype'   => ['required', 'in:SS,SC,SB+,SB0,Unknown'],
            'meds'       => ['required', 'in:Yes,No,Missed'],
            'pain'       => ['required', 'integer', 'min:0', 'max:10'],
            'fatigue'    => ['required', 'in:Low,Medium,High'],
            'sleep'      => ['required', 'in:Good,Okay,Poor'],
            'hydration'  => ['required', 'in:Good,Okay,Low'],
            'condition'  => ['required', 'string'],
            'safety'     => ['required', 'string'],
            'notes'      => ['nullable', 'string', 'max:1000'],
            'symptoms'   => ['nullable', 'array'],
            'symptoms.*' => ['string'],
            'flags'      => ['nullable', 'array'],
            'flags.*'    => ['string'],
            'triggers'   => ['nullable', 'array'],
            'triggers.*' => ['string'],
        ];
    }
}
```

---

## 6. API Resources

### 6a. Create `app/Http/Resources/CheckInResource.php`

This shapes the JSON response to match the shape the frontend expects (`camelCase` keys,
same field names as the TypeScript `CheckInResult` type).

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckInResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'pid'          => $this->pid,
            'name'         => $this->name,
            'genotype'     => $this->genotype,
            'meds'         => $this->meds,
            'pain'         => $this->pain,
            'fatigue'      => $this->fatigue,
            'sleep'        => $this->sleep,
            'hydration'    => $this->hydration,
            'condition'    => $this->condition,
            'safety'       => $this->safety,
            'notes'        => $this->notes,
            'symptoms'     => $this->symptoms ?? [],
            'flags'        => $this->flags ?? [],
            'triggers'     => $this->triggers ?? [],
            'total'        => $this->total,
            'displayScore' => $this->display_score,
            'status'       => $this->status,
            'redFlag'      => $this->red_flag,
            'reason'       => $this->reason,
            'scores'       => $this->scores,
            'genoMult'     => $this->geno_mult,
            'ts'           => $this->checked_in_at,
        ];
    }
}
```

---

## 7. Controllers

### 7a. Create `app/Http/Controllers/AuthController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid username or password'], 401);
        }

        $token = $user->createToken('statra-app')->plainTextToken;

        return response()->json([
            'name'     => $user->name,
            'username' => $user->username,
            'token'    => $token,
        ]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'username' => $request->username,
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('statra-app')->plainTextToken;

        return response()->json([
            'name'     => $user->name,
            'username' => $user->username,
            'token'    => $token,
        ], 201);
    }

    public function logout(): JsonResponse
    {
        auth()->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
```

---

### 7b. Create `app/Http/Controllers/CheckInController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCheckInRequest;
use App\Http\Resources\CheckInResource;
use App\Services\RiskCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CheckInController extends Controller
{
    public function __construct(private RiskCalculatorService $riskCalculator) {}

    // GET /checkin — full history for the logged-in user
    public function index(): AnonymousResourceCollection
    {
        $checkIns = auth()->user()
            ->checkIns()
            ->orderByDesc('checked_in_at')
            ->get();

        return CheckInResource::collection($checkIns);
    }

    // POST /checkin — submit today's check-in
    public function store(StoreCheckInRequest $request): CheckInResource
    {
        $data   = $request->validated();
        $result = $this->riskCalculator->calculate($data);

        $checkIn = auth()->user()->checkIns()->create([
            'pid'          => $data['pid'],
            'name'         => $data['name'],
            'genotype'     => $data['genotype'],
            'meds'         => $data['meds'],
            'pain'         => $data['pain'],
            'fatigue'      => $data['fatigue'],
            'sleep'        => $data['sleep'],
            'hydration'    => $data['hydration'],
            'condition'    => $data['condition'],
            'safety'       => $data['safety'],
            'notes'        => $data['notes'] ?? null,
            'symptoms'     => $data['symptoms'] ?? [],
            'flags'        => $data['flags'] ?? [],
            'triggers'     => $data['triggers'] ?? [],
            'total'        => $result['total'],
            'display_score' => $result['display_score'],
            'status'       => $result['status'],
            'red_flag'     => $result['red_flag'],
            'reason'       => $result['reason'],
            'scores'       => $result['scores'],
            'geno_mult'    => $result['geno_mult'],
            'checked_in_at' => now(),
        ]);

        return new CheckInResource($checkIn);
    }

    // GET /checkin/latest — most recent check-in result
    public function latest(): JsonResponse|CheckInResource
    {
        $checkIn = auth()->user()->checkIns()->latest('checked_in_at')->first();

        if (!$checkIn) {
            return response()->json(['message' => 'No check-ins yet'], 404);
        }

        return new CheckInResource($checkIn);
    }
}
```

---

## 8. Routes

Replace or update `routes/api.php`:

```php
<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckInController;
use Illuminate\Support\Facades\Route;

// Public — no token required
Route::prefix('auth')->group(function () {
    Route::post('login',    [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
});

// Protected — Bearer token required (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('checkin',        [CheckInController::class, 'index']);   // get history
    Route::post('checkin',       [CheckInController::class, 'store']);   // submit check-in
    Route::get('checkin/latest', [CheckInController::class, 'latest']);  // latest result
});
```

---

## 9. CORS (if the frontend is on a different domain/port)

In `config/cors.php`, update:

```php
'allowed_origins'  => [env('FRONTEND_URL', 'http://localhost:3000')],
'allowed_methods'  => ['*'],
'allowed_headers'  => ['*'],
'supports_credentials' => false,
```

Add to `.env`:

```
FRONTEND_URL=https://your-frontend-domain.com
```

---

## 10. Quick Reference — API Endpoints

| Method | URL               | Auth required | Description                      |
|--------|-------------------|---------------|----------------------------------|
| POST   | /auth/login       | No            | Login, returns token             |
| POST   | /auth/register    | No            | Register, returns token          |
| POST   | /auth/logout      | Yes           | Revoke current token             |
| POST   | /checkin          | Yes           | Submit check-in, returns result  |
| GET    | /checkin          | Yes           | Get full check-in history        |
| GET    | /checkin/latest   | Yes           | Get most recent check-in result  |

---

## 11. Directory Checklist

Files to create (new):

```
app/
  Models/
    CheckIn.php                              ← new
  Services/
    RiskCalculatorService.php               ← new
  Http/
    Controllers/
      AuthController.php                    ← new
      CheckInController.php                 ← new
    Requests/
      Auth/
        LoginRequest.php                    ← new
        RegisterRequest.php                 ← new
      StoreCheckInRequest.php               ← new
    Resources/
      CheckInResource.php                   ← new

database/
  migrations/
    xxxx_add_username_to_users_table.php    ← new
    xxxx_create_check_ins_table.php         ← new
```

Files to update (existing):

```
app/Models/User.php                         ← add HasApiTokens + checkIns() relation
app/Providers/AppServiceProvider.php        ← register RiskCalculatorService singleton
routes/api.php                              ← add all routes
config/cors.php                             ← add FRONTEND_URL to allowed_origins
bootstrap/app.php                           ← add Sanctum middleware to api group
.env                                        ← add FRONTEND_URL
```
