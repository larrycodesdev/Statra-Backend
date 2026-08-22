<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthDataExportController extends Controller
{
    // GET /api/v1/patient/health-data/export
    // Always accessible — no subscription gate
    public function export(Request $request): JsonResponse
    {
        $user    = $request->user();
        $patient = $user->patient;

        if (!$patient) {
            return response()->json(['success' => false, 'message' => 'Patient profile not found.'], 404);
        }

        $vitals = $patient->vitalReadings()
            ->orderByDesc('recorded_at')
            ->get(['type', 'value', 'unit', 'recorded_at'])
            ->map(fn ($v) => [
                'type'        => $v->type,
                'value'       => $v->value,
                'unit'        => $v->unit,
                'recorded_at' => $v->recorded_at,
            ]);

        $medications = $patient->medications()
            ->get(['name', 'dosage', 'frequency', 'start_date', 'end_date', 'is_active'])
            ->map(fn ($m) => [
                'name'       => $m->name,
                'dosage'     => $m->dosage,
                'frequency'  => $m->frequency,
                'start_date' => $m->start_date,
                'end_date'   => $m->end_date,
                'is_active'  => $m->is_active,
            ]);

        $symptoms = $patient->symptoms()
            ->orderByDesc('logged_at')
            ->get(['name', 'severity', 'logged_at', 'notes'])
            ->map(fn ($s) => [
                'name'      => $s->name,
                'severity'  => $s->severity,
                'logged_at' => $s->logged_at,
                'notes'     => $s->notes,
            ]);

        $scores = $patient->compositeDeviationScores()
            ->orderByDesc('computed_at')
            ->get(['total_score', 'status', 'outreach_recommended', 'computed_at'])
            ->map(fn ($sc) => [
                'total_score'          => round($sc->total_score, 2),
                'status'               => $sc->status,
                'outreach_recommended' => (bool) $sc->outreach_recommended,
                'computed_at'          => $sc->computed_at,
            ]);

        return response()->json([
            'success'    => true,
            'exported_at' => now()->toISOString(),
            'data'       => [
                'profile'     => [
                    'name'       => $user->full_name,
                    'email'      => $user->email,
                    'genotype'   => $patient->genotype,
                    'blood_type' => $patient->blood_type,
                    'dob'        => $patient->date_of_birth?->toDateString(),
                    'gender'     => $patient->gender,
                    'condition'  => $patient->condition,
                ],
                'vitals'      => $vitals,
                'medications' => $medications,
                'symptoms'    => $symptoms,
                'risk_scores' => $scores,
            ],
        ]);
    }
}
