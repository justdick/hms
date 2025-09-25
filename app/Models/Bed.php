<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bed extends Model
{
    /** @use HasFactory<\Database\Factories\BedFactory> */
    use HasFactory;

    protected $fillable = [
        'bed_number',
        'ward_id',
        'status',
        'type',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'status' => 'string',
            'type' => 'string',
        ];
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function currentAdmission(): HasOne
    {
        return $this->hasOne(PatientAdmission::class)->where('status', 'admitted');
    }

    public function scopeAvailable($query): void
    {
        $query->where('status', 'available')->where('is_active', true);
    }

    public function scopeOccupied($query): void
    {
        $query->where('status', 'occupied');
    }

    public function markAsOccupied(): void
    {
        $this->update(['status' => 'occupied']);
        $this->ward->updateBedCounts();
    }

    public function markAsAvailable(): void
    {
        $this->update(['status' => 'available']);
        $this->ward->updateBedCounts();
    }
}
