<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompositeDeviationScore extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'patient_id', 'computed_at',
        'temp_z', 'spo2_z', 'hr_z', 'hrv_z', 'activity_z',
        'temp_contribution', 'spo2_contribution', 'hr_contribution',
        'hrv_contribution', 'activity_contribution',
        'total_score', 'status', 'confidence',
        'temperature_absolute', 'outreach_recommended', 'outreach_reason',
    ];

    protected function casts(): array
    {
        return [
            'computed_at'          => 'datetime',
            'outreach_recommended' => 'boolean',
            'total_score'          => 'float',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
