<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use DateInterval;
use DatePeriod;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;
        $today   = now()->toDateString();

        $medications = $patient->medications()->orderByDesc('created_at')->get();

        $todayLogs = $patient->medicationLogs()
            ->whereDate('scheduled_at', $today)
            ->get()
            ->groupBy('medication_id');

        $result = $medications->map(function ($medication) use ($todayLogs) {
            $expectedDoses = count($medication->reminder_times ?? [['time' => '08:00']]);
            $logs          = $todayLogs->get($medication->id, collect());
            $takenCount    = $logs->where('status', 'taken')->count();
            $missedCount   = $logs->where('status', 'missed')->count();

            $todayStatus = match (true) {
                ($takenCount + $missedCount) === 0 => 'pending',
                $takenCount === $expectedDoses     => 'taken',
                $missedCount === $expectedDoses    => 'missed',
                default                            => 'partial',
            };

            return array_merge($medication->toArray(), ['today_status' => $todayStatus]);
        });

        return ApiResponse::success($result);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'dosage'                  => ['nullable', 'string', 'max:100'],
            'time_to_use'             => ['nullable', 'string', 'max:100'],
            'frequency'               => ['nullable', 'string', 'max:100'],
            'frequency_count'         => ['nullable', 'integer', 'min:1', 'max:10'],
            'begin_date'              => ['nullable', 'date'],
            'end_date'                => ['nullable', 'date', 'after_or_equal:begin_date'],
            'reminder_times'          => ['nullable', 'array'],
            'reminder_times.*.period' => ['required_with:reminder_times', 'string', 'in:morning,afternoon,evening,night'],
            'reminder_times.*.time'   => ['required_with:reminder_times', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'remind_me'               => ['nullable', 'boolean'],
        ]);

        $medication = $request->user()->patient->medications()->create($data);

        return ApiResponse::created($medication, 'Medication added successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $medication = $request->user()->patient->medications()->findOrFail($id);
        return ApiResponse::success($medication);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $medication = $request->user()->patient->medications()->findOrFail($id);

        $data = $request->validate([
            'name'                    => ['sometimes', 'string', 'max:255'],
            'dosage'                  => ['nullable', 'string', 'max:100'],
            'time_to_use'             => ['nullable', 'string', 'max:100'],
            'frequency'               => ['nullable', 'string', 'max:100'],
            'frequency_count'         => ['nullable', 'integer', 'min:1', 'max:10'],
            'begin_date'              => ['nullable', 'date'],
            'end_date'                => ['nullable', 'date'],
            'reminder_times'          => ['nullable', 'array'],
            'reminder_times.*.period' => ['required_with:reminder_times', 'string', 'in:morning,afternoon,evening,night'],
            'reminder_times.*.time'   => ['required_with:reminder_times', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'remind_me'               => ['nullable', 'boolean'],
            'active'                  => ['nullable', 'boolean'],
        ]);

        $medication->update($data);
        $medication->refresh();

        return ApiResponse::success($medication, 'Medication updated.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $medication = $request->user()->patient->medications()->findOrFail($id);
        $medication->logs()->update(['medication_id' => null]);
        $medication->delete();
        return ApiResponse::success(null, 'Medication removed.');
    }

    public function clearAll(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;
        $patient->medicationLogs()->delete();
        $patient->medications()->delete();
        return ApiResponse::success(null, 'All medications and logs cleared.');
    }

    // GET /medications/log?date=2026-06-12&filter=all|taken|missed
    public function log(Request $request): JsonResponse
    {
        $date    = $request->input('date', now()->toDateString());
        $filter  = $request->input('filter', 'all');
        $patient = $request->user()->patient;

        $medications = $patient->medications()
            ->where('active', true)
            ->where(function ($q) use ($date) {
                $q->whereNull('begin_date')->orWhere('begin_date', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $date);
            })
            ->get();

        $logs = $patient->medicationLogs()
            ->whereDate('scheduled_at', $date)
            ->get()
            ->groupBy('medication_id');

        $upcoming = [];
        $history  = [];

        foreach ($medications as $medication) {
            $times = $medication->reminder_times ?? [['period' => null, 'time' => '08:00']];

            foreach ($times as $item) {
                // Support both old string format and new object format
                $timeStr   = is_array($item) ? ($item['time']   ?? $item) : $item;
                $periodStr = is_array($item) ? ($item['period'] ?? null)  : null;

                $scheduledAt = $date . ' ' . $timeStr;
                $entry = [
                    'medication_id'  => $medication->id,
                    'name'           => $medication->name,
                    'dosage'         => $medication->dosage,
                    'frequency'      => $medication->frequency,
                    'period'         => $periodStr,
                    'scheduled_time' => $timeStr,
                    'scheduled_at'   => $scheduledAt,
                ];

                $medLogs    = $logs->get($medication->id);
                $matchedLog = $medLogs?->first(
                    fn($log) => $log->scheduled_at->format('H:i') === $timeStr
                );

                if ($matchedLog) {
                    $history[] = array_merge($entry, [
                        'status'   => $matchedLog->status,
                        'taken_at' => $matchedLog->taken_at,
                    ]);
                } else {
                    $upcoming[] = $entry;
                }
            }
        }

        if ($filter === 'taken') {
            $history  = array_values(array_filter($history, fn($h) => $h['status'] === 'taken'));
            $upcoming = [];
        } elseif ($filter === 'missed') {
            $history  = array_values(array_filter($history, fn($h) => $h['status'] === 'missed'));
            $upcoming = [];
        }

        return ApiResponse::success([
            'date'     => $date,
            'upcoming' => array_values($upcoming),
            'history'  => array_values($history),
            'summary'  => [
                'total'  => count($upcoming) + count($history),
                'taken'  => count(array_filter($history, fn($h) => $h['status'] === 'taken')),
                'missed' => count(array_filter($history, fn($h) => $h['status'] === 'missed')),
            ],
        ]);
    }

    // GET /medications/history?status=taken|missed|upcoming&start_date=&end_date=&page=1&per_page=20
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'status'     => ['nullable', 'string', 'in:taken,missed,upcoming'],
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date'],
            'page'       => ['nullable', 'integer', 'min:1'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $patient = $request->user()->patient;
        $status  = $request->input('status');
        $start   = $request->input('start_date');
        $end     = $request->input('end_date');
        $perPage = (int) $request->input('per_page', 20);
        $page    = (int) $request->input('page', 1);

        $entries = collect();

        // --- Real logs: taken + missed ---
        if (!$status || in_array($status, ['taken', 'missed'])) {
            $logsQuery = $patient->medicationLogs()
                ->with('medication')
                ->whereIn('status', $status ? [$status] : ['taken', 'missed']);

            if ($start) $logsQuery->where('scheduled_at', '>=', $start . ' 00:00:00');
            if ($end)   $logsQuery->where('scheduled_at', '<=', $end   . ' 23:59:59');

            foreach ($logsQuery->get() as $log) {
                $logTime  = $log->scheduled_at->format('H:i');
                $times    = $log->medication?->reminder_times ?? [];
                $matched  = collect($times)->first(fn($item) =>
                    (is_array($item) ? ($item['time'] ?? $item) : $item) === $logTime
                );
                $period = is_array($matched) ? ($matched['period'] ?? null) : null;

                $entries->push([
                    'medication_id'  => (int) $log->medication_id,
                    'name'           => $log->medication_name,
                    'dosage'         => $log->dosage,
                    'frequency'      => $log->medication?->frequency,
                    'period'         => $period,
                    'scheduled_time' => $logTime,
                    'scheduled_at'   => $log->scheduled_at->format('Y-m-d H:i'),
                    'status'         => $log->status,
                    'taken_at'       => $log->taken_at,
                ]);
            }
        }

        // --- Upcoming: dynamically generated for today → end (max 30 days) ---
        if (!$status || $status === 'upcoming') {
            $upcomingStart = $start
                ? max($start, now()->toDateString())
                : now()->toDateString();

            $upcomingEnd = $end ?? now()->addDays(30)->toDateString();

            if ($upcomingStart <= $upcomingEnd) {
                $medications = $patient->medications()->where('active', true)->get();

                // Index existing logs in the upcoming window to exclude already-logged slots
                $existingKeys = $patient->medicationLogs()
                    ->where('scheduled_at', '>=', $upcomingStart . ' 00:00:00')
                    ->where('scheduled_at', '<=', $upcomingEnd   . ' 23:59:59')
                    ->get(['medication_id', 'scheduled_at'])
                    ->mapWithKeys(fn($log) => [
                        $log->medication_id . '_' . $log->scheduled_at->format('Y-m-d H:i') => true,
                    ]);

                $period = new DatePeriod(
                    new DateTime($upcomingStart),
                    new DateInterval('P1D'),
                    (new DateTime($upcomingEnd))->modify('+1 day')
                );

                $nowStr = now()->format('Y-m-d H:i');

                foreach ($period as $date) {
                    $dateStr = $date->format('Y-m-d');

                    foreach ($medications as $medication) {
                        if ($medication->begin_date && $medication->begin_date->toDateString() > $dateStr) continue;
                        if ($medication->end_date   && $medication->end_date->toDateString()   < $dateStr) continue;

                        $times = $medication->reminder_times ?? [['period' => null, 'time' => '08:00']];

                        foreach ($times as $item) {
                            $timeStr      = is_array($item) ? ($item['time']   ?? $item) : $item;
                            $periodStr    = is_array($item) ? ($item['period'] ?? null)  : null;
                            $scheduledAt  = $dateStr . ' ' . $timeStr;

                            if ($scheduledAt <= $nowStr) continue;
                            if ($existingKeys->has($medication->id . '_' . $scheduledAt)) continue;

                            $entries->push([
                                'medication_id'  => $medication->id,
                                'name'           => $medication->name,
                                'dosage'         => $medication->dosage,
                                'frequency'      => $medication->frequency,
                                'period'         => $periodStr,
                                'scheduled_time' => $timeStr,
                                'scheduled_at'   => $scheduledAt,
                                'status'         => 'upcoming',
                                'taken_at'       => null,
                            ]);
                        }
                    }
                }
            }
        }

        $sorted = $entries->sortByDesc('scheduled_at')->values();
        $total  = $sorted->count();
        $items  = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        return ApiResponse::success([
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page'    => max(1, (int) ceil($total / $perPage)),
                'per_page'     => $perPage,
                'total'        => $total,
            ],
        ]);
    }

    public function markTaken(Request $request, int $id): JsonResponse
    {
        return $this->recordStatus($request, $id, 'taken');
    }

    public function markMissed(Request $request, int $id): JsonResponse
    {
        return $this->recordStatus($request, $id, 'missed');
    }

    private function recordStatus(Request $request, int $id, string $status): JsonResponse
    {
        $medication = $request->user()->patient->medications()->findOrFail($id);

        $data = $request->validate([
            'scheduled_at' => ['required', 'date'],
        ]);

        $log = $request->user()->patient->medicationLogs()->updateOrCreate(
            [
                'medication_id' => $medication->id,
                'scheduled_at'  => $data['scheduled_at'],
            ],
            [
                'medication_name' => $medication->name,
                'dosage'          => $medication->dosage,
                'status'          => $status,
                'taken_at'        => $status === 'taken' ? now() : null,
            ]
        );

        $label = $status === 'taken' ? 'taken' : 'missed';

        return ApiResponse::success($log, "{$medication->name} recorded as {$label}.");
    }
}
