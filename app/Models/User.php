<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'uuid', 'name',
        'first_name', 'last_name', 'username',
        'email', 'password',
        'role', 'phone', 'avatar', 'fcm_token',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (User $user) {
            if (empty($user->uuid)) {
                $user->uuid = Str::uuid()->toString();
            }
        });
    }

    // Always returns full name from first+last (falls back to stored name)
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}") ?: ($this->name ?? '');
    }

    public function patient(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Patient::class);
    }

    public function doctor(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    public function isPatient(): bool
    {
        return $this->role === 'patient';
    }

    public function isDoctor(): bool
    {
        return $this->role === 'doctor';
    }
}
