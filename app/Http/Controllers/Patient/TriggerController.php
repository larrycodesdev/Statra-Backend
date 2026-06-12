<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TriggerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;
        $period  = $request->get('period', '30days');

        $from = match ($period) {
            'today'  => now()->startOfDay(),
            '7days'  => now()->subDays(7)->startOfDay(),
            default  => now()->subDays(30)->startOfDay(),
        };

        $symptoms = $patient->symptoms()
            ->where('logged_at', '>=', $from)
            ->whereNotNull('triggers')
            ->get(['triggers', 'logged_at']);

        // Count trigger frequency
        $counts = [];
        foreach ($symptoms as $symptom) {
            foreach ($symptom->triggers ?? [] as $trigger) {
                $counts[$trigger] = ($counts[$trigger] ?? 0) + 1;
            }
        }
        arsort($counts);

        $triggers = collect($counts)->map(fn ($count, $name) => [
            'name'  => $name,
            'count' => $count,
        ])->values();

        return ApiResponse::success([
            'period'           => $period,
            'triggers'         => $triggers,
            'pattern_insights' => $this->detectPatterns($symptoms),
        ]);
    }

    private function detectPatterns($symptoms): array
    {
        $coOccurrence = [];

        foreach ($symptoms as $s) {
            $triggers = $s->triggers ?? [];
            if (count($triggers) >= 2) {
                sort($triggers);
                $pair                    = implode(' + ', array_slice($triggers, 0, 2));
                $coOccurrence[$pair] = ($coOccurrence[$pair] ?? 0) + 1;
            }
        }

        arsort($coOccurrence);

        return collect($coOccurrence)
            ->take(3)
            ->filter(fn ($count) => $count >= 2)
            ->map(fn ($count, $pair) => [
                'pattern'     => $pair,
                'description' => "Often appear together — occurred {$count} times this period",
            ])
            ->values()
            ->all();
    }
}
