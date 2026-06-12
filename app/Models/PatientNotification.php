<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientNotification extends Model
{
    protected $table = 'patient_notifications';

    protected $fillable = [
        'patient_id', 'type', 'title', 'body', 'data', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data'    => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function patient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
