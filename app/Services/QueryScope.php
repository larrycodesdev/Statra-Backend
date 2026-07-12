<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class QueryScope
{
    public function patients(Request $request): Builder
    {
        $user  = $request->user();
        $query = Patient::query();

        return match ($user->role) {
            'doctor'     => $query->where('assigned_doctor_id', $user->id),
            'admin'      => $query->where('hospital_id', $user->hospital_id),
            'staff'      => $query->where('hospital_id', $user->hospital_id),
            'superadmin' => $query,
            default      => $query->whereRaw('1=0'),
        };
    }

    public function alerts(Request $request): Builder
    {
        $user  = $request->user();
        $query = Alert::query();

        return match ($user->role) {
            'doctor' => $query->whereHas(
                'patient', fn ($q) => $q->where('assigned_doctor_id', $user->id)
            ),
            'admin', 'staff' => $query->whereHas(
                'patient', fn ($q) => $q->where('hospital_id', $user->hospital_id)
            ),
            'superadmin' => $query,
            default      => $query->whereRaw('1=0'),
        };
    }

    public function canWrite(Request $request, Patient $patient): bool
    {
        $user = $request->user();

        return match ($user->role) {
            'doctor'     => (int) $patient->assigned_doctor_id === (int) $user->id,
            'admin'      => (int) $patient->hospital_id === (int) $user->hospital_id,
            'superadmin' => true,
            default      => false,
        };
    }
}
