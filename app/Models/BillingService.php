<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingService extends Model
{
    /** @use HasFactory<\Database\Factories\BillingServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'service_type',
        'service_code',
        'service_name',
        'base_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
            'service_type' => 'string',
        ];
    }

    public function billItems(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }

    public function scopeByType($query, string $type): void
    {
        $query->where('service_type', $type);
    }

    public function scopeConsultation($query): void
    {
        $query->where('service_type', 'consultation');
    }

    public function scopeLabTest($query): void
    {
        $query->where('service_type', 'lab_test');
    }

    public function scopeProcedure($query): void
    {
        $query->where('service_type', 'procedure');
    }

    public function scopeMedication($query): void
    {
        $query->where('service_type', 'medication');
    }
}
