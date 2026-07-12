<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientBaseline extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'patient_id', 'signal_type', 'activity_context',
        'rolling_mean', 'rolling_variance', 'rolling_stddev',
        'sample_count', 'window_days', 'baseline_confidence',
        'last_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'rolling_mean'     => 'float',
            'rolling_variance' => 'float',
            'rolling_stddev'   => 'float',
            'last_updated_at'  => 'datetime',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
