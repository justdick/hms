<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientInsurance extends Model
{
    /** @use HasFactory<\Database\Factories\PatientInsuranceFactory> */
    use HasFactory;

    protected $table = 'patient_insurance';

    protected $fillable = [
        'patient_id',
        'insurance_plan_id',
        'membership_id',
        'policy_number',
        'folder_id_prefix',
        'is_dependent',
        'principal_member_name',
        'relationship_to_principal',
        'coverage_start_date',
        'coverage_end_date',
        'status',
        'card_number',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_dependent' => 'boolean',
            'coverage_start_date' => 'date',
            'coverage_end_date' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InsurancePlan::class, 'insurance_plan_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class);
    }

    public function scopeActive($query): void
    {
        $query->where('status', 'active')
            ->where('coverage_start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('coverage_end_date')
                    ->orWhere('coverage_end_date', '>=', now());
            });
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->coverage_start_date <= now()
            && ($this->coverage_end_date === null || $this->coverage_end_date >= now());
    }

    /**
     * Check if this insurance is NHIS.
     */
    public function isNhis(): bool
    {
        return $this->plan?->provider?->is_nhis ?? false;
    }

    /**
     * Check if the coverage has expired.
     */
    public function isExpired(): bool
    {
        if ($this->coverage_end_date === null) {
            return false;
        }

        return $this->coverage_end_date->isPast();
    }
}
