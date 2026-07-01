<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityWaitlist extends Model
{
    protected $table = 'community_waitlist';

    protected $fillable = [
        'name', 'email', 'country', 'owns_band', 'social_platform',
    ];

    protected function casts(): array
    {
        return ['owns_band' => 'boolean'];
    }
}
