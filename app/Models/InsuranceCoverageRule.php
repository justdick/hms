<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceCoverageRule extends Model
{
    /** @use HasFactory<\Database\Factories\InsuranceCoverageRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'insurance_plan_id',
        'coverage_category',
        'item_code',
        'item_description',
        'is_covered',
        'coverage_type',
        'coverage_value',
        'patient_copay_percentage',
        'max_quantity_per_visit',
        'max_amount_per_visit',
        'requires_preauthorization',
        'is_active',
        'effective_from',
        'effective_to',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_covered' => 'boolean',
            'coverage_value' => 'decimal:2',
            'patient_copay_percentage' => 'decimal:2',
            'max_quantity_per_visit' => 'integer',
            'max_amount_per_visit' => 'decimal:2',
            'requires_preauthorization' => 'boolean',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InsurancePlan::class, 'insurance_plan_id');
    }

    public function scopeActive($query): void
    {
        $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            });
    }
}
