<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Symptom extends Model
{
    protected $fillable = [
        'patient_id', 'symptom', 'severity', 'severity_label',
        'body_locations', 'pain_areas', 'pain_level',
        'duration', 'triggers', 'on_medication',
        'notes', 'mood', 'edit_count', 'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'body_locations' => 'array',
            'pain_areas'     => 'array',
            'pain_level'     => 'integer',
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
