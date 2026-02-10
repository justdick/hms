<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceClaimItem extends Model
{
    /** @use HasFactory<\Database\Factories\InsuranceClaimItemFactory> */
    use HasFactory;

    protected $fillable = [
        'insurance_claim_id',
        'charge_id',
        'item_date',
        'item_type',
        'code',
        'description',
        'quantity',
        'frequency',
        'unit_tariff',
        'subtotal',
        'is_covered',
        'coverage_percentage',
        'insurance_pays',
        'patient_pays',
        'is_approved',
        'is_unmapped',
        'has_flexible_copay',
        'rejection_reason',
        'notes',
        'nhis_tariff_id',
        'nhis_code',
        'nhis_price',
    ];

    protected function casts(): array
    {
        return [
            'item_date' => 'date',
            'quantity' => 'integer',
            'unit_tariff' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'is_covered' => 'boolean',
            'coverage_percentage' => 'decimal:2',
            'insurance_pays' => 'decimal:2',
            'patient_pays' => 'decimal:2',
            'is_approved' => 'boolean',
            'is_unmapped' => 'boolean',
            'has_flexible_copay' => 'boolean',
            'nhis_price' => 'decimal:2',
        ];
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class, 'insurance_claim_id');
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    public function nhisTariff(): BelongsTo
    {
        return $this->belongsTo(NhisTariff::class);
    }
}
