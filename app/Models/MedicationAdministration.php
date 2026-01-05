<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Medication Administration Record (MAR)
 *
 * This model represents a log of medication administrations for admitted patients.
 * Instead of pre-scheduling doses, nurses record administrations as they happen.
 *
 * The prescription's frequency (TDS, BD, etc.) serves as guidance for how often
 * medication should be given, but the actual recording is on-demand.
 */
class MedicationAdministration extends Model
{
    /** @use HasFactory<\Database\Factories\MedicationAdministrationFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'prescription_id',
        'patient_admission_id',
        'administered_by_id',
        'administered_at',
        'status',
        'dosage_given',
        'route',
        'notes',
        'deleted_by_id',
    ];

    protected function casts(): array
    {
        return [
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

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_id');
    }

    // Status check methods
    public function isGiven(): bool
    {
        return $this->status === 'given';
    }

    public function isHeld(): bool
    {
        return $this->status === 'held';
    }

    public function isRefused(): bool
    {
        return $this->status === 'refused';
    }

    public function isOmitted(): bool
    {
        return $this->status === 'omitted';
    }

    // Scopes
    public function scopeGiven($query): void
    {
        $query->where('status', 'given');
    }

    public function scopeToday($query): void
    {
        $query->whereDate('administered_at', today());
    }

    public function scopeForPrescription($query, int $prescriptionId): void
    {
        $query->where('prescription_id', $prescriptionId);
    }
}
