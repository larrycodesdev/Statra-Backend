<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $appointments = $request->user()->patient
            ->appointments()
            ->with('doctor:id,name,email,avatar')
            ->orderByDesc('scheduled_at')
            ->paginate(20);

        return ApiResponse::paginated($appointments);
    }
}
