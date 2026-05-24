<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsPatient
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->role !== 'patient') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Patient account required.',
            ], 403);
        }

        if (!$user->patient) {
            return response()->json([
                'success' => false,
                'message' => 'Patient profile not found.',
            ], 404);
        }

        // Attach the patient profile to the request for easy access in controllers
        $request->merge(['_patient' => $user->patient]);

        return $next($request);
    }
}
