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

        // Date range — period takes priority, then specific date
        $period = $request->input('period');
        $date   = $request->input('date');

        if ($period === 'today') {
            $query->whereDate('logged_at', now()->toDateString());
        } elseif ($period === 'week') {
            $query->where('logged_at', '>=', now()->startOfWeek());
        } elseif ($period === 'month') {
            $query->where('logged_at', '>=', now()->startOfMonth());
        } elseif ($date) {
            $query->whereDate('logged_at', $date);
        }

        // Severity label filter
        if ($request->filled('severity')) {
            $query->where('severity_label', $request->input('severity'));
        }

        // Mood filter
        if ($request->filled('mood')) {
            $query->where('mood', $request->input('mood'));
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

    public function clearAll(Request $request): JsonResponse
    {
        $request->user()->patient->symptoms()->delete();
        return ApiResponse::success(null, 'All symptom entries cleared.');
    }

    public function stats(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;
        $range   = $request->input('range', '7d');

        $days = match ($range) {
            '30d'   => 30,
            '90d'   => 90,
            default => 7,
        };

        $since = now()->subDays($days - 1)->startOfDay();

        $total      = $patient->symptoms()->count();
        $daysLogged = $patient->symptoms()
            ->selectRaw('COUNT(DISTINCT CAST(logged_at AS DATE)) as days')
            ->value('days') ?? 0;
        $avgSeverity = round((float) ($patient->symptoms()->avg('severity') ?? 0), 1);

        // Per-day chart data for the selected range
        $chart = $patient->symptoms()
            ->where('logged_at', '>=', $since)
            ->selectRaw('CAST(logged_at AS DATE) as date, COUNT(*) as count, AVG(CAST(severity AS FLOAT)) as avg_severity')
            ->groupByRaw('CAST(logged_at AS DATE)')
            ->orderByRaw('CAST(logged_at AS DATE)')
            ->get()
            ->map(fn($row) => [
                'date'         => $row->date,
                'count'        => (int) $row->count,
                'avg_severity' => round((float) $row->avg_severity, 1),
            ]);

        return ApiResponse::success([
            'total_symptoms' => $total,
            'days_logged'    => (int) $daysLogged,
            'avg_severity'   => $avgSeverity,
            'range'          => $range,
            'chart'          => $chart,
        ]);
    }
}
