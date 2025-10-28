<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsurancePlan extends Model
{
    /** @use HasFactory<\Database\Factories\InsurancePlanFactory> */
    use HasFactory;

    protected $fillable = [
        'insurance_provider_id',
        'plan_name',
        'plan_code',
        'plan_type',
        'coverage_type',
        'annual_limit',
        'visit_limit',
        'default_copay_percentage',
        'requires_referral',
        'is_active',
        'effective_from',
        'effective_to',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'annual_limit' => 'decimal:2',
            'default_copay_percentage' => 'decimal:2',
            'visit_limit' => 'integer',
            'requires_referral' => 'boolean',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class, 'insurance_provider_id');
    }

    public function patientInsurances(): HasMany
    {
        return $this->hasMany(PatientInsurance::class);
    }

    public function coverageRules(): HasMany
    {
        return $this->hasMany(InsuranceCoverageRule::class);
    }

    public function tariffs(): HasMany
    {
        return $this->hasMany(InsuranceTariff::class);
    }

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }
}
