<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceTariff extends Model
{
    /** @use HasFactory<\Database\Factories\InsuranceTariffFactory> */
    use HasFactory;

    protected $fillable = [
        'insurance_plan_id',
        'item_type',
        'item_code',
        'item_description',
        'standard_price',
        'insurance_tariff',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'standard_price' => 'decimal:2',
            'insurance_tariff' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InsurancePlan::class, 'insurance_plan_id');
    }

    public function scopeEffective($query): void
    {
        $query->where('effective_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            });
    }
}
