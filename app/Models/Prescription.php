<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prescription extends Model
{
    /** @use HasFactory<\Database\Factories\PrescriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'consultation_id',
        'medication_name',
        'dosage',
        'frequency',
        'duration',
        'instructions',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function scopeByStatus($query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopePrescribed($query): void
    {
        $query->where('status', 'prescribed');
    }

    public function scopeDispensed($query): void
    {
        $query->where('status', 'dispensed');
    }

    public function scopeCancelled($query): void
    {
        $query->where('status', 'cancelled');
    }

    public function markDispensed(): void
    {
        $this->update(['status' => 'dispensed']);
    }

    public function markCancelled(): void
    {
        $this->update(['status' => 'cancelled']);
    }
}
