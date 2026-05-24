<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $fillable = [
        'patient_id', 'vital_reading_id', 'type',
        'level', 'message', 'status', 'assigned_to', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'level' => 'integer',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function vitalReading()
    {
        return $this->belongsTo(VitalReading::class);
    }

    public function assignedDoctor()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
