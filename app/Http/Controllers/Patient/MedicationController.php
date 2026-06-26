<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;
        $today   = now()->toDateString();

        $medications = $patient->medications()->orderByDesc('created_at')->get();

        // Load today's logs in one query, grouped by medication_id
        $todayLogs = $patient->medicationLogs()
            ->whereDate('scheduled_at', $today)
            ->get()
            ->groupBy('medication_id');

        $result = $medications->map(function ($medication) use ($todayLogs) {
            $expectedDoses = count($medication->reminder_times ?? ['08:00']);
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
            'name'              => ['required', 'string', 'max:255'],
            'dosage'            => ['nullable', 'string', 'max:100'],
            'time_to_use'       => ['nullable', 'string', 'max:100'],
            'frequency'         => ['nullable', 'string', 'max:100'],
            'frequency_count'   => ['nullable', 'integer', 'min:1', 'max:10'],
            'begin_date'        => ['nullable', 'date'],
            'end_date'          => ['nullable', 'date', 'after_or_equal:begin_date'],
            'reminder_times'    => ['nullable', 'array'],
            'reminder_times.*'  => ['string', 'regex:/^\d{2}:\d{2}$/'],
            'remind_me'         => ['nullable', 'boolean'],
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
            'name'              => ['sometimes', 'string', 'max:255'],
            'dosage'            => ['nullable', 'string', 'max:100'],
            'time_to_use'       => ['nullable', 'string', 'max:100'],
            'frequency'         => ['nullable', 'string', 'max:100'],
            'frequency_count'   => ['nullable', 'integer', 'min:1', 'max:10'],
            'begin_date'        => ['nullable', 'date'],
            'end_date'          => ['nullable', 'date'],
            'reminder_times'    => ['nullable', 'array'],
            'reminder_times.*'  => ['string', 'regex:/^\d{2}:\d{2}$/'],
            'remind_me'         => ['nullable', 'boolean'],
            'active'            => ['nullable', 'boolean'],
        ]);

        $medication->update($data);

        return ApiResponse::success($medication->fresh(), 'Medication updated.');
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
            $times = $medication->reminder_times ?? ['08:00'];

            foreach ($times as $time) {
                $scheduledAt = $date . ' ' . $time;
                $entry = [
                    'medication_id'  => $medication->id,
                    'name'           => $medication->name,
                    'dosage'         => $medication->dosage,
                    'frequency'      => $medication->frequency,
                    'scheduled_time' => $time,
                    'scheduled_at'   => $scheduledAt,
                ];

                $medLogs = $logs->get($medication->id);

                // Compare by time only — scheduled_at is a Carbon instance,
                // string comparison with "HH:MM" would fail due to seconds/timezone
                $matchedLog = $medLogs?->first(
                    fn($log) => $log->scheduled_at->format('H:i') === $time
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

        // Apply filter — when filtering by taken/missed, upcoming is irrelevant
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
