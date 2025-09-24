<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientBill extends Model
{
    /** @use HasFactory<\Database\Factories\PatientBillFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'consultation_id',
        'bill_number',
        'total_amount',
        'paid_amount',
        'status',
        'issued_at',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_date' => 'date',
            'status' => 'string',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function billItems(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    public function scopeByStatus($query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopePending($query): void
    {
        $query->where('status', 'pending');
    }

    public function scopePartial($query): void
    {
        $query->where('status', 'partial');
    }

    public function scopePaid($query): void
    {
        $query->where('status', 'paid');
    }

    public function scopeOverdue($query): void
    {
        $query->where('due_date', '<', today())
            ->whereIn('status', ['pending', 'partial']);
    }

    public function getRemainingAmountAttribute(): string
    {
        return number_format($this->total_amount - $this->paid_amount, 2);
    }

    public function getIsFullyPaidAttribute(): bool
    {
        return $this->paid_amount >= $this->total_amount;
    }

    public function addPayment(float $amount): void
    {
        $newPaidAmount = $this->paid_amount + $amount;

        $status = 'partial';
        if ($newPaidAmount >= $this->total_amount) {
            $status = 'paid';
            $newPaidAmount = $this->total_amount;
        }

        $this->update([
            'paid_amount' => $newPaidAmount,
            'status' => $status,
        ]);
    }

    public function calculateTotal(): void
    {
        $total = $this->billItems()->sum('total_price');

        $this->update(['total_amount' => $total]);
    }
}
