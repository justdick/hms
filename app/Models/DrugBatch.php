<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DrugBatch extends Model
{
    /** @use HasFactory<\Database\Factories\DrugBatchFactory> */
    use HasFactory;

    protected $fillable = [
        'drug_id',
        'supplier_id',
        'batch_number',
        'expiry_date',
        'manufacture_date',
        'quantity_received',
        'quantity_remaining',
        'cost_per_unit',
        'selling_price_per_unit',
        'received_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'manufacture_date' => 'date',
            'received_date' => 'date',
            'cost_per_unit' => 'decimal:2',
            'selling_price_per_unit' => 'decimal:2',
        ];
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function dispensings(): HasMany
    {
        return $this->hasMany(Dispensing::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expiry_date->lte(now()->addDays($days));
    }

    public function hasStock(): bool
    {
        return $this->quantity_remaining > 0;
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->isExpired()) {
            return 'expired';
        }

        if (! $this->hasStock()) {
            return 'out_of_stock';
        }

        if ($this->isExpiringSoon()) {
            return 'expiring_soon';
        }

        return 'in_stock';
    }

    public function scopeAvailable($query): void
    {
        $query->where('quantity_remaining', '>', 0)
            ->where('expiry_date', '>', now());
    }

    public function scopeExpired($query): void
    {
        $query->where('expiry_date', '<=', now());
    }

    public function scopeExpiringSoon($query, int $days = 30): void
    {
        $query->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>', now());
    }

    public function reduceStock(int $quantity): bool
    {
        if ($this->quantity_remaining >= $quantity) {
            $this->quantity_remaining -= $quantity;
            $this->save();

            return true;
        }

        return false;
    }
}
