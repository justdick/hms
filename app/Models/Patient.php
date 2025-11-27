<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Patient extends Model
{
    /** @use HasFactory<\Database\Factories\PatientFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_number',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'phone_number',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'national_id',
        'status',
        'past_medical_surgical_history',
        'drug_history',
        'family_history',
        'social_history',
    ];

    protected $appends = ['full_name', 'age'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'status' => 'string',
            'gender' => 'string',
        ];
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(PatientCheckin::class);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class);
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(PatientAdmission::class);
    }

    public function activeAdmission(): HasOne
    {
        return $this->hasOne(PatientAdmission::class)->where('status', 'admitted');
    }

    public function insurancePlans(): HasMany
    {
        return $this->hasMany(PatientInsurance::class);
    }

    public function activeInsurance(): HasOne
    {
        return $this->hasOne(PatientInsurance::class)
            ->where('status', 'active')
            ->where('coverage_start_date', '<=', now())
            ->where(function ($query) {
                $query->whereNull('coverage_end_date')
                    ->orWhere('coverage_end_date', '>=', now());
            });
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function scopeSearch($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('patient_number', 'like', "%{$search}%")
                ->orWhere('phone_number', 'like', "%{$search}%");
        });
    }

    /**
     * Get the patient's active NHIS insurance if they have one.
     */
    public function activeNhisInsurance(): HasOne
    {
        return $this->hasOne(PatientInsurance::class)
            ->whereHas('plan.provider', fn ($q) => $q->where('is_nhis', true))
            ->where('status', 'active')
            ->where('coverage_start_date', '<=', now())
            ->where(function ($query) {
                $query->whereNull('coverage_end_date')
                    ->orWhere('coverage_end_date', '>=', now());
            });
    }

    /**
     * Check if the patient has valid NHIS coverage.
     */
    public function hasValidNhis(): bool
    {
        return $this->activeNhisInsurance()->exists();
    }
}
