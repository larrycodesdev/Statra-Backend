<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $fillable = [
        'user_id', 'genotype', 'blood_type', 'date_of_birth',
        'gender', 'emergency_contact_name', 'emergency_contact_phone',
        'assigned_doctor_id',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedDoctor()
    {
        return $this->belongsTo(User::class, 'assigned_doctor_id');
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function vitalReadings()
    {
        return $this->hasMany(VitalReading::class);
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }

    public function painLogs()
    {
        return $this->hasMany(PainLog::class);
    }

    public function medicationLogs()
    {
        return $this->hasMany(MedicationLog::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class);
    }
}
