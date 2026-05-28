<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientSettings extends Model
{
    protected $fillable = [
        'patient_id',
        'allow_doctor_view_records',
        'allow_doctor_view_data',
        'share_symptom_pain_data',
        'share_medication_records',
        'reminder_enabled',
        'smart_alert_enabled',
    ];

    protected function casts(): array
    {
        return [
            'allow_doctor_view_records' => 'boolean',
            'allow_doctor_view_data'    => 'boolean',
            'share_symptom_pain_data'   => 'boolean',
            'share_medication_records'  => 'boolean',
            'reminder_enabled'          => 'boolean',
            'smart_alert_enabled'       => 'boolean',
        ];
    }

    public function patient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
