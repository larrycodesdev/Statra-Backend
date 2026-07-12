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
        'uuid', 'pid', 'name',
        'first_name', 'last_name', 'username',
        'email', 'password',
        'role', 'phone', 'avatar', 'fcm_token',
        'hospital_id', 'approval_status',
        'password_reset_otp', 'password_reset_otp_expires_at',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'              => 'datetime',
            'password'                       => 'hashed',
            'password_reset_otp_expires_at'  => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (User $user) {
            if (empty($user->uuid)) {
                $user->uuid = Str::uuid()->toString();
            }
            if (empty($user->pid) && $user->role === 'checkin_user') {
                do {
                    $pid = 'STA-' . strtoupper(Str::random(6));
                } while (static::where('pid', $pid)->exists());
                $user->pid = $pid;
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

    public function checkIns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function hospital(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function isPatient(): bool  { return $this->role === 'patient'; }
    public function isDoctor(): bool   { return $this->role === 'doctor'; }
    public function isAdmin(): bool    { return $this->role === 'admin'; }
    public function isSuperAdmin(): bool { return $this->role === 'superadmin'; }
    public function isStaff(): bool    { return $this->role === 'staff'; }
    public function isApproved(): bool { return $this->approval_status === 'approved'; }

    public function getInitialsAttribute(): string
    {
        $name = $this->full_name;
        $parts = explode(' ', trim($name));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
        }
        return strtoupper(substr($name, 0, 2));
    }
}
