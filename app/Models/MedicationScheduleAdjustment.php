<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationScheduleAdjustment extends Model
{
    /** @use HasFactory<\Database\Factories\MedicationScheduleAdjustmentFactory> */
    use HasFactory;

    protected $fillable = [
        'medication_administration_id',
        'adjusted_by_id',
        'original_time',
        'adjusted_time',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'original_time' => 'datetime',
            'adjusted_time' => 'datetime',
        ];
    }

    public function medicationAdministration(): BelongsTo
    {
        return $this->belongsTo(MedicationAdministration::class);
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by_id');
    }
}
