<?php

use App\Http\Middleware\EnsureIsDoctor;
use App\Http\Middleware\EnsureIsPatient;
use App\Http\Middleware\EnsureIsStaff;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        using: function () {
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::middleware('api')
                ->prefix('api/v1/patient')
                ->group(base_path('routes/api_patient.php'));

            Route::middleware('api')
                ->prefix('api/v1/doctor')
                ->group(base_path('routes/api_doctor.php'));

            Route::middleware('api')
                ->prefix('api/v1/checkin')
                ->group(base_path('routes/api_checkin.php'));

            Route::middleware('api')
                ->prefix('api/v1/store')
                ->group(base_path('routes/api_store.php'));

            Route::middleware('api')
                ->prefix('api/v1/website')
                ->group(base_path('routes/api_website.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'patient'     => EnsureIsPatient::class,
            'doctor'      => EnsureIsDoctor::class,
            'staff'       => EnsureIsStaff::class,
            'store.admin' => \App\Http\Middleware\StoreAdminToken::class,

            'ability'   => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Return JSON for unauthenticated requests on API routes
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });

        // Return JSON for validation errors on API routes
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        // Return JSON for model not found on API routes
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                ], 404);
            }
        });

        // Return JSON for authorization errors
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied.',
                ], 403);
            }
        });

        // Catch-all: return JSON 500 for any unhandled exception on API routes
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => app()->hasDebugModeEnabled()
                        ? $e->getMessage()
                        : 'Server error.',
                ], 500);
            }
        });
    })->create();
