<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsDoctor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->role !== 'doctor') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Doctor account required.',
            ], 403);
        }

        if (!$user->doctor) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor profile not found.',
            ], 404);
        }

        $request->merge(['_doctor' => $user->doctor]);

        return $next($request);
    }
}
