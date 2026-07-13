<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Mail\HospitalAdminInviteMail;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class HospitalController extends Controller
{
    public function index(): JsonResponse
    {
        $hospitals = Hospital::withCount(['patients', 'staff'])
            ->orderBy('name')
            ->get();

        return ApiResponse::success($hospitals->map(fn ($h) => $this->hospitalResource($h)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255', 'unique:hospitals,name'],
            'address'       => ['nullable', 'string', 'max:500'],
            'city'          => ['nullable', 'string', 'max:100'],
            'country'       => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $hospital = Hospital::create($data);

        return ApiResponse::created($this->hospitalResource($hospital), 'Hospital created.');
    }

    public function show(int $id): JsonResponse
    {
        $hospital = Hospital::withCount(['patients', 'staff'])->findOrFail($id);

        $admins = User::where('hospital_id', $id)
            ->where('role', 'admin')
            ->get(['id', 'first_name', 'last_name', 'name', 'email', 'phone', 'approval_status', 'created_at']);

        $resource = $this->hospitalResource($hospital);
        $resource['admins'] = $admins->map(fn ($a) => [
            'id'             => $a->id,
            'name'           => trim("{$a->first_name} {$a->last_name}") ?: $a->name,
            'email'          => $a->email,
            'phone'          => $a->phone,
            'approvalStatus' => $a->approval_status,
            'createdAt'      => $a->created_at->toISOString(),
        ]);

        return ApiResponse::success($resource);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $hospital = Hospital::findOrFail($id);

        $data = $request->validate([
            'name'          => ['sometimes', 'string', 'max:255', 'unique:hospitals,name,' . $id],
            'address'       => ['nullable', 'string', 'max:500'],
            'city'          => ['nullable', 'string', 'max:100'],
            'country'       => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $hospital->update($data);

        return ApiResponse::success($this->hospitalResource($hospital), 'Hospital updated.');
    }

    public function toggleActive(int $id): JsonResponse
    {
        $hospital = Hospital::findOrFail($id);
        $hospital->update(['is_active' => !$hospital->is_active]);

        $state = $hospital->is_active ? 'activated' : 'deactivated';

        return ApiResponse::success(['isActive' => $hospital->is_active], "Hospital {$state}.");
    }

    public function createAdmin(Request $request, int $id): JsonResponse
    {
        $hospital = Hospital::findOrFail($id);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'unique:users,email'],
            'phone'      => ['nullable', 'string', 'max:20'],
        ]);

        $inviteToken   = Str::random(48);
        $inviteExpires = now()->addHours(72);

        $admin = User::create([
            'first_name'         => $data['first_name'],
            'last_name'          => $data['last_name'],
            'name'               => $data['first_name'] . ' ' . $data['last_name'],
            'email'              => $data['email'],
            'password'           => Hash::make(Str::random(32)),
            'role'               => 'admin',
            'hospital_id'        => $hospital->id,
            'phone'              => $data['phone'] ?? null,
            'approval_status'    => 'approved',
            'invite_token'       => hash('sha256', $inviteToken),
            'invite_expires_at'  => $inviteExpires,
        ]);

        $inviteUrl = config('app.dashboard_url', 'https://dashboard.statra.health')
            . '/auth/accept-invite?token=' . $inviteToken
            . '&email=' . urlencode($admin->email);

        Mail::to($admin->email)->send(new HospitalAdminInviteMail(
            name:         $data['first_name'],
            hospitalName: $hospital->name,
            inviteUrl:    $inviteUrl,
        ));

        return ApiResponse::created([
            'id'          => $admin->id,
            'name'        => $admin->name,
            'email'       => $admin->email,
            'hospitalId'  => $hospital->id,
            'inviteSent'  => true,
        ], 'Admin created and invite sent.');
    }

    private function hospitalResource(Hospital $hospital): array
    {
        return [
            'id'           => $hospital->id,
            'name'         => $hospital->name,
            'address'      => $hospital->address,
            'city'         => $hospital->city,
            'country'      => $hospital->country,
            'contactEmail' => $hospital->contact_email,
            'contactPhone' => $hospital->contact_phone,
            'isActive'     => $hospital->is_active,
            'stats'        => [
                'totalPatients' => $hospital->patients_count ?? 0,
                'totalStaff'    => $hospital->staff_count    ?? 0,
            ],
            'createdAt'    => $hospital->created_at->toISOString(),
        ];
    }
}
