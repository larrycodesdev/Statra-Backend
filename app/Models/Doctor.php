<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    protected $fillable = [
        'user_id', 'hospital_name', 'department',
        'specialisation', 'license_number',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function patients()
    {
        return $this->hasMany(Patient::class, 'assigned_doctor_id', 'user_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'doctor_id', 'user_id');
    }
}
