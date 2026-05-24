<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PainLogController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pain_level' => ['required', 'integer', 'min:1', 'max:10'],
            'location'   => ['nullable', 'array'],
            'notes'      => ['nullable', 'string', 'max:1000'],
            'logged_at'  => ['nullable', 'date'],
        ]);

        $log = $request->user()->patient->painLogs()->create([
            'pain_level' => $data['pain_level'],
            'location'   => $data['location'] ?? null,
            'notes'      => $data['notes'] ?? null,
            'logged_at'  => $data['logged_at'] ?? now(),
        ]);

        return ApiResponse::created($log, 'Pain log recorded.');
    }

    public function index(Request $request): JsonResponse
    {
        $logs = $request->user()->patient
            ->painLogs()
            ->orderByDesc('logged_at')
            ->paginate(20);

        return ApiResponse::paginated($logs);
    }
}
