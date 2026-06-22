<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class StoreAdminToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token || $token !== config('app.store_admin_token')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}
