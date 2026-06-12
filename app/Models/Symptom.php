<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Symptom extends Model
{
    protected $fillable = [
        'patient_id', 'symptom', 'severity', 'severity_label',
        'body_locations', 'duration', 'triggers', 'on_medication',
        'notes', 'mood', 'edit_count', 'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'body_locations' => 'array',
            'triggers'       => 'array',
            'on_medication'  => 'boolean',
            'logged_at'      => 'datetime',
        ];
    }

    public function patient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
