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
        'tariff_amount',
        'patient_copay_percentage',
        'patient_copay_amount',
        'is_unmapped',
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
            'tariff_amount' => 'decimal:2',
            'patient_copay_percentage' => 'decimal:2',
            'patient_copay_amount' => 'decimal:2',
            'is_unmapped' => 'boolean',
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

    public function history(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InsuranceCoverageRuleHistory::class, 'insurance_coverage_rule_id');
    }

    public function tariff(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(InsuranceTariff::class, 'item_code', 'item_code')
            ->where(function ($query) {
                $query->where('insurance_tariffs.insurance_plan_id', $this->insurance_plan_id)
                    ->where('insurance_tariffs.item_type', $this->coverage_category);
            })
            ->where('effective_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            });
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

    public function scopeGeneral($query): void
    {
        $query->whereNull('item_code');
    }

    public function scopeSpecific($query): void
    {
        $query->whereNotNull('item_code');
    }

    public function scopeForCategory($query, string $category): void
    {
        $query->where('coverage_category', $category);
    }

    public function scopeUnmapped($query): void
    {
        $query->where('is_unmapped', true);
    }

    public function scopeMapped($query): void
    {
        $query->where('is_unmapped', false);
    }

    public function getIsGeneralAttribute(): bool
    {
        return is_null($this->item_code);
    }

    public function getIsSpecificAttribute(): bool
    {
        return ! is_null($this->item_code);
    }

    public function getRuleTypeAttribute(): string
    {
        return $this->is_general ? 'general' : 'specific';
    }

    protected static function booted(): void
    {
        static::created(function ($rule) {
            $rule->recordHistory('created', null, $rule->getAttributes());
        });

        static::updating(function ($rule) {
            $rule->recordHistory('updated', $rule->getOriginal(), $rule->getAttributes());
        });

        static::saved(function ($rule) {
            static::clearCacheForRule($rule);
        });

        static::deleting(function ($rule) {
            $rule->recordHistory('deleted', $rule->getAttributes(), null);
        });

        static::deleted(function ($rule) {
            static::clearCacheForRule($rule);
        });
    }

    public function recordHistory(string $action, ?array $oldValues, ?array $newValues): void
    {
        // Get batch ID from session if exists (for grouping related changes)
        $batchId = session('coverage_rule_batch_id');

        InsuranceCoverageRuleHistory::create([
            'insurance_coverage_rule_id' => $this->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'batch_id' => $batchId,
        ]);
    }

    protected static function clearCacheForRule(self $rule): void
    {
        $categories = ['consultation', 'drug', 'lab', 'procedure', 'ward', 'nursing'];

        // Clear general rule cache for this category
        $generalCacheKey = "coverage_rule_general_{$rule->insurance_plan_id}_{$rule->coverage_category}";
        \Illuminate\Support\Facades\Cache::forget($generalCacheKey);

        // Clear specific rule cache if item_code exists
        if ($rule->item_code) {
            $specificCacheKey = "coverage_rule_specific_{$rule->insurance_plan_id}_{$rule->coverage_category}_{$rule->item_code}";
            \Illuminate\Support\Facades\Cache::forget($specificCacheKey);
        }
    }
}
