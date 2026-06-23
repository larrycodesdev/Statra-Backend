<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SymptomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->patient->symptoms()->orderByDesc('logged_at');

        if ($request->period === 'week') {
            $query->where('logged_at', '>=', now()->startOfWeek());
        } elseif ($request->period === 'month') {
            $query->where('logged_at', '>=', now()->startOfMonth());
        }

        if ($request->type === 'pain') {
            $query->where('severity', '>=', 1);
        } elseif ($request->type === 'crisis') {
            $query->where('severity', '>=', 8);
        }

        return ApiResponse::paginated($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'symptom'          => ['required', 'string', 'max:255'],
            'severity'         => ['required', 'integer', 'min:1', 'max:10'],
            'severity_label'   => ['nullable', 'in:none,mild,moderate,severe'],
            'body_locations'   => ['nullable', 'array'],
            'body_locations.*' => ['string'],
            'pain_areas'       => ['nullable', 'array'],
            'pain_areas.*'     => ['string'],
            'pain_level'       => ['nullable', 'integer', 'min:0', 'max:10'],
            'duration'         => ['nullable', 'string', 'max:100'],
            'triggers'         => ['nullable', 'array'],
            'triggers.*'       => ['string'],
            'on_medication'    => ['nullable', 'boolean'],
            'notes'            => ['nullable', 'string', 'max:1000'],
            'mood'             => ['nullable', 'in:low,okay,alright,good'],
            'logged_at'        => ['nullable', 'date'],
        ]);

        $symptom = $request->user()->patient->symptoms()->create([
            ...$data,
            'logged_at' => $data['logged_at'] ?? now(),
        ]);

        return ApiResponse::created($symptom, 'Symptom logged successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $symptom = $request->user()->patient->symptoms()->findOrFail($id);
        return ApiResponse::success($symptom);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $symptom = $request->user()->patient->symptoms()->findOrFail($id);

        if ($symptom->edit_count >= 1) {
            return ApiResponse::error('This entry can only be edited once.', 403);
        }

        $data = $request->validate([
            'symptom'          => ['sometimes', 'string', 'max:255'],
            'severity'         => ['sometimes', 'integer', 'min:1', 'max:10'],
            'severity_label'   => ['nullable', 'in:none,mild,moderate,severe'],
            'body_locations'   => ['nullable', 'array'],
            'body_locations.*' => ['string'],
            'pain_areas'       => ['nullable', 'array'],
            'pain_areas.*'     => ['string'],
            'pain_level'       => ['nullable', 'integer', 'min:0', 'max:10'],
            'duration'         => ['nullable', 'string', 'max:100'],
            'triggers'         => ['nullable', 'array'],
            'triggers.*'       => ['string'],
            'on_medication'    => ['nullable', 'boolean'],
            'notes'            => ['nullable', 'string', 'max:1000'],
            'mood'             => ['nullable', 'in:low,okay,alright,good'],
        ]);

        $symptom->update(array_merge($data, ['edit_count' => 1]));

        return ApiResponse::success($symptom->fresh(), 'Symptom updated.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->patient->symptoms()->findOrFail($id)->delete();
        return ApiResponse::success(null, 'Symptom entry deleted.');
    }

    public function stats(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;

        $total      = $patient->symptoms()->count();
        $daysLogged = $patient->symptoms()
            ->selectRaw('COUNT(DISTINCT CAST(logged_at AS DATE)) as days')
            ->value('days') ?? 0;
        $avgSeverity = round((float) ($patient->symptoms()->avg('severity') ?? 0), 1);

        // Risk chart: avg severity per day for last 7 days
        $riskChart = $patient->symptoms()
            ->where('logged_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('CAST(logged_at AS DATE) as day, AVG(CAST(severity AS FLOAT)) as avg_severity, COUNT(*) as count')
            ->groupByRaw('CAST(logged_at AS DATE)')
            ->orderByRaw('CAST(logged_at AS DATE)')
            ->get();

        return ApiResponse::success([
            'total_symptoms' => $total,
            'days_logged'    => (int) $daysLogged,
            'avg_severity'   => $avgSeverity,
            'risk_chart'     => $riskChart,
        ]);
    }
}
