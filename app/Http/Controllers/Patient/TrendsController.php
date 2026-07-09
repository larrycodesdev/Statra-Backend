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
            $today  = now()->toDateString();
            $nowStr = now()->format('Y-m-d H:i');

            $medications = $patient->medications()
                ->where('active', true)
                ->orderByDesc('created_at')
                ->get();

            // Latest log per medication in one query
            $latestLogs = $patient->medicationLogs()
                ->whereIn('id', function ($q) use ($patient) {
                    $q->selectRaw('MAX(id)')
                        ->from('medication_logs')
                        ->where('patient_id', $patient->id)
                        ->groupBy('medication_id');
                })
                ->get()
                ->keyBy('medication_id');

            $medications = $medications->map(function ($med) use ($latestLogs, $today, $nowStr) {
                $times     = $med->reminder_times ?? [['period' => null, 'time' => '08:00']];
                $latestLog = $latestLogs->get($med->id);

                if ($latestLog) {
                    $logTime = $latestLog->scheduled_at->format('H:i');
                    $matched = collect($times)->first(fn($item) =>
                        (is_array($item) ? ($item['time'] ?? $item) : $item) === $logTime
                    );
                    $period = is_array($matched) ? ($matched['period'] ?? null) : null;

                    return [
                        'medication_id'  => (int) $med->id,
                        'name'           => $med->name,
                        'dosage'         => $med->dosage,
                        'frequency'      => $med->frequency,
                        'period'         => $period,
                        'scheduled_time' => $logTime,
                        'scheduled_at'   => $latestLog->scheduled_at->format('Y-m-d H:i'),
                        'status'         => $latestLog->status,
                        'taken_at'       => $latestLog->taken_at,
                    ];
                }

                $next      = collect($times)->first(fn($item) =>
                    ($today . ' ' . (is_array($item) ? ($item['time'] ?? '08:00') : $item)) > $nowStr
                ) ?? $times[0] ?? ['period' => null, 'time' => '08:00'];

                $timeStr   = is_array($next) ? ($next['time']   ?? '08:00') : $next;
                $periodStr = is_array($next) ? ($next['period'] ?? null)    : null;

                return [
                    'medication_id'  => (int) $med->id,
                    'name'           => $med->name,
                    'dosage'         => $med->dosage,
                    'frequency'      => $med->frequency,
                    'period'         => $periodStr,
                    'scheduled_time' => $timeStr,
                    'scheduled_at'   => $today . ' ' . $timeStr,
                    'status'         => 'upcoming',
                    'taken_at'       => null,
                ];
            })->values();

            // 30-day adherence stats from actual logs
            $allLogs = $patient->medicationLogs()
                ->where('scheduled_at', '>=', now()->subDays(30))
                ->get(['status']);

            $total  = $allLogs->count();
            $taken  = $allLogs->where('status', 'taken')->count();
            $missed = $allLogs->where('status', 'missed')->count();

            $data['medication'] = [
                'medications'    => $medications,
                'adherence_rate' => $total > 0 ? round($taken / $total * 100, 1) : 0,
                'total_30d'      => $total,
                'taken_30d'      => $taken,
                'missed_30d'     => $missed,
                'care_team_alert' => $missed > 0
                    ? "You missed {$missed} dose(s) in the last 30 days. Your care team has been notified."
                    : null,
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
