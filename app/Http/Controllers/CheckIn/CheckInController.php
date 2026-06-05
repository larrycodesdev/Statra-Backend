<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\StoreCheckInRequest;
use App\Http\Resources\CheckInResource;
use App\Services\RiskCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CheckInController extends Controller
{
    public function __construct(private readonly RiskCalculatorService $riskCalculator) {}

    // GET /api/v1/checkin/history — full history for the logged-in check-in user
    public function index(): AnonymousResourceCollection
    {
        $checkIns = auth()->user()
            ->checkIns()
            ->orderByDesc('checked_in_at')
            ->get();

        return CheckInResource::collection($checkIns);
    }

    // POST /api/v1/checkin/check-in — submit a new check-in
    public function store(StoreCheckInRequest $request): CheckInResource
    {
        $data   = $request->validated();

        // Always recalculate server-side — never trust scores from client
        $result = $this->riskCalculator->calculate($data);

        $user    = auth()->user();
        $checkIn = $user->checkIns()->create([
            'pid'           => $user->pid,
            'name'          => $user->name,
            'genotype'      => $data['genotype'],
            'meds'          => $data['meds'],
            'pain'          => $data['pain'],
            'fatigue'       => $data['fatigue'],
            'sleep'         => $data['sleep'],
            'hydration'     => $data['hydration'],
            'condition'     => $data['condition'],
            'safety'        => $data['safety'],
            'notes'         => $data['notes'] ?? null,
            'symptoms'      => $data['symptoms'] ?? [],
            'flags'         => $data['flags']    ?? [],
            'triggers'      => $data['triggers'] ?? [],
            'total'         => $result['total'],
            'display_score' => $result['display_score'],
            'status'        => $result['status'],
            'red_flag'      => $result['red_flag'],
            'reason'        => $result['reason'],
            'scores'        => $result['scores'],
            'geno_mult'     => $result['geno_mult'],
            'checked_in_at' => now(),
        ]);

        return new CheckInResource($checkIn);
    }

    // GET /api/v1/checkin/check-in/latest — most recent check-in
    public function latest(): JsonResponse|CheckInResource
    {
        $checkIn = auth()->user()
            ->checkIns()
            ->latest('checked_in_at')
            ->first();

        if (!$checkIn) {
            return response()->json([
                'success' => true,
                'message' => 'No check-ins yet.',
                'data'    => null,
            ], 200);
        }

        return new CheckInResource($checkIn);
    }
}
