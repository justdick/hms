<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WardTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_admission_id',
        'from_ward_id',
        'from_bed_id',
        'to_ward_id',
        'transfer_reason',
        'transfer_notes',
        'transferred_by_id',
        'transferred_at',
    ];

    protected function casts(): array
    {
        return [
            'transferred_at' => 'datetime',
        ];
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(PatientAdmission::class, 'patient_admission_id');
    }

    public function fromWard(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'from_ward_id');
    }

    public function fromBed(): BelongsTo
    {
        return $this->belongsTo(Bed::class, 'from_bed_id');
    }

    public function toWard(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'to_ward_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by_id');
    }
}
