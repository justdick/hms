<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Drug extends Model
{
    /** @use HasFactory<\Database\Factories\DrugFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'generic_name',
        'brand_name',
        'drug_code',
        'category',
        'form',
        'strength',
        'description',
        'unit_price',
        'unit_type',
        'bottle_size',
        'minimum_stock_level',
        'maximum_stock_level',
        'is_active',
        'nhis_claim_qty_as_one',
        'migrated_from_mittag',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'is_active' => 'boolean',
            'nhis_claim_qty_as_one' => 'boolean',
        ];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(DrugBatch::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    /**
     * Get the NHIS item mapping for this drug.
     */
    public function nhisMapping(): HasOne
    {
        return $this->hasOne(NhisItemMapping::class, 'item_id')
            ->where('item_type', 'drug');
    }

    public function availableBatches(): HasMany
    {
        return $this->batches()
            ->where('quantity_remaining', '>', 0)
            ->where('expiry_date', '>', now())
            ->orderBy('expiry_date', 'asc');
    }

    public function getTotalStockAttribute(): int
    {
        return $this->batches()
            ->where('quantity_remaining', '>', 0)
            ->where('expiry_date', '>', now())
            ->sum('quantity_remaining');
    }

    public function isLowStock(): bool
    {
        return $this->total_stock <= $this->minimum_stock_level;
    }

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }

    public function scopeCategory($query, string $category): void
    {
        $query->where('category', $category);
    }

    public function scopeLowStock($query): void
    {
        $query->whereHas('batches', function ($batchQuery) {
            $batchQuery->where('quantity_remaining', '>', 0)
                ->where('expiry_date', '>', now());
        }, '<=', 'minimum_stock_level');
    }
}
