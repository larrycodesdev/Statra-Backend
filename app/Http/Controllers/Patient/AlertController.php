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
        $patient = $request->user()->patient;
        $filter  = $request->get('filter', 'all');

        $alertsQuery = $patient->alerts()
            ->with('vitalReading:id,type,value,unit,recorded_at')
            ->orderByDesc('created_at');

        if ($filter === 'active') {
            $alertsQuery->whereIn('status', ['pending', 'acknowledged']);
        }

        $alerts        = $alertsQuery->paginate(20);
        $smartInsights = in_array($filter, ['all', 'smart']) ? $this->smartInsights($patient) : [];

        return ApiResponse::success([
            'smart_insights' => $smartInsights,
            'alerts'         => $alerts->items(),
            'meta'           => [
                'total'        => $alerts->total(),
                'current_page' => $alerts->currentPage(),
                'last_page'    => $alerts->lastPage(),
            ],
        ]);
    }

    private function smartInsights($patient): array
    {
        $insights = [];

        // Trigger pattern detection
        $recentSymptoms = $patient->symptoms()
            ->where('logged_at', '>=', now()->subDays(30))
            ->whereNotNull('triggers')
            ->get(['triggers']);

        $triggerCounts = [];
        foreach ($recentSymptoms as $s) {
            foreach ($s->triggers ?? [] as $trigger) {
                $triggerCounts[$trigger] = ($triggerCounts[$trigger] ?? 0) + 1;
            }
        }

        if (!empty($triggerCounts)) {
            arsort($triggerCounts);
            $topTrigger = array_key_first($triggerCounts);
            $count      = $triggerCounts[$topTrigger];

            if ($count >= 3) {
                $insights[] = [
                    'type'    => 'pattern',
                    'title'   => 'Pattern Detected',
                    'message' => "High {$topTrigger} trigger frequency detected. Your care team has been notified.",
                    'tag'     => 'Pattern',
                ];
            }
        }

        // Weekly medication adherence summary
        $weekLogs = $patient->medicationLogs()
            ->where('scheduled_at', '>=', now()->subWeek())
            ->get(['status']);

        if ($weekLogs->isNotEmpty()) {
            $total    = $weekLogs->count();
            $taken    = $weekLogs->where('status', 'taken')->count();
            $rate     = round($taken / $total * 100);

            $insights[] = [
                'type'    => 'weekly',
                'title'   => 'Weekly Summary',
                'message' => "{$rate}% medication adherence this week.",
                'tag'     => 'Weekly',
            ];
        }

        // Trigger warning based on vitals/symptoms trends
        $highPain = $patient->symptoms()
            ->where('severity', '>=', 8)
            ->where('logged_at', '>=', now()->subDays(7))
            ->count();

        if ($highPain >= 2) {
            $insights[] = [
                'type'    => 'trigger_warning',
                'title'   => 'Trigger Warning',
                'message' => 'High pain episodes detected this week. Monitor your triggers and stay hydrated.',
                'tag'     => 'Weekly',
            ];
        }

        return $insights;
    }
}
