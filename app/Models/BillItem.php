<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillItem extends Model
{
    /** @use HasFactory<\Database\Factories\BillItemFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_bill_id',
        'billing_service_id',
        'description',
        'quantity',
        'unit_price',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function patientBill(): BelongsTo
    {
        return $this->belongsTo(PatientBill::class);
    }

    public function billingService(): BelongsTo
    {
        return $this->belongsTo(BillingService::class);
    }

    protected static function booted(): void
    {
        static::saving(function (BillItem $billItem) {
            $billItem->total_price = $billItem->quantity * $billItem->unit_price;
        });
    }
}
