<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationAdministration extends Model
{
    /** @use HasFactory<\Database\Factories\MedicationAdministrationFactory> */
    use HasFactory;

    protected $fillable = [
        'prescription_id',
        'patient_admission_id',
        'administered_by_id',
        'scheduled_time',
        'administered_at',
        'status',
        'dosage_given',
        'route',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_time' => 'datetime',
            'administered_at' => 'datetime',
        ];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function patientAdmission(): BelongsTo
    {
        return $this->belongsTo(PatientAdmission::class);
    }

    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by_id');
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isGiven(): bool
    {
        return $this->status === 'given';
    }

    public function isHeld(): bool
    {
        return $this->status === 'held';
    }

    public function isDue(): bool
    {
        return $this->isScheduled() && $this->scheduled_time <= now();
    }

    public function scopeDue($query): void
    {
        $query->where('status', 'scheduled')
            ->where('scheduled_time', '<=', now());
    }

    public function scopeScheduled($query): void
    {
        $query->where('status', 'scheduled');
    }

    public function scopeGiven($query): void
    {
        $query->where('status', 'given');
    }
}
