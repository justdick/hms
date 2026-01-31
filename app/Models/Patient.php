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
        'migrated_from_mittag',
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

    /**
     * Get all active admissions for the patient (supports multiple concurrent admissions).
     */
    public function activeAdmissions(): HasMany
    {
        return $this->hasMany(PatientAdmission::class)->where('status', 'admitted');
    }

    public function insurancePlans(): HasMany
    {
        return $this->hasMany(PatientInsurance::class);
    }

    public function account(): HasOne
    {
        return $this->hasOne(PatientAccount::class);
    }

    public function getAccountBalanceAttribute(): float
    {
        return (float) ($this->account?->balance ?? 0);
    }

    public function getAvailableBalanceAttribute(): float
    {
        return (float) ($this->account?->available_balance ?? 0);
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
            // Check if search contains multiple words (likely first + last name)
            $words = preg_split('/\s+/', trim($search));

            if (count($words) >= 2) {
                // Multi-word search: match first word against first_name AND second against last_name
                $q->where(function ($subQ) use ($words) {
                    $subQ->where('first_name', 'like', "%{$words[0]}%")
                        ->where('last_name', 'like', "%{$words[1]}%");
                })->orWhere(function ($subQ) use ($words) {
                    // Also try reverse order (last_name first_name)
                    $subQ->where('last_name', 'like', "%{$words[0]}%")
                        ->where('first_name', 'like', "%{$words[1]}%");
                });
            } else {
                // Check if search looks like a patient/folder number (contains / or starts with digits)
                $looksLikePatientNumber = preg_match('/^\d+\/\d+$/', $search) || preg_match('/^PAT\d+$/i', $search);

                if ($looksLikePatientNumber) {
                    // Exact match for patient numbers
                    $q->where('patient_number', $search);
                } else {
                    // Fuzzy search for names, phone numbers, and insurance membership IDs
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhereHas('insurancePlans', function ($insuranceQuery) use ($search) {
                            $insuranceQuery->where('membership_id', 'like', "%{$search}%");
                        });
                }
            }
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

    /**
     * Check if the patient has credit privilege (via PatientAccount).
     */
    public function hasCreditPrivilege(): bool
    {
        return $this->account && $this->account->hasCreditPrivilege();
    }

    /**
     * Get credit limit from PatientAccount.
     */
    public function getCreditLimitAttribute(): float
    {
        return (float) ($this->account?->credit_limit ?? 0);
    }

    /**
     * Get deposit balance from PatientAccount.
     */
    public function getDepositBalanceAttribute(): float
    {
        return (float) ($this->account?->deposit_balance ?? 0);
    }

    /**
     * Get amount currently owed (negative balance in account).
     */
    public function getAmountOwedAttribute(): float
    {
        return (float) ($this->account?->amount_owed ?? 0);
    }

    /**
     * Get remaining credit available.
     */
    public function getRemainingCreditAttribute(): float
    {
        return (float) ($this->account?->remaining_credit ?? 0);
    }

    /**
     * Check if patient can receive services worth a given amount.
     */
    public function canReceiveServices(float $amount = 0): bool
    {
        // Has deposit balance
        if ($this->deposit_balance > 0) {
            return true;
        }

        // Has credit privilege and within limit
        if ($this->hasCreditPrivilege() && $this->account) {
            return $this->account->canReceiveServices($amount);
        }

        return false;
    }

    /**
     * Scope to get only credit-eligible patients (via PatientAccount).
     */
    public function scopeCreditEligible($query)
    {
        return $query->whereHas('account', fn ($aq) => $aq->where('credit_limit', '>', 0));
    }
}
