<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VitalReading extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'patient_id', 'device_id', 'type',
        'value', 'unit', 'recorded_at',
        'activity_context', 'quality_flag',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'recorded_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function alert()
    {
        return $this->hasOne(Alert::class);
    }
}
