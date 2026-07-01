<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Affiliate extends Model
{
    protected $fillable = [
        'name', 'email', 'country', 'owns_band', 'social_platform',
    ];

    protected function casts(): array
    {
        return ['owns_band' => 'boolean'];
    }
}
