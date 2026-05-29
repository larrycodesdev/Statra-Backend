<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckIn extends Model
{
    protected $fillable = [
        'user_id', 'pid', 'name', 'genotype', 'meds',
        'pain', 'fatigue', 'sleep', 'hydration', 'condition',
        'symptoms', 'flags', 'triggers',
        'safety', 'notes',
        'total', 'display_score', 'status', 'red_flag',
        'reason', 'scores', 'geno_mult', 'checked_in_at',
    ];

    protected function casts(): array
    {
        return [
            'symptoms'      => 'array',
            'flags'         => 'array',
            'triggers'      => 'array',
            'scores'        => 'array',
            'red_flag'      => 'boolean',
            'pain'          => 'integer',
            'total'         => 'integer',
            'geno_mult'     => 'float',
            'checked_in_at' => 'datetime',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
