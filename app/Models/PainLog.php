<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PainLog extends Model
{
    protected $fillable = [
        'patient_id', 'pain_level', 'location', 'notes', 'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'location' => 'array',
            'logged_at' => 'datetime',
            'pain_level' => 'integer',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
