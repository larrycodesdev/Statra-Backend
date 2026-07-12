<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsStaff
{
    private const ALLOWED_ROLES       = ['doctor', 'admin', 'superadmin', 'staff'];
    private const APPROVAL_REQUIRED   = ['doctor', 'staff'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, self::ALLOWED_ROLES)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied.',
            ], 403);
        }

        if (in_array($user->role, self::APPROVAL_REQUIRED) && !$user->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Account pending approval.',
            ], 403);
        }

        return $next($request);
    }
}
