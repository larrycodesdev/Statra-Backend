<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $alerts = $request->user()->patient
            ->alerts()
            ->with('vitalReading:id,type,value,unit,recorded_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::paginated($alerts);
    }
}
