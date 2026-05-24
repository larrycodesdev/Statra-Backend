<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicationLog extends Model
{
    protected $fillable = [
        'patient_id', 'medication_name', 'dosage',
        'scheduled_at', 'taken_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'taken_at' => 'datetime',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
