<?php

namespace App\Http\Middleware;

use App\Models\StoreAdmin;
use Closure;
use Illuminate\Http\Request;

class StoreAdminToken
{
    public function handle(Request $request, Closure $next)
    {
        $plain = $request->bearerToken();

        if (!$plain) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $admin = StoreAdmin::findByToken($plain);

        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        // Make the authenticated admin available to controllers
        $request->merge(['_store_admin' => $admin]);

        return $next($request);
    }
}
