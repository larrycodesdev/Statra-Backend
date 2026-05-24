<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = [
        'patient_id', 'device_id', 'device_model',
        'firmware_version', 'platform', 'last_synced_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function vitalReadings()
    {
        return $this->hasMany(VitalReading::class);
    }
}
