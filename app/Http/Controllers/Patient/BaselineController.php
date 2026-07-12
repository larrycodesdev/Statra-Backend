<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BaselineController extends Controller
{
    public function score(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;

        $latest = $patient->compositeDeviationScores()
            ->orderByDesc('computed_at')
            ->first();

        $baselines = $patient->baselines()
            ->get(['signal_type', 'activity_context', 'rolling_mean', 'rolling_stddev', 'baseline_confidence', 'sample_count', 'last_updated_at'])
            ->groupBy('signal_type');

        return ApiResponse::success([
            'calibration_status' => $patient->calibration_status,
            'latest_score'       => $latest ? [
                'computed_at'          => $latest->computed_at,
                'status'               => $latest->status,
                'total_score'          => round($latest->total_score, 2),
                'confidence'           => $latest->confidence,
                'outreach_recommended' => $latest->outreach_recommended,
                'outreach_reason'      => $latest->outreach_reason,
                'signals'              => [
                    'temperature' => ['z' => $latest->temp_z,     'contribution' => $latest->temp_contribution],
                    'spo2'        => ['z' => $latest->spo2_z,     'contribution' => $latest->spo2_contribution],
                    'heart_rate'  => ['z' => $latest->hr_z,       'contribution' => $latest->hr_contribution],
                    'hrv'         => ['z' => $latest->hrv_z,      'contribution' => $latest->hrv_contribution],
                    'activity'    => ['z' => $latest->activity_z, 'contribution' => $latest->activity_contribution],
                ],
            ] : null,
            'baselines' => $baselines,
        ]);
    }
}
