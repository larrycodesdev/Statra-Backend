<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hospital extends Model
{
    protected $fillable = [
        'name', 'address', 'city', 'country',
        'contact_email', 'contact_phone', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function doctors()
    {
        return $this->hasMany(User::class, 'hospital_id')->where('role', 'doctor');
    }

    public function staff()
    {
        return $this->hasMany(User::class, 'hospital_id')->whereIn('role', ['doctor', 'admin', 'staff']);
    }

    public function patients()
    {
        return $this->hasMany(Patient::class, 'hospital_id');
    }
}
