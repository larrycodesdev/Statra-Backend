<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrendsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;
        $filter  = $request->get('filter', 'all');
        $data    = [];

        if (in_array($filter, ['all', 'pain'])) {
            $data['most_frequent_symptoms'] = $patient->symptoms()
                ->selectRaw('symptom, COUNT(*) as count')
                ->groupBy('symptom')
                ->orderByRaw('COUNT(*) DESC')
                ->limit(5)
                ->get();

            $data['care_team_alert'] = $this->painCareTeamAlert($patient);
        }

        if (in_array($filter, ['all', 'triggers'])) {
            $symptomsWithTriggers = $patient->symptoms()
                ->where('logged_at', '>=', now()->subDays(30))
                ->whereNotNull('triggers')
                ->get(['triggers']);

            $triggerCounts = [];
            foreach ($symptomsWithTriggers as $s) {
                foreach ($s->triggers ?? [] as $trigger) {
                    $triggerCounts[$trigger] = ($triggerCounts[$trigger] ?? 0) + 1;
                }
            }
            arsort($triggerCounts);

            $data['top_triggers'] = collect($triggerCounts)
                ->take(5)
                ->map(fn ($count, $name) => ['name' => $name, 'count' => $count])
                ->values();

            $data['pattern_insights'] = $this->detectPatterns($symptomsWithTriggers);

            if (!isset($data['care_team_alert'])) {
                $data['care_team_alert'] = $this->triggerCareTeamAlert($triggerCounts);
            }
        }

        if (in_array($filter, ['all', 'medication'])) {
            $logs = $patient->medicationLogs()
                ->where('scheduled_at', '>=', now()->subDays(30))
                ->get(['medication_name', 'dosage', 'status', 'scheduled_at', 'taken_at']);

            $total  = $logs->count();
            $taken  = $logs->where('status', 'taken')->count();
            $missed = $logs->where('status', 'missed')->count();

            $data['medication'] = [
                'total'            => $total,
                'taken'            => $taken,
                'missed'           => $missed,
                'adherence_rate'   => $total > 0 ? round($taken / $total * 100, 1) : 0,
                'recent'           => $logs->sortByDesc('scheduled_at')->take(5)->values(),
            ];
        }

        if (in_array($filter, ['all', 'mood'])) {
            $moods = $patient->symptoms()
                ->whereNotNull('mood')
                ->where('logged_at', '>=', now()->subDays(30))
                ->selectRaw('mood, COUNT(*) as count')
                ->groupBy('mood')
                ->orderByRaw('COUNT(*) DESC')
                ->get();

            $data['mood'] = $moods;

            $data['mood_care_team_alert'] = $this->moodCareTeamAlert($patient);
        }

        return ApiResponse::success($data);
    }

    private function painCareTeamAlert($patient): ?string
    {
        $recent = $patient->symptoms()
            ->where('severity', '>=', 8)
            ->where('logged_at', '>=', now()->subDays(7))
            ->latest('logged_at')
            ->first();

        if (!$recent) {
            return null;
        }

        return "Severe {$recent->symptom} logged on {$recent->logged_at->format('M d')}. Your care team has been notified.";
    }

    private function triggerCareTeamAlert(array $triggerCounts): ?string
    {
        if (empty($triggerCounts)) {
            return null;
        }

        $topTrigger = array_key_first($triggerCounts);
        $count      = $triggerCounts[$topTrigger];

        if ($count >= 3) {
            return "High {$topTrigger} trigger frequency detected. Your care team has been notified.";
        }

        return null;
    }

    private function moodCareTeamAlert($patient): ?string
    {
        $lowMoodCount = $patient->symptoms()
            ->where('mood', 'low')
            ->where('logged_at', '>=', now()->subDays(7))
            ->count();

        if ($lowMoodCount >= 3) {
            return 'Your mood has been low frequently this week. Your care team has been notified.';
        }

        $stableCount = $patient->symptoms()
            ->whereIn('mood', ['alright', 'good'])
            ->where('logged_at', '>=', now()->subDays(7))
            ->count();

        if ($stableCount >= 3) {
            return 'Your mood has been stable this week. Your care team has been notified.';
        }

        return null;
    }

    private function detectPatterns($symptoms): array
    {
        $coOccurrence = [];

        foreach ($symptoms as $s) {
            $triggers = $s->triggers ?? [];
            if (count($triggers) >= 2) {
                sort($triggers);
                $pair                = implode(' + ', array_slice($triggers, 0, 2));
                $coOccurrence[$pair] = ($coOccurrence[$pair] ?? 0) + 1;
            }
        }

        arsort($coOccurrence);

        return collect($coOccurrence)
            ->take(3)
            ->filter(fn ($count) => $count >= 2)
            ->map(fn ($count, $pair) => [
                'pattern'     => $pair,
                'description' => "Often appear together — occurred {$count} times this month",
            ])
            ->values()
            ->all();
    }
}
