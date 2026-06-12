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
        $medications = $request->user()->patient
            ->medications()
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success($medications);
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
                $matchedLog = $medLogs?->firstWhere('scheduled_at', $scheduledAt);

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
            $history = array_filter($history, fn($h) => $h['status'] === 'taken');
        } elseif ($filter === 'missed') {
            $history = array_filter($history, fn($h) => $h['status'] === 'missed');
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
