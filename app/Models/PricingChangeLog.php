<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingChangeLog extends Model
{
    /** @use HasFactory<\Database\Factories\PricingChangeLogFactory> */
    use HasFactory;

    public const FIELD_CASH_PRICE = 'cash_price';

    public const FIELD_COPAY = 'copay';

    public const FIELD_COVERAGE = 'coverage';

    public const FIELD_TARIFF = 'tariff';

    public const TYPE_DRUG = 'drug';

    public const TYPE_LAB = 'lab';

    public const TYPE_CONSULTATION = 'consultation';

    public const TYPE_PROCEDURE = 'procedure';

    protected $fillable = [
        'item_type',
        'item_id',
        'item_code',
        'field_changed',
        'insurance_plan_id',
        'old_value',
        'new_value',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'decimal:2',
            'new_value' => 'decimal:2',
        ];
    }

    /**
     * Get the user who made the change.
     */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Get the insurance plan if applicable.
     */
    public function insurancePlan(): BelongsTo
    {
        return $this->belongsTo(InsurancePlan::class);
    }

    /**
     * Scope to filter by item type and ID.
     */
    public function scopeForItem(Builder $query, string $itemType, int $itemId): Builder
    {
        return $query->where('item_type', $itemType)->where('item_id', $itemId);
    }

    /**
     * Scope to filter by item code.
     */
    public function scopeForItemCode(Builder $query, string $itemCode): Builder
    {
        return $query->where('item_code', $itemCode);
    }

    /**
     * Scope to filter by insurance plan.
     */
    public function scopeForPlan(Builder $query, ?int $insurancePlanId): Builder
    {
        if ($insurancePlanId === null) {
            return $query->whereNull('insurance_plan_id');
        }

        return $query->where('insurance_plan_id', $insurancePlanId);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeInDateRange(Builder $query, ?string $startDate, ?string $endDate): Builder
    {
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query;
    }

    /**
     * Scope to filter by field changed.
     */
    public function scopeForField(Builder $query, string $field): Builder
    {
        return $query->where('field_changed', $field);
    }

    /**
     * Scope to filter cash price changes only.
     */
    public function scopeCashPriceChanges(Builder $query): Builder
    {
        return $query->where('field_changed', self::FIELD_CASH_PRICE);
    }

    /**
     * Scope to filter copay changes only.
     */
    public function scopeCopayChanges(Builder $query): Builder
    {
        return $query->where('field_changed', self::FIELD_COPAY);
    }

    /**
     * Create a pricing change log entry.
     */
    public static function logChange(
        string $itemType,
        int $itemId,
        ?string $itemCode,
        string $fieldChanged,
        ?int $insurancePlanId,
        ?float $oldValue,
        float $newValue,
        int $changedBy
    ): self {
        return self::create([
            'item_type' => $itemType,
            'item_id' => $itemId,
            'item_code' => $itemCode,
            'field_changed' => $fieldChanged,
            'insurance_plan_id' => $insurancePlanId,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'changed_by' => $changedBy,
        ]);
    }
}
