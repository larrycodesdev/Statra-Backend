<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medication extends Model
{
    protected $fillable = [
        'patient_id', 'name', 'dosage', 'time_to_use', 'frequency',
        'frequency_count', 'begin_date', 'end_date',
        'reminder_times', 'remind_me', 'active',
    ];

    protected function casts(): array
    {
        return [
            'patient_id'      => 'integer',
            'frequency_count' => 'integer',
            'reminder_times'  => 'array',
            'remind_me'       => 'boolean',
            'active'          => 'boolean',
            'begin_date'      => 'date',
            'end_date'        => 'date',
        ];
    }

    public function patient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function logs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MedicationLog::class);
    }
}
