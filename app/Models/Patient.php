<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $fillable = [
        'user_id', 'genotype', 'blood_type', 'date_of_birth',
        'gender', 'condition', 'assigned_doctor_id',
        'calibration_status', 'calibration_start_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'condition'     => 'array',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedDoctor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_doctor_id');
    }

    public function settings(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PatientSettings::class);
    }

    public function emergencyContacts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    public function devices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function vitalReadings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VitalReading::class);
    }

    public function alerts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function painLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PainLog::class);
    }

    public function medicationLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MedicationLog::class);
    }

    public function appointments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function medicalRecords(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    public function symptoms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Symptom::class);
    }

    public function medications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Medication::class);
    }

    public function notifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PatientNotification::class);
    }

    public function baselines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PatientBaseline::class);
    }

    public function compositeDeviationScores(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CompositeDeviationScore::class);
    }

    // Age derived from date_of_birth
    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }
}
